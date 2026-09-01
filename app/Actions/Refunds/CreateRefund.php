<?php

namespace App\Actions\Refunds;

use App\Enums\PaymentStatus;
use App\Enums\RefundAllocationType;
use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Models\VendorOrder;
use App\Services\Refunds\RefundFinancials;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateRefund
{
    public function __construct(
        private readonly RefundNumberGenerator $refundNumberGenerator,
        private readonly RefundFinancials $refundFinancials,
    ) {
    }

    /**
     * @param array<int, array{type:string, amount:int, quantity?:int|null, order_item_id?:int|null, vendor_order_id?:int|null}> $allocations
     */
    public function handle(Order $order, User $admin, string $reason, ?string $notes, array $allocations): Refund
    {
        return DB::transaction(function () use ($order, $admin, $reason, $notes, $allocations): Refund {
            $order = Order::query()->with('payment')->lockForUpdate()->findOrFail($order->id);
            $payment = Payment::query()->whereKey($order->payment?->id)->lockForUpdate()->first();

            if (! $payment || $payment->order_id !== $order->id) {
                throw ValidationException::withMessages(['refund' => 'This order does not have a refundable payment.']);
            }

            if (! in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true)) {
                throw ValidationException::withMessages(['refund' => 'Only collected payments can be refunded.']);
            }

            if ($allocations === []) {
                throw ValidationException::withMessages(['allocations' => 'At least one refund allocation is required.']);
            }

            $prepared = $this->prepareAllocations($order, $allocations);
            $this->validatePreparedCapacities($prepared);
            $amount = array_sum(array_column($prepared, 'amount'));

            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Refund amount must be greater than zero.']);
            }

            $summary = $this->refundFinancials->summaryForPayment($payment);

            if ($amount > $summary->refundableAmount()) {
                throw ValidationException::withMessages(['amount' => 'Refund amount exceeds the remaining refundable balance.']);
            }

            $refund = $payment->refunds()->create([
                'refund_number' => $this->refundNumberGenerator->generate(),
                'order_id' => $order->id,
                'status' => RefundStatus::Pending,
                'amount' => $amount,
                'currency' => $payment->currency,
                'reason' => $reason,
                'notes' => $notes,
                'provider' => $payment->provider,
                'requested_by' => $admin->id,
                'requested_at' => now(),
            ]);

            foreach ($prepared as $allocation) {
                $refund->allocations()->create($allocation);
            }

            return $refund->load(['payment.order', 'allocations']);
        }, 3);
    }

    private function prepareAllocations(Order $order, array $allocations): array
    {
        $prepared = [];

        foreach ($allocations as $index => $allocation) {
            $type = RefundAllocationType::from($allocation['type']);
            $amount = (int) $allocation['amount'];

            if ($amount <= 0) {
                continue;
            }

            $prepared[] = match ($type) {
                RefundAllocationType::Item => $this->prepareItemAllocation($order, $allocation, $amount, $index),
                RefundAllocationType::Shipping => $this->prepareShippingAllocation($order, $allocation, $amount, $index),
                RefundAllocationType::Adjustment => $this->prepareAdjustmentAllocation($order, $allocation, $amount, $index),
            };
        }

        return $prepared;
    }

    private function validatePreparedCapacities(array $prepared): void
    {
        $itemGroups = collect($prepared)
            ->where('type', RefundAllocationType::Item)
            ->groupBy('order_item_id');

        foreach ($itemGroups as $itemId => $allocations) {
            $item = OrderItem::query()->findOrFail($itemId);
            $amount = (int) $allocations->sum('amount');
            $quantity = (int) $allocations->sum('quantity');

            if ($amount > $item->line_total - $this->refundFinancials->protectedItemAmount($item->id)) {
                throw ValidationException::withMessages(['allocations' => 'Refund amount exceeds available item value.']);
            }

            if ($quantity > 0 && $quantity > $item->quantity - $this->refundFinancials->protectedItemQuantity($item->id)) {
                throw ValidationException::withMessages(['allocations' => 'Refund quantity exceeds available item quantity.']);
            }
        }

        $shippingGroups = collect($prepared)
            ->where('type', RefundAllocationType::Shipping)
            ->groupBy('vendor_order_id');

        foreach ($shippingGroups as $vendorOrderId => $allocations) {
            $vendorOrder = VendorOrder::query()->findOrFail($vendorOrderId);

            if ((int) $allocations->sum('amount') > $vendorOrder->shipping_total - $this->refundFinancials->protectedShippingAmount($vendorOrder->id)) {
                throw ValidationException::withMessages(['allocations' => 'Refund amount exceeds available shipping value.']);
            }
        }
    }

    private function prepareAdjustmentAllocation(Order $order, array $allocation, int $amount, int $index): array
    {
        $vendorOrderId = isset($allocation['vendor_order_id']) && $allocation['vendor_order_id'] !== null
            ? (int) $allocation['vendor_order_id']
            : null;

        if ($vendorOrderId && ! VendorOrder::query()->whereKey($vendorOrderId)->where('order_id', $order->id)->exists()) {
            throw ValidationException::withMessages(["allocations.{$index}.vendor_order_id" => 'Refund adjustment seller order must belong to this order.']);
        }

        return [
            'type' => RefundAllocationType::Adjustment,
            'amount' => $amount,
            'quantity' => null,
            'vendor_order_id' => $vendorOrderId,
            'order_item_id' => null,
        ];
    }

    private function prepareItemAllocation(Order $order, array $allocation, int $amount, int $index): array
    {
        $itemId = (int) ($allocation['order_item_id'] ?? 0);
        $item = OrderItem::query()
            ->whereKey($itemId)
            ->whereHas('vendorOrder', fn ($query) => $query->where('order_id', $order->id))
            ->with('vendorOrder')
            ->first();

        if (! $item) {
            throw ValidationException::withMessages(["allocations.{$index}.order_item_id" => 'Refund item must belong to this order.']);
        }

        $quantity = isset($allocation['quantity']) && $allocation['quantity'] !== null ? (int) $allocation['quantity'] : null;

        if ($quantity !== null) {
            if ($quantity <= 0 || $quantity > $item->quantity - $this->refundFinancials->protectedItemQuantity($item->id)) {
                throw ValidationException::withMessages(["allocations.{$index}.quantity" => 'Refund quantity exceeds available item quantity.']);
            }

            if ($amount !== $quantity * $item->unit_price) {
                throw ValidationException::withMessages(["allocations.{$index}.amount" => 'Quantity-based item refunds must equal quantity times unit price.']);
            }
        }

        if ($amount > $item->line_total - $this->refundFinancials->protectedItemAmount($item->id)) {
            throw ValidationException::withMessages(["allocations.{$index}.amount" => 'Refund amount exceeds available item value.']);
        }

        return [
            'type' => RefundAllocationType::Item,
            'amount' => $amount,
            'quantity' => $quantity,
            'vendor_order_id' => $item->vendor_order_id,
            'order_item_id' => $item->id,
        ];
    }

    private function prepareShippingAllocation(Order $order, array $allocation, int $amount, int $index): array
    {
        $vendorOrderId = (int) ($allocation['vendor_order_id'] ?? 0);
        $vendorOrder = VendorOrder::query()
            ->whereKey($vendorOrderId)
            ->where('order_id', $order->id)
            ->first();

        if (! $vendorOrder) {
            throw ValidationException::withMessages(["allocations.{$index}.vendor_order_id" => 'Refund shipping seller order must belong to this order.']);
        }

        if ($amount > $vendorOrder->shipping_total - $this->refundFinancials->protectedShippingAmount($vendorOrder->id)) {
            throw ValidationException::withMessages(["allocations.{$index}.amount" => 'Refund amount exceeds available shipping value.']);
        }

        return [
            'type' => RefundAllocationType::Shipping,
            'amount' => $amount,
            'quantity' => null,
            'vendor_order_id' => $vendorOrder->id,
            'order_item_id' => null,
        ];
    }
}
