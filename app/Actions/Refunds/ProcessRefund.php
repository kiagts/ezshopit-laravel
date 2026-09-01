<?php

namespace App\Actions\Refunds;

use App\Enums\RefundStatus;
use App\Models\Refund;
use App\Services\Payments\PaymentProviderManager;
use App\Services\Payments\RefundablePaymentProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcessRefund
{
    public function __construct(
        private readonly PaymentProviderManager $paymentProviderManager,
        private readonly MarkRefundSucceeded $markRefundSucceeded,
        private readonly MarkRefundFailed $markRefundFailed,
    ) {
    }

    public function handle(Refund $refund): Refund
    {
        $refund->loadMissing('payment');

        if ($refund->status !== RefundStatus::Pending) {
            return $refund->refresh();
        }

        $provider = $this->paymentProviderManager->resolve($refund->provider);

        if (! $provider instanceof RefundablePaymentProvider) {
            throw ValidationException::withMessages(['refund' => 'This payment provider cannot process refunds automatically.']);
        }

        $result = $provider->refund($refund);

        return match ($result->status) {
            RefundStatus::Succeeded => $this->markRefundSucceeded->handle($refund, $result->providerReference),
            RefundStatus::Processing => $this->markProcessing($refund, $result->providerReference),
            RefundStatus::Failed => $this->markRefundFailed->handle($refund, $result->failureCode, $result->failureMessage),
            default => $refund->refresh(),
        };
    }

    private function markProcessing(Refund $refund, ?string $providerReference): Refund
    {
        return DB::transaction(function () use ($refund, $providerReference): Refund {
            $refund = Refund::query()->lockForUpdate()->findOrFail($refund->id);

            if ($refund->status !== RefundStatus::Pending) {
                return $refund->refresh();
            }

            $refund->forceFill([
                'status' => RefundStatus::Processing,
                'provider_reference' => $providerReference ?: $refund->provider_reference,
            ])->save();

            return $refund->refresh();
        }, 3);
    }
}
