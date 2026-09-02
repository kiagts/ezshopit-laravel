<?php

namespace App\Actions\VendorPayouts;

use App\Enums\VendorPayoutStatus;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayout;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkVendorPayoutPaid
{
    public function __construct(private readonly PostVendorPayoutLedgerDebit $postDebit) {}

    public function handle(VendorPayout $payout, User $admin, ?string $externalReference = null, ?string $internalNotes = null): VendorPayout
    {
        return DB::transaction(function () use ($payout, $admin, $externalReference, $internalNotes): VendorPayout {
            Vendor::query()->whereKey($payout->vendor_id)->lockForUpdate()->firstOrFail();
            $payout = VendorPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if ($payout->status === VendorPayoutStatus::Paid) {
                $this->postDebit->handle($payout);

                return $payout;
            }

            if ($payout->status !== VendorPayoutStatus::Processing) {
                throw ValidationException::withMessages(['payout' => 'Only processing payouts can be marked paid.']);
            }

            $paidAt = now();

            $payout->forceFill([
                'status' => VendorPayoutStatus::Paid,
                'paid_at' => $paidAt,
                'paid_by' => $admin->id,
                'external_reference' => $externalReference,
                'internal_notes' => $internalNotes,
            ]);

            $this->postDebit->handle($payout);
            $payout->save();

            return $payout;
        });
    }
}
