<?php

namespace App\Services\Payments;

use App\Models\Payment;

class CashOnDeliveryPaymentProvider implements PaymentProvider
{
    public function code(): string
    {
        return 'cash_on_delivery';
    }

    public function initialize(Payment $payment): PaymentInitializationResult
    {
        return PaymentInitializationResult::pending('Pay when your order is delivered.');
    }
}
