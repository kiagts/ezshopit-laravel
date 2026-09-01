<?php

namespace App\Actions\Vendors;

use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Validation\ValidationException;

class RejectVendor
{
    public function handle(Vendor $vendor, User $admin, string $reason): Vendor
    {
        if ($vendor->status !== VendorStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only pending vendor applications can be rejected.',
            ]);
        }

        $vendor->forceFill([
            'status' => VendorStatus::Rejected,
            'rejection_reason' => $reason,
            'rejected_at' => now(),
            'rejected_by' => $admin->id,
            'approved_at' => null,
            'approved_by' => null,
        ])->save();

        return $vendor;
    }
}
