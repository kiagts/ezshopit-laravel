<?php

namespace App\Actions\Payments;

use App\Actions\Refunds\MarkRefundFailed;
use App\Actions\Refunds\MarkRefundSucceeded;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Models\Refund;
use App\Services\Payments\StripePaymentProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Event;

class HandleStripeWebhook
{
    public function __construct(
        private readonly UpdatePaymentStatus $updatePaymentStatus,
        private readonly MarkRefundSucceeded $markRefundSucceeded,
        private readonly MarkRefundFailed $markRefundFailed,
    ) {
    }

    public function handle(Event $event): void
    {
        if (str_starts_with($event->type, 'refund.')) {
            $this->handleRefundEvent($event);

            return;
        }

        if (! str_starts_with($event->type, 'payment_intent.')) {
            $this->recordNonPaymentEvent($event);

            return;
        }

        DB::transaction(function () use ($event): void {
            $webhookEvent = PaymentWebhookEvent::query()->firstOrCreate(
                ['provider' => StripePaymentProvider::CODE, 'provider_event_id' => $event->id],
                ['event_type' => $event->type],
            );

            if ($webhookEvent->processed_at) {
                return;
            }

            $intent = $event->data->object;
            $attempt = PaymentAttempt::query()
                ->where('provider_reference', $intent->id)
                ->lockForUpdate()
                ->first();

            if (! $attempt) {
                $webhookEvent->forceFill(['processed_at' => now()])->save();

                return;
            }

            $payment = Payment::query()->lockForUpdate()->find($attempt->payment_id);

            $webhookEvent->forceFill([
                'payment_id' => $payment?->id,
                'payment_attempt_id' => $attempt->id,
            ])->save();

            if (! $payment || $payment->provider !== StripePaymentProvider::CODE) {
                $webhookEvent->forceFill(['processed_at' => now()])->save();

                return;
            }

            if ($payment->status === PaymentStatus::Paid && $event->type !== 'payment_intent.succeeded') {
                $webhookEvent->forceFill(['processed_at' => now()])->save();

                return;
            }

            if (in_array($payment->status, [PaymentStatus::PartiallyRefunded, PaymentStatus::Refunded], true)) {
                $webhookEvent->forceFill(['processed_at' => now()])->save();

                return;
            }

            match ($event->type) {
                'payment_intent.succeeded' => $this->handleSucceeded($payment, $attempt, $intent),
                'payment_intent.processing' => $this->handleProcessing($payment, $attempt, $intent),
                'payment_intent.payment_failed' => $this->handleFailed($payment, $attempt, $intent),
                'payment_intent.canceled' => $this->handleCancelled($payment, $attempt, $intent),
                default => null,
            };

            $webhookEvent->forceFill(['processed_at' => now()])->save();
        }, 3);
    }

    private function recordNonPaymentEvent(Event $event): void
    {
        PaymentWebhookEvent::query()->firstOrCreate(
            ['provider' => StripePaymentProvider::CODE, 'provider_event_id' => $event->id],
            ['event_type' => $event->type, 'processed_at' => now()],
        );
    }

    private function handleRefundEvent(Event $event): void
    {
        DB::transaction(function () use ($event): void {
            $webhookEvent = PaymentWebhookEvent::query()->firstOrCreate(
                ['provider' => StripePaymentProvider::CODE, 'provider_event_id' => $event->id],
                ['event_type' => $event->type],
            );

            if ($webhookEvent->processed_at) {
                return;
            }

            $stripeRefund = $event->data->object;
            $metadata = $stripeRefund->metadata ?? null;
            $refundId = is_object($metadata) ? ($metadata->refund_id ?? null) : null;
            $refund = Refund::query()
                ->where('provider_reference', $stripeRefund->id)
                ->when($refundId, fn ($query) => $query->orWhere('id', $refundId))
                ->lockForUpdate()
                ->first();

            $webhookEvent->forceFill([
                'payment_id' => $refund?->payment_id,
            ])->save();

            if (! $refund || $refund->provider !== StripePaymentProvider::CODE) {
                $webhookEvent->forceFill(['processed_at' => now()])->save();

                return;
            }

            match ($stripeRefund->status) {
                'succeeded' => $this->markRefundSucceeded->handle($refund, $stripeRefund->id),
                'failed', 'canceled' => $this->markRefundFailed->handle(
                    $refund,
                    $stripeRefund->failure_reason ?: 'stripe_refund_failed',
                    'Stripe reported refund failure.'
                ),
                default => $this->markRefundProcessing($refund, $stripeRefund->id),
            };

            $webhookEvent->forceFill(['processed_at' => now()])->save();
        }, 3);
    }

