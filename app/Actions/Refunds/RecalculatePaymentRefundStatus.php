<?php

namespace App\Actions\Refunds;

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Payment;

class RecalculatePaymentRefundStatus
{
    public function handle(Payment $payment): Payment
    {
        $refunded = $payment->amount > 0
            ? app(\App\Services\Refunds\RefundFinancials::class)->summaryForPayment($payment->fresh())->refundedAmount
            : (int) $payment->refunds()->where('status', RefundStatus::Succeeded->value)->sum('amount');

        $status = match (true) {
            $refunded <= 0 => PaymentStatus::Paid,
            $refunded >= ($payment->amount ?: $payment->order->grand_total) => PaymentStatus::Refunded,
            default => PaymentStatus::PartiallyRefunded,
        };

        $updates = ['status' => $status];

        if ($status === PaymentStatus::Refunded && $payment->requires_review_at) {
            $updates['review_resolved_at'] = now();
        }

        $payment->forceFill($updates)->save();
        $payment->order()->update(['payment_status' => $status]);

        return $payment->refresh();
    }
}
