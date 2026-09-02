<?php

namespace App\Services\VendorPayouts;

class VendorPayoutBalance
{
    public function __construct(
        public readonly string $currency,
        public readonly int $availableLedgerBalance,
        public readonly int $activePayoutReserved,
        public readonly int $requestableBalance,
        public readonly int $minimumPayout,
        public readonly int $paidOutTotal,
    ) {}
}
