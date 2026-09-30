<?php

namespace App\Actions\VendorLedger;

use App\Enums\VendorLedgerEntryType;
use App\Models\VendorLedgerEntry;
use App\Models\VendorOrder;
use App\Models\VendorOrderCommission;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PostVendorPromotionDiscountDebit
{
    public function handle(VendorOrderCommission $commission): ?VendorLedgerEntry
    {
        return DB::transaction(function () use ($commission): ?VendorLedgerEntry {
            $commission = VendorOrderCommission::query()
                ->with('vendorOrder.order')
                ->lockForUpdate()
                ->findOrFail($commission->id);

            $vendorOrder = $commission->vendorOrder;
            $amount = (int) $vendorOrder->vendor_discount_total;

            if ($amount <= 0) {
                return null;
            }

            $sourceKey = 'vendor-order:'.$vendorOrder->id.':promotion';
            $existing = VendorLedgerEntry::query()->where('source_key', $sourceKey)->first();

            if ($existing) {
                return null;
            }

            return VendorLedgerEntry::query()->forceCreate([
                'vendor_id' => $vendorOrder->vendor_id,
                'source_key' => $sourceKey,
                'type' => VendorLedgerEntryType::Promotion,
                'amount' => -1 * $amount,
                'currency' => $commission->currency ?: $vendorOrder->order->currency,
                'available_at' => $this->availableAt($commission),
                'vendor_order_id' => $vendorOrder->id,
                'order_id' => $vendorOrder->order_id,
                'reference_type' => VendorOrder::class,
                'reference_id' => $vendorOrder->id,
                'description' => "Vendor-funded promotion discount for {$vendorOrder->vendor_order_number}",
                'metadata' => [
                    'vendor_discount_total' => $amount,
                    'order_number' => $vendorOrder->order->order_number,
                ],
            ]);
        }, 3);
    }

    private function availableAt(VendorOrderCommission $commission): Carbon
    {
        $days = max(0, (int) config('marketplace.vendor_earnings_hold_days', 7));
        $base = $commission->vendorOrder->delivered_at ?? $commission->recognized_at ?? now();

        return $base->copy()->addDays($days);
    }
}
