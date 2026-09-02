<?php

namespace App\Actions\VendorPayouts;

use App\Enums\VendorLedgerEntryType;
use App\Enums\VendorPayoutStatus;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Models\VendorPayout;
use Illuminate\Support\Facades\DB;
use LogicException;

class PostVendorPayoutLedgerDebit
{
    public function handle(VendorPayout $payout): VendorLedgerEntry
    {
        return DB::transaction(function () use ($payout): VendorLedgerEntry {
            if ($payout->status !== VendorPayoutStatus::Paid) {
                throw new LogicException('Only paid payouts can create payout ledger debits.');
            }

            Vendor::query()->whereKey($payout->vendor_id)->lockForUpdate()->firstOrFail();

            $existing = VendorLedgerEntry::query()
                ->where('source_key', $payout->payoutLedgerSourceKey())
                ->first();

            if ($existing) {
                return $existing;
            }

            return VendorLedgerEntry::query()->forceCreate([
                'vendor_id' => $payout->vendor_id,
                'source_key' => $payout->payoutLedgerSourceKey(),
                'type' => VendorLedgerEntryType::Payout,
                'amount' => -abs($payout->amount),
                'currency' => $payout->currency,
                'available_at' => $payout->paid_at ?: now(),
                'vendor_payout_id' => $payout->id,
                'reference_type' => VendorPayout::class,
                'reference_id' => $payout->id,
                'description' => 'Vendor payout '.$payout->payout_number.' paid.',
                'metadata' => [
                    'payout_number' => $payout->payout_number,
                    'external_reference' => $payout->external_reference,
                ],
            ]);
        }, 3);
    }
}
