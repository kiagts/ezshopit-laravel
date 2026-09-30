<?php

namespace App\Actions\VendorLedger;

use App\Enums\RefundAllocationType;
use App\Enums\RefundStatus;
use App\Enums\VendorLedgerEntryType;
use App\Models\RefundAllocation;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use Illuminate\Support\Facades\DB;

class PostVendorRefundDebit
{
    public function handle(RefundAllocation $allocation): ?VendorLedgerEntry
    {
        return DB::transaction(function () use ($allocation): ?VendorLedgerEntry {
            $allocation = RefundAllocation::query()
                ->with(['refund.payment', 'vendorOrder', 'orderItem.vendorOrder'])
                ->lockForUpdate()
                ->findOrFail($allocation->id);

            if (! $allocation->refund?->economicsRecognized() || ! $allocation->vendor_order_id || $allocation->amount <= 0) {
                return null;
            }

            if (! in_array($allocation->type, [RefundAllocationType::Item, RefundAllocationType::Shipping], true)) {
                return null;
            }

            $vendorOrder = $allocation->vendorOrder ?: $allocation->orderItem?->vendorOrder;

            if (! $vendorOrder || (int) $vendorOrder->id !== (int) $allocation->vendor_order_id) {
                return null;
            }

            $vendorOrder = \App\Models\VendorOrder::whereKey($vendorOrder->id)->lockForUpdate()->firstOrFail();

            Vendor::query()->whereKey($vendorOrder->vendor_id)->lockForUpdate()->firstOrFail();

            $sourceKey = 'refund-allocation:'.$allocation->id.':refund';
            $existing = VendorLedgerEntry::query()->where('source_key', $sourceKey)->first();

            if ($existing) {
                return $existing;
            }

            return VendorLedgerEntry::query()->forceCreate([
                'vendor_id' => $vendorOrder->vendor_id,
                'source_key' => $sourceKey,
                'type' => VendorLedgerEntryType::Refund,
                'amount' => -1 * (int) ($allocation->seller_amount ?? $allocation->amount),
                'currency' => $allocation->refund->currency,
                'available_at' => $allocation->refund->processed_at ?? now(),
                'vendor_order_id' => $vendorOrder->id,
                'order_id' => $vendorOrder->order_id,
                'order_item_id' => $allocation->order_item_id,
                'refund_id' => $allocation->refund_id,
                'refund_allocation_id' => $allocation->id,
                'reference_type' => $allocation::class,
                'reference_id' => $allocation->id,
                'description' => $allocation->type === RefundAllocationType::Shipping
                    ? "Shipping refund for {$vendorOrder->vendor_order_number}"
                    : "Item refund for {$vendorOrder->vendor_order_number}",
                'metadata' => [
                    'allocation_type' => $allocation->type->value,
                    'refund_number' => $allocation->refund->refund_number,
                    'commission_id_at_refund' => $vendorOrder->commission()->lockForUpdate()->value('id'),
                ],
            ]);
        }, 3);
    }
}
