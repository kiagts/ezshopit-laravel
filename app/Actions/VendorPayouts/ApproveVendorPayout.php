<?php

namespace App\Actions\VendorPayouts;

use App\Enums\VendorPayoutStatus;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayout;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveVendorPayout
{
    public function handle(VendorPayout $payout, User $admin): VendorPayout
    {
        return DB::transaction(function () use ($payout, $admin): VendorPayout {
            Vendor::query()->whereKey($payout->vendor_id)->lockForUpdate()->firstOrFail();
            $payout = VendorPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if ($payout->status !== VendorPayoutStatus::Pending) {
                throw ValidationException::withMessages(['payout' => 'Only pending payouts can be approved.']);
            }

            $payout->forceFill([
                'status' => VendorPayoutStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $admin->id,
            ])->save();

            return $payout;
        });
    }
}
