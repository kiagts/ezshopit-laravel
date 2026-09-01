<?php

namespace App\Services\Refunds;

class PaymentFinancialSummary
{
    public function __construct(
        public readonly int $originalAmount,
        public readonly int $refundedAmount,
        public readonly int $pendingRefundAmount,
    ) {
    }

    public function refundableAmount(): int
    {
        return max(0, $this->originalAmount - $this->refundedAmount - $this->pendingRefundAmount);
    }

    public function netPaidAmount(): int
    {
        return max(0, $this->originalAmount - $this->refundedAmount);
    }
}
