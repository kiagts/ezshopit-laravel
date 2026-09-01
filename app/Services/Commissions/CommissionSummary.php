<?php

namespace App\Services\Commissions;

class CommissionSummary
{
    public function __construct(
        public readonly int $originalSellerValue,
        public readonly int $successfulVendorRefunds,
        public readonly int $netSellerValue,
        public readonly int $originalCommissionableAmount,
        public readonly int $netCommissionableAmount,
        public readonly int $originalCommissionAmount,
        public readonly int $commissionAdjustmentAmount,
        public readonly int $netCommissionAmount,
        public readonly int $netAfterCommission,
    ) {
    }
}
