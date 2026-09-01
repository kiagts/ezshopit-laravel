<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\AvailablePaymentMethods;
use App\Services\Payments\PaymentProviderManager;
use Illuminate\Validation\ValidationException;

class CreatePaymentForOrder
{
    public function __construct(
        private readonly AvailablePaymentMethods $availablePaymentMethods,
        private readonly PaymentProviderManager $paymentProviderManager,
        private readonly CreatePaymentAttempt $createPaymentAttempt,
    ) {
    }

    public function handle(Order $order, string $methodCode): Payment
    {
        $option = $this->availablePaymentMethods->find($methodCode);

        if (! $option) {
            throw ValidationException::withMessages([
                'payment_method' => 'Choose an available payment method.',
            ]);
        }

        $payment = $order->payment()->create([
            'method' => $option->method,
            'provider' => $this->paymentProviderManager->resolve($option->provider)->code(),
            'status' => PaymentStatus::Pending,
            'currency' => $order->currency,
            'amount' => $order->grand_total,
            'provider_reference' => null,
            'expires_at' => $option->method === PaymentMethod::Card
                ? now()->addMinutes((int) config('payments.online_payment_expiry_minutes', 30))
                : null,
        ]);

        $this->createPaymentAttempt->handle($payment);

        return $payment;
    }
}
