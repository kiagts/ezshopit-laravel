<?php

namespace App\Services\VendorLedger;

class VendorBalanceSummary
{
    public function __construct(
        public readonly string $currency,
        public readonly int $availableBalance,
        public readonly int $pendingBalance,
        public readonly int $netEarnings,
        public readonly int $grossSales,
        public readonly int $commissionCharged,
        public readonly int $refunds,
        public readonly int $commissionRefunds,
    ) {
    }
}
