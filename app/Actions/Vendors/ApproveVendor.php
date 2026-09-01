<?php

namespace App\Actions\Vendors;

use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Validation\ValidationException;

class ApproveVendor
{
    public function handle(Vendor $vendor, User $admin): Vendor
    {
        if ($vendor->status !== VendorStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only pending vendor applications can be approved.',
            ]);
        }

        $vendor->forceFill([
            'status' => VendorStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $admin->id,
            'rejection_reason' => null,
            'rejected_at' => null,
            'rejected_by' => null,
        ])->save();

        return $vendor;
    }
}
