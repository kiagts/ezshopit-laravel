<?php

namespace App\Actions\Refunds;

use App\Actions\Commissions\ApplyRefundCommissionAdjustments;
use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarkRefundSucceeded
{
    public function __construct(
        private readonly RecalculatePaymentRefundStatus $recalculatePaymentRefundStatus,
        private readonly ApplyRefundCommissionAdjustments $applyRefundCommissionAdjustments,
    ) {
    }

    public function handle(Refund $refund, ?string $providerReference = null): Refund
    {
        $refund = DB::transaction(function () use ($refund, $providerReference): Refund {
            $refund = Refund::query()->lockForUpdate()->findOrFail($refund->id);
            $payment = Payment::query()->lockForUpdate()->findOrFail($refund->payment_id);

            if ($refund->status === RefundStatus::Succeeded) {
                return $refund->refresh();
            }

            if (in_array($refund->status, [RefundStatus::Failed, RefundStatus::Cancelled], true)) {
                return $refund->refresh();
            }

            $refund->forceFill([
                'status' => RefundStatus::Succeeded,
                'provider_reference' => $providerReference ?: $refund->provider_reference,
                'processed_at' => $refund->processed_at ?: now(),
                'failed_at' => null,
                'failure_code' => null,
                'failure_message' => null,
            ])->save();

            $this->recalculatePaymentRefundStatus->handle($payment);

            return $refund->refresh();
        }, 3);

        try {
            $this->applyRefundCommissionAdjustments->handle($refund);
        } catch (\Throwable $exception) {
            Log::error('Commission refund adjustment failed after refund success.', [
                'refund_id' => $refund->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        return $refund;
    }
}
