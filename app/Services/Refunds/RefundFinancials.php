<?php

namespace App\Services\Refunds;

use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\VendorOrder;

class RefundFinancials
{
    public function summaryForPayment(Payment $payment): PaymentFinancialSummary
    {
        $refunds = $payment->relationLoaded('refunds')
            ? $payment->refunds
            : $payment->refunds()->get();

        $refunded = 0;
        $pending = 0;
        foreach ($refunds as $refund) {
            if ($refund->idempotency_key) {
                foreach ($refund->fundingAllocations as $funding) {
                    if ($funding->source_type !== \App\Enums\RefundFundingSource::ExternalPayment) continue;
                    if ($funding->status === \App\Enums\RefundFundingStatus::Completed) $refunded += $funding->amount;
                    elseif ($refund->status->protectsCapacity()) $pending += $funding->amount;
                }
            } else {
                if ($refund->status === RefundStatus::Succeeded) $refunded += $refund->amount;
                elseif ($refund->status->protectsCapacity()) $pending += $refund->amount;
            }
        }
        return new PaymentFinancialSummary((int) $payment->amount, $refunded, $pending);
    }

    public function protectedItemAmount(int $orderItemId): int
    {
        return (int) \App\Models\RefundAllocation::query()
            ->where('order_item_id', $orderItemId)
            ->whereHas('refund', fn ($query) => $query->whereIn('status', [
                RefundStatus::Pending->value,
                RefundStatus::Processing->value,
                RefundStatus::PartiallyProcessed->value,
                RefundStatus::Succeeded->value,
            ]))
            ->sum('amount');
    }

    public function protectedItemQuantity(int $orderItemId): int
    {
        return (int) \App\Models\RefundAllocation::query()
            ->where('order_item_id', $orderItemId)
            ->whereNotNull('quantity')
            ->whereHas('refund', fn ($query) => $query->whereIn('status', [
                RefundStatus::Pending->value,
                RefundStatus::Processing->value,
                RefundStatus::PartiallyProcessed->value,
                RefundStatus::Succeeded->value,
            ]))
            ->sum('quantity');
    }

    public function protectedShippingAmount(int $vendorOrderId): int
    {
        return (int) \App\Models\RefundAllocation::query()
            ->where('vendor_order_id', $vendorOrderId)
            ->where('type', \App\Enums\RefundAllocationType::Shipping->value)
            ->whereHas('refund', fn ($query) => $query->whereIn('status', [
                RefundStatus::Pending->value,
                RefundStatus::Processing->value,
                RefundStatus::PartiallyProcessed->value,
                RefundStatus::Succeeded->value,
            ]))
            ->sum('amount');
    }

    public function successfulVendorRefundAmount(VendorOrder $vendorOrder): int
    {
        $allocations = $vendorOrder->relationLoaded('refundAllocations')
            ? $vendorOrder->refundAllocations
            : $vendorOrder->refundAllocations()->with('refund')->get();

        return (int) $allocations
            ->filter(fn ($allocation): bool => $allocation->refund?->economicsRecognized())
            ->sum(fn ($allocation) => $allocation->seller_amount ?? $allocation->amount);
    }
}
