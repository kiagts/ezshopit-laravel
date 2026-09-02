<?php

namespace App\Actions\VendorPayouts;

use App\Enums\VendorPayoutStatus;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayout;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RejectVendorPayout
{
    public function handle(VendorPayout $payout, User $admin, string $reason): VendorPayout
    {
        return DB::transaction(function () use ($payout, $admin, $reason): VendorPayout {
            Vendor::query()->whereKey($payout->vendor_id)->lockForUpdate()->firstOrFail();
            $payout = VendorPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if ($payout->status !== VendorPayoutStatus::Pending) {
                throw ValidationException::withMessages(['payout' => 'Only pending payouts can be rejected.']);
            }

            $payout->forceFill([
                'status' => VendorPayoutStatus::Rejected,
                'rejection_reason' => $reason,
                'rejected_at' => now(),
                'rejected_by' => $admin->id,
            ])->save();

            return $payout;
        });
    }
}
