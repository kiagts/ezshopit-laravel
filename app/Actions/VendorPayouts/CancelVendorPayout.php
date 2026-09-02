<?php

namespace App\Actions\VendorPayouts;

use App\Enums\VendorPayoutStatus;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayout;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelVendorPayout
{
    public function handle(VendorPayout $payout, User $actor, string $reason = 'Cancelled by vendor.'): VendorPayout
    {
        return DB::transaction(function () use ($payout, $actor, $reason): VendorPayout {
            Vendor::query()->whereKey($payout->vendor_id)->lockForUpdate()->firstOrFail();
            $payout = VendorPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();

            $allowed = $actor->isAdmin()
                ? [VendorPayoutStatus::Pending, VendorPayoutStatus::Approved]
                : [VendorPayoutStatus::Pending];

            if (! in_array($payout->status, $allowed, true)) {
                throw ValidationException::withMessages(['payout' => 'This payout can no longer be cancelled.']);
            }

            $payout->forceFill([
                'status' => VendorPayoutStatus::Cancelled,
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
            ])->save();

            return $payout;
        });
    }
}
