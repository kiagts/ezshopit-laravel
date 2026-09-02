<?php

namespace App\Actions\VendorLedger;

use App\Enums\VendorLedgerEntryType;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Models\VendorOrderCommission;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PostVendorOrderEarnings
{
    public function handle(VendorOrderCommission $commission): int
    {
        return DB::transaction(function () use ($commission): int {
            $commission = VendorOrderCommission::query()
                ->with('vendorOrder.order')
                ->lockForUpdate()
                ->findOrFail($commission->id);

            $vendorOrder = $commission->vendorOrder;
            Vendor::query()->whereKey($vendorOrder->vendor_id)->lockForUpdate()->firstOrFail();

            $created = 0;
            $availableAt = $this->availableAt($commission);
            $currency = $commission->currency ?: $vendorOrder->order->currency;
            $saleAmount = (int) $vendorOrder->items_subtotal + (int) $vendorOrder->shipping_total;

            if ($saleAmount > 0) {
                $created += $this->createOnce('vendor-order:'.$vendorOrder->id.':sale', [
                    'vendor_id' => $vendorOrder->vendor_id,
                    'type' => VendorLedgerEntryType::Sale,
                    'amount' => $saleAmount,
                    'currency' => $currency,
                    'available_at' => $availableAt,
                    'vendor_order_id' => $vendorOrder->id,
                    'order_id' => $vendorOrder->order_id,
                    'reference_type' => $vendorOrder::class,
                    'reference_id' => $vendorOrder->id,
                    'description' => "Sale earnings for {$vendorOrder->vendor_order_number}",
                    'metadata' => [
                        'items_subtotal' => (int) $vendorOrder->items_subtotal,
                        'shipping_total' => (int) $vendorOrder->shipping_total,
                    ],
                ]);
            }

            if ($commission->original_commission_amount > 0) {
                $created += $this->createOnce('vendor-order-commission:'.$commission->id.':commission', [
                    'vendor_id' => $vendorOrder->vendor_id,
                    'type' => VendorLedgerEntryType::Commission,
                    'amount' => -1 * (int) $commission->original_commission_amount,
                    'currency' => $currency,
                    'available_at' => $availableAt,
                    'vendor_order_id' => $vendorOrder->id,
                    'order_id' => $vendorOrder->order_id,
                    'vendor_order_commission_id' => $commission->id,
                    'reference_type' => $commission::class,
                    'reference_id' => $commission->id,
                    'description' => "Marketplace commission for {$vendorOrder->vendor_order_number}",
                    'metadata' => [
                        'rate_basis_points' => (int) $commission->rate_basis_points,
                        'commissionable_amount' => (int) $commission->original_commissionable_amount,
                    ],
                ]);
            }

            return $created;
        }, 3);
    }

    private function availableAt(VendorOrderCommission $commission): Carbon
    {
        $days = max(0, (int) config('marketplace.vendor_earnings_hold_days', 7));
        $base = $commission->vendorOrder->delivered_at ?? $commission->recognized_at ?? now();

        return $base->copy()->addDays($days);
    }

    private function createOnce(string $sourceKey, array $attributes): int
    {
        if (VendorLedgerEntry::query()->where('source_key', $sourceKey)->exists()) {
            return 0;
        }

        VendorLedgerEntry::query()->forceCreate(['source_key' => $sourceKey] + $attributes);

        return 1;
    }
}
