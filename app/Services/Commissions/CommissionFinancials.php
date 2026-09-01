<?php

namespace App\Services\Commissions;

use App\Services\Refunds\RefundFinancials;
use App\Models\VendorOrder;
use App\Models\VendorOrderCommission;

class CommissionFinancials
{
    public function __construct(
        private readonly CommissionCalculator $calculator,
        private readonly RefundFinancials $refundFinancials,
    ) {
    }

    public function summaryFor(VendorOrderCommission $commission): CommissionSummary
    {
        $commission->loadMissing(['vendorOrder.refundAllocations.refund', 'adjustments']);
        $vendorOrder = $commission->vendorOrder;
        $successfulRefunds = $vendorOrder ? $this->refundFinancials->successfulVendorRefundAmount($vendorOrder) : 0;
        $netCommissionable = $this->calculator->netCommissionableAmount($commission);
        $netCommission = $this->calculator->netCommissionAmount($commission);

        return new CommissionSummary(
            originalSellerValue: $vendorOrder?->grand_total ?? 0,
            successfulVendorRefunds: $successfulRefunds,
            netSellerValue: max(0, ($vendorOrder?->grand_total ?? 0) - $successfulRefunds),
            originalCommissionableAmount: $commission->original_commissionable_amount,
            netCommissionableAmount: $netCommissionable,
            originalCommissionAmount: $commission->original_commission_amount,
            commissionAdjustmentAmount: (int) $commission->adjustments->sum('commission_amount_delta'),
            netCommissionAmount: $netCommission,
            netAfterCommission: max(0, (($vendorOrder?->grand_total ?? $netCommissionable) - $successfulRefunds) - $netCommission),
        );
    }
}
