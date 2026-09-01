<?php

namespace App\Actions\Refunds;

use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;

class MarkRefundFailed
{
    public function __construct(private readonly RecalculatePaymentRefundStatus $recalculatePaymentRefundStatus)
    {
    }

    public function handle(Refund $refund, ?string $code = null, ?string $message = null): Refund
    {
        return DB::transaction(function () use ($refund, $code, $message): Refund {
            $refund = Refund::query()->lockForUpdate()->findOrFail($refund->id);
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
    }
}
