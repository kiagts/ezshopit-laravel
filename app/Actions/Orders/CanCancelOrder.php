<?php

namespace App\Actions\Orders;

use App\Enums\OrderCancellationSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\VendorOrderStatus;
use App\Models\Order;
use App\Services\Payments\FulfillmentEligibility;

class CanCancelOrder
{
    public function check(Order $order, OrderCancellationSource $source): FulfillmentEligibility
    {
        $order->loadMissing('payment', 'vendorOrders');
        $payment = $order->payment;

        if ($order->status === OrderStatus::Cancelled) {
            return new FulfillmentEligibility(false, 'This order is already cancelled.');
        }

        if ($order->status === OrderStatus::Completed) {
            return new FulfillmentEligibility(false, 'Completed orders cannot be cancelled here.');
        }

        if (! $payment) {
            return new FulfillmentEligibility(false, 'Payment record is missing.');
        }

        if ($payment->status === PaymentStatus::Paid) {
            return new FulfillmentEligibility(false, 'Paid orders cannot be cancelled through this workflow.');
        }

        if ($payment->status === PaymentStatus::Processing) {
            return new FulfillmentEligibility(false, 'Payment is still processing.');
        }

        if ($payment->method !== PaymentMethod::Card) {
            return new FulfillmentEligibility(false, 'Only unpaid online card orders can be cancelled here.');
        }

        if ($order->vendorOrders->contains(fn ($vendorOrder): bool => in_array($vendorOrder->status, [VendorOrderStatus::Shipped, VendorOrderStatus::Delivered], true))) {
            return new FulfillmentEligibility(false, 'This order has already entered shipment.');
        }

        if ($source === OrderCancellationSource::PaymentExpired && (! $payment->expires_at || $payment->expires_at->isFuture())) {
            return new FulfillmentEligibility(false, 'Payment has not expired yet.');
        }

        return new FulfillmentEligibility(true);
    }
}
