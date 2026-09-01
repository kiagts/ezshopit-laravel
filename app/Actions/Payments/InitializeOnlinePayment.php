<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Services\Payments\PaymentInitializationResult;
use App\Services\Payments\PaymentProviderManager;

class InitializeOnlinePayment
{
    public function __construct(
        private readonly PaymentProviderManager $paymentProviderManager,
    ) {
    }

    public function handle(Payment $payment): PaymentInitializationResult
    {
        return $this->paymentProviderManager
            ->resolve($payment->provider)
            ->initialize($payment);
    }
}
