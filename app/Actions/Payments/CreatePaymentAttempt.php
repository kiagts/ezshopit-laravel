<?php

namespace App\Actions\Payments;

use App\Enums\PaymentAttemptStatus;
use App\Models\Payment;
use App\Models\PaymentAttempt;

class CreatePaymentAttempt
{
    public function handle(Payment $payment, PaymentAttemptStatus $status = PaymentAttemptStatus::Pending, ?array $metadata = null): PaymentAttempt
    {
        $next = ((int) $payment->attempts()->lockForUpdate()->max('attempt_number')) + 1;

        return $payment->attempts()->create([
            'attempt_number' => $next,
            'status' => $status,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'provider_reference' => $payment->provider_reference,
            'metadata' => $metadata,
            'started_at' => now(),
            'completed_at' => null,
        ]);
    }
}
