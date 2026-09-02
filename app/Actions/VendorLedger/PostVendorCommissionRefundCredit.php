<?php

namespace App\Actions\VendorLedger;

use App\Enums\CommissionAdjustmentType;
use App\Enums\VendorLedgerEntryType;
use App\Models\CommissionAdjustment;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use Illuminate\Support\Facades\DB;

class PostVendorCommissionRefundCredit
{
    public function handle(CommissionAdjustment $adjustment): ?VendorLedgerEntry
    {
        return DB::transaction(function () use ($adjustment): ?VendorLedgerEntry {
            $adjustment = CommissionAdjustment::query()
                ->with(['commission.vendorOrder', 'refund'])
                ->lockForUpdate()
                ->findOrFail($adjustment->id);

            if ($adjustment->type !== CommissionAdjustmentType::Refund || $adjustment->commission_amount_delta >= 0) {
                return null;
            }

            $amount = abs((int) $adjustment->commission_amount_delta);

            if ($amount === 0) {
                return null;
            }

            $sourceKey = 'commission-adjustment:'.$adjustment->id.':commission-refund';
            $existing = VendorLedgerEntry::query()->where('source_key', $sourceKey)->first();

            if ($existing) {
                return $existing;
            }

            $commission = $adjustment->commission;
            $vendorOrder = $commission->vendorOrder;
            Vendor::query()->whereKey($vendorOrder->vendor_id)->lockForUpdate()->firstOrFail();

            return VendorLedgerEntry::query()->forceCreate([
                'vendor_id' => $vendorOrder->vendor_id,
                'source_key' => $sourceKey,
                'type' => VendorLedgerEntryType::CommissionRefund,
                'amount' => $amount,
                'currency' => $commission->currency,
                'available_at' => $adjustment->refund?->processed_at ?? now(),
                'vendor_order_id' => $vendorOrder->id,
                'order_id' => $vendorOrder->order_id,
                'refund_id' => $adjustment->refund_id,
                'refund_allocation_id' => $adjustment->refund_allocation_id,
                'vendor_order_commission_id' => $commission->id,
                'commission_adjustment_id' => $adjustment->id,
                'reference_type' => $adjustment::class,
                'reference_id' => $adjustment->id,
                'description' => "Commission returned after refund for {$vendorOrder->vendor_order_number}",
                'metadata' => [
                    'commission_amount_delta' => (int) $adjustment->commission_amount_delta,
                    'commissionable_amount_delta' => (int) $adjustment->commissionable_amount_delta,
                ],
            ]);
        }, 3);
    }
}
