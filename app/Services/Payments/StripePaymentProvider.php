<?php

namespace App\Services\Payments;

use App\Actions\Payments\CreatePaymentAttempt;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;

class StripePaymentProvider implements PaymentProvider, RefundablePaymentProvider
{
    public const CODE = 'stripe';

    public function __construct(
        private readonly CreatePaymentAttempt $createPaymentAttempt,
    ) {
    }

    public function code(): string
    {
        return self::CODE;
    }

    public function initialize(Payment $payment): PaymentInitializationResult
    {
        $this->ensureConfigured();
        $this->ensurePaymentCanInitialize($payment);

        $attempt = DB::transaction(function () use ($payment): PaymentAttempt {
            $payment = Payment::query()->with('order')->lockForUpdate()->findOrFail($payment->id);
            $attempt = $this->currentReusableAttempt($payment);

            if (! $attempt) {
                $attempt = $this->createPaymentAttempt->handle($payment);
            }

            return $attempt;
        });

        try {
            if ($attempt->provider_reference) {
                $intent = $this->client()->paymentIntents->retrieve($attempt->provider_reference);
            } else {
                $intent = $this->client()->paymentIntents->create([
                    'amount' => $payment->amount,
                    'currency' => strtolower($payment->currency),
                    'automatic_payment_methods' => ['enabled' => true],
                    'description' => 'Marketplace Order '.$payment->order->order_number,
                    'metadata' => [
                        'order_id' => (string) $payment->order_id,
                        'order_number' => $payment->order->order_number,
                        'payment_id' => (string) $payment->id,
                        'payment_attempt_id' => (string) $attempt->id,
                    ],
                ], [
                    'idempotency_key' => $this->idempotencyKey($payment, $attempt),
                ]);
            }
        } catch (ApiErrorException $exception) {
            $this->markAttemptFailed($attempt, $exception->getStripeCode() ?: 'stripe_api_error', $exception->getMessage());

            throw ValidationException::withMessages([
                'payment' => 'We could not prepare secure card payment. Please try again.',
            ]);
        }

        DB::transaction(function () use ($payment, $attempt, $intent): void {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $attempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            $payment->forceFill([
                'provider_reference' => $intent->id,
                'status' => PaymentStatus::Pending,
            ])->save();

            $payment->order()->update(['payment_status' => PaymentStatus::Pending]);

            $attempt->forceFill([
                'provider_reference' => $intent->id,
                'status' => PaymentAttemptStatus::Pending,
                'metadata' => ['payment_intent_status' => $intent->status],
            ])->save();
        });

        return PaymentInitializationResult::requiresClientConfirmation((string) $intent->client_secret, (string) $intent->id);
    }

    public function refund(Refund $refund): PaymentRefundResult
    {
        $refund->loadMissing('payment.order');
        $payment = $refund->payment;

        if (! $payment || $payment->provider !== $this->code() || blank($payment->provider_reference)) {
            throw ValidationException::withMessages(['refund' => 'This Stripe payment is missing a provider reference.']);
        }

        $this->ensureConfigured();

        try {
            $stripeRefund = $this->client()->refunds->create([
                'payment_intent' => $payment->provider_reference,
                'amount' => $refund->amount,
                'metadata' => [
                    'refund_id' => (string) $refund->id,
                    'refund_number' => $refund->refund_number,
                    'order_number' => $payment->order->order_number,
                ],
            ], [
                'idempotency_key' => $this->refundIdempotencyKey($refund),
            ]);
        } catch (InvalidRequestException $exception) {
            return PaymentRefundResult::failed($exception->getStripeCode() ?: 'stripe_refund_rejected', $exception->getMessage());
        } catch (ApiErrorException $exception) {
            throw ValidationException::withMessages([
                'refund' => 'Stripe refund status is uncertain. The local refund remains pending for review.',
            ]);
        }

        return match ($stripeRefund->status) {
            'succeeded' => PaymentRefundResult::succeeded($stripeRefund->id),
            'failed', 'canceled' => PaymentRefundResult::failed(
                $stripeRefund->failure_reason ?: 'stripe_refund_failed',
                'Stripe could not complete the refund.'
            ),
            default => PaymentRefundResult::processing($stripeRefund->id),
        };
    }

    private function ensureConfigured(): void
    {
        $currency = strtolower((string) config('marketplace.currency.code', 'USD'));

        if (! config('payments.stripe.enabled')
            || blank(config('payments.stripe.public_key'))
            || blank(config('payments.stripe.secret_key'))) {
            throw ValidationException::withMessages(['payment' => 'Card payment is not available right now.']);
        }

        if (! in_array($currency, config('payments.stripe.supported_currencies', []), true)) {
            throw ValidationException::withMessages(['payment' => 'Card payment is not available for this currency.']);
        }
    }

    private function ensurePaymentCanInitialize(Payment $payment): void
    {
        $payment->loadMissing('order');

        if ($payment->method !== PaymentMethod::Card || $payment->provider !== $this->code()) {
            throw ValidationException::withMessages(['payment' => 'This payment is not a Stripe card payment.']);
        }

        if ($payment->status === PaymentStatus::Paid) {
            throw ValidationException::withMessages(['payment' => 'This payment has already been completed.']);
        }

        if ($payment->expires_at && $payment->expires_at->isPast()) {
            throw ValidationException::withMessages(['payment' => 'The payment window has expired for this order.']);
        }

        if (! in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Failed], true)) {
            throw ValidationException::withMessages(['payment' => 'This payment cannot be retried right now.']);
        }
    }

    private function currentReusableAttempt(Payment $payment): ?PaymentAttempt
    {
        return $payment->attempts()
            ->whereIn('status', [PaymentAttemptStatus::Pending, PaymentAttemptStatus::Processing])
            ->latest('attempt_number')
            ->lockForUpdate()
            ->first();
    }

    private function markAttemptFailed(PaymentAttempt $attempt, string $code, string $message): void
    {
        DB::transaction(function () use ($attempt, $code, $message): void {
            $attempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $attempt->forceFill([
                'status' => PaymentAttemptStatus::Failed,
                'failure_code' => $code,
                'failure_message' => str($message)->limit(500)->toString(),
                'completed_at' => now(),
            ])->save();
        });
    }

    private function client(): StripeClient
    {
        return new StripeClient((string) config('payments.stripe.secret_key'));
    }

    private function idempotencyKey(Payment $payment, PaymentAttempt $attempt): string
    {
        return "payment-{$payment->id}-attempt-{$attempt->attempt_number}";
    }

    private function refundIdempotencyKey(Refund $refund): string
    {
        return "payment-{$refund->payment_id}-refund-{$refund->id}";
    }
}
