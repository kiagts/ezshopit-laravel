<?php

namespace App\Actions\VendorLedger;

use App\Models\Refund;

class PostVendorRefundDebits
{
    public function __construct(private readonly PostVendorRefundDebit $postVendorRefundDebit)
    {
    }

    public function handle(Refund $refund): int
    {
        $created = 0;

        $refund->loadMissing('allocations');

        foreach ($refund->allocations as $allocation) {
            $before = $allocation->ledgerEntry()->exists();
            $entry = $this->postVendorRefundDebit->handle($allocation);

            if (! $before && $entry) {
                $created++;
            }
        }

        return $created;
    }
}
