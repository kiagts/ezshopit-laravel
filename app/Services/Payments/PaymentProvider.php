<?php

namespace App\Services\Payments;

use App\Models\Payment;

interface PaymentProvider
{
    public function code(): string;

    public function initialize(Payment $payment): PaymentInitializationResult;
}
