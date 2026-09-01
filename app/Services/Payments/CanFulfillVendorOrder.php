<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\VendorOrder;

class CanFulfillVendorOrder
{
    public function check(VendorOrder $vendorOrder): FulfillmentEligibility
    {
        $vendorOrder->loadMissing('order.payment');
        $payment = $vendorOrder->order->payment;

        if (! $payment) {
            return new FulfillmentEligibility(false, 'Waiting for payment record.');
        }

        if ($payment->method === PaymentMethod::CashOnDelivery) {
            return new FulfillmentEligibility(true);
        }

        if ($payment->method === PaymentMethod::Card && $payment->status === PaymentStatus::Paid) {
            return new FulfillmentEligibility(true);
        }

        if ($payment->method === PaymentMethod::Card) {
            return new FulfillmentEligibility(false, 'Waiting for customer payment.');
        }

        return new FulfillmentEligibility(false, 'Payment method is not fulfillable yet.');
    }
}
