<?php

namespace App\Actions\Refunds;

use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Notifications\MarketplaceNotifier;
use Illuminate\Support\Facades\DB;

class MarkRefundFailed
{
    public function __construct(
        private readonly RecalculatePaymentRefundStatus $recalculatePaymentRefundStatus,
        private readonly MarketplaceNotifier $notifier,
    ) {}

    public function handle(Refund $refund, ?string $code = null, ?string $message = null): Refund
    {
        if ($refund->economics_posted_at) return $refund->fresh();
        $refund = DB::transaction(function () use ($refund, $code, $message): Refund {
            $refund = Refund::query()->lockForUpdate()->findOrFail($refund->id);
            if ($refund->economics_posted_at) return $refund;
            $payment = Payment::query()->lockForUpdate()->findOrFail($refund->payment_id);

            if ($refund->status === RefundStatus::Succeeded || $refund->status === RefundStatus::Failed) {
                return $refund->refresh();
            }

            $refund->forceFill([
                'status' => RefundStatus::Failed,
                'failed_at' => now(),
                'failure_code' => $code,
                'failure_message' => $message ? str($message)->limit(500)->toString() : null,
            ])->save();

            $this->recalculatePaymentRefundStatus->handle($payment);

            return $refund->refresh();
        }, 3);

        if ($refund->status === RefundStatus::Failed) {
            $this->notifier->refundChanged($refund, RefundStatus::Failed);
        }

        return $refund;
    }
}
