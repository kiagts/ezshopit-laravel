<?php

namespace App\Actions\Refunds;

use App\Actions\Commissions\ApplyRefundCommissionAdjustments;
use App\Actions\VendorLedger\PostVendorRefundDebits;
use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Notifications\MarketplaceNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarkRefundSucceeded
{
    public function __construct(
        private readonly RecalculatePaymentRefundStatus $recalculatePaymentRefundStatus,
        private readonly ApplyRefundCommissionAdjustments $applyRefundCommissionAdjustments,
        private readonly PostVendorRefundDebits $postVendorRefundDebits,
        private readonly MarketplaceNotifier $notifier,
    ) {
    }

    public function handle(Refund $refund, ?string $providerReference = null): Refund
    {
        if ($refund->idempotency_key && $refund->fundingAllocations()->where('status','!=','completed')->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['refund'=>'Funding destinations have not completed. External success requires a verified provider adapter.']);
        }
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
            $this->postVendorRefundDebits->handle($refund);
        } catch (\Throwable $exception) {
            Log::error('Vendor ledger refund debit posting failed after refund success.', [
                'refund_id' => $refund->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        try {
            $this->applyRefundCommissionAdjustments->handle($refund);
        } catch (\Throwable $exception) {
            Log::error('Commission refund adjustment failed after refund success.', [
                'refund_id' => $refund->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        if ($refund->status === RefundStatus::Succeeded) {
            $this->notifier->refundChanged($refund, RefundStatus::Succeeded);
        }

        return $refund;
    }
}
