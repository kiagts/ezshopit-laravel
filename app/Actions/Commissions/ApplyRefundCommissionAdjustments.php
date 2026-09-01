<?php

namespace App\Actions\Commissions;

use App\Models\Refund;

class ApplyRefundCommissionAdjustments
{
    public function __construct(private readonly ApplyRefundCommissionAdjustment $applyRefundCommissionAdjustment)
    {
    }

    public function handle(Refund $refund): void
    {
        $refund->loadMissing('allocations');

        foreach ($refund->allocations as $allocation) {
            $this->applyRefundCommissionAdjustment->handle($allocation);
        }
    }
}
