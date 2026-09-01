<?php

namespace App\Actions\Payments;

use App\Enums\InventoryReservationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Payments\AvailablePaymentMethods;
use App\Services\Payments\FulfillmentEligibility;
use App\Services\Payments\StripePaymentProvider;

class CanRetryCardPayment
{
    public function __construct(
        private readonly AvailablePaymentMethods $availablePaymentMethods,
    ) {
    }

    public function check(Order $order): FulfillmentEligibility
    {
        $order->loadMissing('payment', 'vendorOrders.items.reservation');
        $payment = $order->payment;

        if (! $payment) {
            return new FulfillmentEligibility(false, 'Payment has not been prepared for this order.');
        }

        if ($payment->method !== PaymentMethod::Card || $payment->provider !== StripePaymentProvider::CODE) {
            return new FulfillmentEligibility(false, 'This order is not a card payment.');
        }

        if (! in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Failed], true)) {
            return new FulfillmentEligibility(false, 'This payment cannot be retried right now.');
        }

        if (in_array($order->status, [OrderStatus::Completed, OrderStatus::Cancelled], true)) {
            return new FulfillmentEligibility(false, 'This order can no longer accept payment.');
        }

        if ($payment->expires_at && $payment->expires_at->isPast()) {
            return new FulfillmentEligibility(false, 'The payment window has expired for this order.');
        }

        $hasInactiveReservation = $order->vendorOrders
            ->flatMap(fn ($vendorOrder) => $vendorOrder->items)
            ->contains(fn ($item): bool => $item->reservation?->status !== InventoryReservationStatus::Active);

        if ($hasInactiveReservation) {
            return new FulfillmentEligibility(false, 'Reserved stock is no longer active for this order.');
        }

        if (! $this->availablePaymentMethods->find(PaymentMethod::Card->value)) {
            return new FulfillmentEligibility(false, 'Card payment is not available right now.');
        }

        return new FulfillmentEligibility(true);
    }
}
