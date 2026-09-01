<?php

namespace App\Services\Payments;

use App\Enums\RefundStatus;

class PaymentRefundResult
{
    public function __construct(
        public readonly RefundStatus $status,
        public readonly ?string $providerReference = null,
        public readonly ?string $failureCode = null,
        public readonly ?string $failureMessage = null,
    ) {
    }

    public static function succeeded(string $providerReference): self
    {
        return new self(RefundStatus::Succeeded, $providerReference);
    }

    public static function processing(?string $providerReference = null): self
    {
        return new self(RefundStatus::Processing, $providerReference);
    }

    public static function failed(?string $code, ?string $message): self
    {
        return new self(RefundStatus::Failed, null, $code, $message);
    }
}
