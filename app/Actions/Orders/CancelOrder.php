<?php

namespace App\Actions\Orders;

use App\Actions\Orders\Fulfillment\ReleaseInventoryReservationsForVendorOrder;
use App\Actions\Payments\UpdatePaymentStatus;
use App\Enums\OrderCancellationSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Enums\VendorOrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\VendorOrder;
use App\Services\Notifications\MarketplaceNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelOrder
{
    public function __construct(
        private readonly CanCancelOrder $canCancelOrder,
        private readonly ReleaseInventoryReservationsForVendorOrder $releaseReservations,
        private readonly UpdatePaymentStatus $updatePaymentStatus,
        private readonly MarketplaceNotifier $notifier,
    ) {
    }

    public function handle(Order $order, OrderCancellationSource $source, ?string $reason = null): Order
    {
        $order = DB::transaction(function () use ($order, $source, $reason): Order {
            $lockedOrder = Order::query()
                ->with('vendorOrders')
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status === OrderStatus::Cancelled) {
                return $lockedOrder->load(['payment', 'vendorOrders.items.reservation']);
            }

            $payment = Payment::query()
                ->where('order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->first();

            $lockedOrder->setRelation('payment', $payment);
            $eligibility = $this->canCancelOrder->check($lockedOrder, $source);

            if (! $eligibility->allowed) {
                throw ValidationException::withMessages([
                    'order' => $eligibility->message ?? 'This order cannot be cancelled.',
                ]);
            }

            foreach ($lockedOrder->vendorOrders()->orderBy('id')->get() as $vendorOrder) {
                if ($vendorOrder->status !== VendorOrderStatus::Cancelled) {
                    $this->releaseReservations->handle($vendorOrder);
                    $this->cancelVendorOrder($vendorOrder, $source, $reason);
                }
            }

            $this->cancelPayment($payment);
            if ($lockedOrder->wallet_applied_total > 0) {
                app(\App\Services\WalletService::class)->reverseCancelledOrder($lockedOrder);
            }

            $lockedOrder->forceFill([
                'status' => OrderStatus::Cancelled,
                'payment_status' => PaymentStatus::Cancelled,
                'cancelled_at' => $lockedOrder->cancelled_at ?? now(),
                'completed_at' => null,
                'cancellation_source' => $source,
                'cancellation_reason' => $this->reason($source, $reason),
            ])->save();

            return $lockedOrder->refresh()->load(['payment', 'vendorOrders.items.reservation']);
        }, 3);

        $this->notifier->orderCancelled($order);

        return $order;
    }

    private function cancelVendorOrder(VendorOrder $vendorOrder, OrderCancellationSource $source, ?string $reason): void
    {
        $vendorOrder->forceFill([
            'status' => VendorOrderStatus::Cancelled,
            'cancelled_at' => $vendorOrder->cancelled_at ?? now(),
            'cancelled_by' => null,
            'cancellation_reason' => $this->vendorReason($source, $reason),
        ])->save();
    }

    private function cancelPayment(?Payment $payment): void
    {
        if (! $payment) {
            throw ValidationException::withMessages(['payment' => 'Payment record is missing.']);
        }

        $payment->attempts()
            ->whereIn('status', [PaymentAttemptStatus::Pending, PaymentAttemptStatus::Processing])
            ->update([
                'status' => PaymentAttemptStatus::Cancelled,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

        $this->updatePaymentStatus->handle($payment, PaymentStatus::Cancelled);
    }

    private function reason(OrderCancellationSource $source, ?string $reason): string
    {
        if ($source === OrderCancellationSource::Customer && filled($reason)) {
            return str($reason)->limit(500)->toString();
        }

        return match ($source) {
            OrderCancellationSource::Customer => 'Customer cancelled unpaid online order.',
            OrderCancellationSource::PaymentExpired => 'Online payment was not completed within the allowed payment window.',
            OrderCancellationSource::Admin => 'Order cancelled by an administrator.',
            OrderCancellationSource::System => 'Order cancelled by the system.',
        };
    }

    private function vendorReason(OrderCancellationSource $source, ?string $reason): string
    {
        return match ($source) {
            OrderCancellationSource::PaymentExpired => 'Customer payment was not completed.',
            OrderCancellationSource::Customer => 'Order cancelled before fulfillment.',
            OrderCancellationSource::Admin => 'Order cancelled by marketplace administration.',
            OrderCancellationSource::System => 'Order cancelled before fulfillment.',
        };
    }
}
