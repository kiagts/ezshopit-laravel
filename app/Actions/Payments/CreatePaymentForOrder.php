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
        $remaining = max(0, $order->grand_total - $order->wallet_applied_total - $order->gift_card_applied_total);
        if (($order->wallet_applied_total > 0 || $order->gift_card_applied_total > 0) && $remaining === 0) {
            $payment = $order->payment()->create([
                'method' => PaymentMethod::Wallet, 'provider' => 'wallet',
                'status' => PaymentStatus::Paid, 'currency' => $order->currency,
                'amount' => 0, 'paid_at' => now(),
            ]);
            $attempt = $this->createPaymentAttempt->handle($payment, \App\Enums\PaymentAttemptStatus::Succeeded,
                ['wallet_applied_total' => $order->wallet_applied_total, 'gift_card_applied_total' => $order->gift_card_applied_total, 'funding' => 'internal_stored_value']);
            $attempt->update(['completed_at' => now()]);
            $order->update(['payment_status' => PaymentStatus::Paid]);
            return $payment;
        }
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
            'amount' => $remaining,
            'provider_reference' => null,
            'expires_at' => $option->method === PaymentMethod::Card
                ? now()->addMinutes((int) config('payments.online_payment_expiry_minutes', 30))
                : null,
        ]);

        $this->createPaymentAttempt->handle($payment);

        return $payment;
    }
}
