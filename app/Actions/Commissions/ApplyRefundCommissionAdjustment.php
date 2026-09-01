<?php

namespace App\Actions\Commissions;

use App\Enums\CommissionAdjustmentType;
use App\Enums\RefundAllocationType;
use App\Enums\RefundStatus;
use App\Models\RefundAllocation;
use App\Models\VendorOrderCommission;
use App\Services\Commissions\CommissionCalculator;
use Illuminate\Support\Facades\DB;

class ApplyRefundCommissionAdjustment
{
    public function __construct(private readonly CommissionCalculator $calculator)
    {
    }

    public function handle(RefundAllocation $allocation): void
    {
        DB::transaction(function () use ($allocation): void {
            $allocation = RefundAllocation::query()
                ->with('refund')
                ->lockForUpdate()
                ->findOrFail($allocation->id);

            if ($allocation->refund?->status !== RefundStatus::Succeeded || ! $allocation->vendor_order_id) {
                return;
            }

            $commission = VendorOrderCommission::query()
                ->where('vendor_order_id', $allocation->vendor_order_id)
                ->with('adjustments')
                ->lockForUpdate()
                ->first();

            if (! $commission || $commission->adjustments()->where('refund_allocation_id', $allocation->id)->exists()) {
                return;
            }

            if ($allocation->refund->processed_at && $commission->recognized_at && $allocation->refund->processed_at->lessThanOrEqualTo($commission->recognized_at)) {
                return;
            }

            $commissionableDelta = $this->commissionableDelta($commission, $allocation);

            if ($commissionableDelta === 0) {
                return;
            }

            $commissionDelta = -1 * min(
                $this->calculator->calculate(abs($commissionableDelta), $commission->rate_basis_points),
                $this->calculator->netCommissionAmount($commission),
            );

            $commission->adjustments()->create([
                'refund_id' => $allocation->refund_id,
                'refund_allocation_id' => $allocation->id,
                'type' => CommissionAdjustmentType::Refund,
                'commissionable_amount_delta' => $commissionableDelta,
                'commission_amount_delta' => $commissionDelta,
                'reason' => 'Successful seller-attributed refund allocation.',
            ]);
        }, 3);
    }

    private function commissionableDelta(VendorOrderCommission $commission, RefundAllocation $allocation): int
    {
        if ($allocation->type === RefundAllocationType::Item) {
            return -1 * $allocation->amount;
        }

        if ($allocation->type === RefundAllocationType::Shipping && $commission->include_shipping) {
            return -1 * $allocation->amount;
        }

        return 0;
    }
}
