<?php

namespace App\Actions\Commissions;

use App\Enums\CommissionAdjustmentType;
use App\Enums\RefundAllocationType;
use App\Enums\RefundStatus;
use App\Actions\VendorLedger\PostVendorCommissionRefundCredit;
use App\Models\CommissionAdjustment;
use App\Models\RefundAllocation;
use App\Models\VendorOrderCommission;
use App\Services\Commissions\CommissionCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApplyRefundCommissionAdjustment
{
    public function __construct(
        private readonly CommissionCalculator $calculator,
        private readonly PostVendorCommissionRefundCredit $postVendorCommissionRefundCredit,
    )
    {
    }

    public function handle(RefundAllocation $allocation): ?CommissionAdjustment
    {
        $adjustment = DB::transaction(function () use ($allocation): ?CommissionAdjustment {
            $allocation = RefundAllocation::query()
                ->with('refund')
                ->lockForUpdate()
                ->findOrFail($allocation->id);

            if ($allocation->refund?->status !== RefundStatus::Succeeded || ! $allocation->vendor_order_id) {
                return null;
            }

            $commission = VendorOrderCommission::query()
                ->where('vendor_order_id', $allocation->vendor_order_id)
                ->with('adjustments')
                ->lockForUpdate()
                ->first();

            if (! $commission || $commission->adjustments()->where('refund_allocation_id', $allocation->id)->exists()) {
                return $allocation->commissionAdjustment()->first();
            }

            if ($allocation->refund->processed_at && $commission->recognized_at && $allocation->refund->processed_at->lessThanOrEqualTo($commission->recognized_at)) {
                return null;
            }

            $commissionableDelta = $this->commissionableDelta($commission, $allocation);

            if ($commissionableDelta === 0) {
                return null;
            }

            $commissionDelta = -1 * min(
                $this->calculator->calculate(abs($commissionableDelta), $commission->rate_basis_points),
                $this->calculator->netCommissionAmount($commission),
            );

            return $commission->adjustments()->create([
                'refund_id' => $allocation->refund_id,
                'refund_allocation_id' => $allocation->id,
                'type' => CommissionAdjustmentType::Refund,
                'commissionable_amount_delta' => $commissionableDelta,
                'commission_amount_delta' => $commissionDelta,
                'reason' => 'Successful seller-attributed refund allocation.',
            ]);
        }, 3);

        if ($adjustment) {
            try {
                $this->postVendorCommissionRefundCredit->handle($adjustment);
            } catch (\Throwable $exception) {
                Log::error('Vendor ledger commission refund credit posting failed after commission adjustment.', [
                    'commission_adjustment_id' => $adjustment->id,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $adjustment;
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