    private function markRefundProcessing(Refund $refund, string $providerReference): void
    {
        if ($refund->status !== RefundStatus::Pending) {
            return;
        }

        $refund->forceFill([
            'status' => RefundStatus::Processing,
            'provider_reference' => $providerReference,
        ])->save();
    }

    private function handleSucceeded(Payment $payment, PaymentAttempt $attempt, object $intent): void
    {
        if (! $this->matchesPayment($payment, $intent)) {
            Log::warning('Stripe payment intent amount or currency mismatch.', [
                'payment_id' => $payment->id,
                'payment_intent' => $intent->id,
            ]);

            return;
        }

        if ($payment->order()->where('status', OrderStatus::Cancelled)->exists()) {
            $this->handleLateSucceededAfterCancellation($payment, $attempt, $intent);

            return;
        }

        $attempt->forceFill([
            'status' => PaymentAttemptStatus::Succeeded,
            'failure_code' => null,
            'failure_message' => null,
            'metadata' => array_merge($attempt->metadata ?? [], ['payment_intent_status' => $intent->status]),
            'completed_at' => now(),
        ])->save();

        $this->updatePaymentStatus->handle($payment, PaymentStatus::Paid);
    }

    private function handleLateSucceededAfterCancellation(Payment $payment, PaymentAttempt $attempt, object $intent): void
    {
        $attempt->forceFill([
            'status' => PaymentAttemptStatus::Succeeded,
            'failure_code' => null,
            'failure_message' => null,
            'metadata' => array_merge($attempt->metadata ?? [], [
                'payment_intent_status' => $intent->status,
                'late_success_after_cancellation' => true,
            ]),
            'completed_at' => now(),
        ])->save();

        $payment->forceFill([
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
            'failed_at' => null,
            'cancelled_at' => null,
            'requires_review_at' => now(),
            'review_reason' => 'Payment succeeded after order cancellation. Refund review is required.',
        ])->save();

        $payment->order()->update(['payment_status' => PaymentStatus::Paid]);
    }

    private function handleProcessing(Payment $payment, PaymentAttempt $attempt, object $intent): void
    {
        $attempt->forceFill([
            'status' => PaymentAttemptStatus::Processing,
            'metadata' => array_merge($attempt->metadata ?? [], ['payment_intent_status' => $intent->status]),
        ])->save();

        if ($payment->status === PaymentStatus::Pending) {
            $this->updatePaymentStatus->handle($payment, PaymentStatus::Processing);
        }
    }

    private function handleFailed(Payment $payment, PaymentAttempt $attempt, object $intent): void
    {
        $error = $intent->last_payment_error ?? null;

        $attempt->forceFill([
            'status' => PaymentAttemptStatus::Failed,
            'failure_code' => $error?->code,
            'failure_message' => $error?->message,
            'metadata' => array_merge($attempt->metadata ?? [], ['payment_intent_status' => $intent->status]),
            'completed_at' => now(),
        ])->save();

        if ($payment->status === PaymentStatus::Processing) {
            $payment->forceFill(['status' => PaymentStatus::Pending])->save();
            $payment->order()->update(['payment_status' => PaymentStatus::Pending]);
        }
    }

    private function handleCancelled(Payment $payment, PaymentAttempt $attempt, object $intent): void
    {
        $attempt->forceFill([
            'status' => PaymentAttemptStatus::Cancelled,
            'metadata' => array_merge($attempt->metadata ?? [], ['payment_intent_status' => $intent->status]),
            'completed_at' => now(),
        ])->save();

        if ($payment->status === PaymentStatus::Processing) {
            $payment->forceFill(['status' => PaymentStatus::Pending])->save();
            $payment->order()->update(['payment_status' => PaymentStatus::Pending]);
        }
    }

    private function matchesPayment(Payment $payment, object $intent): bool
    {
        return (int) $intent->amount === $payment->amount
            && strtolower((string) $intent->currency) === strtolower($payment->currency);
    }
}
