<?php

namespace App\Actions\Vendors;

use App\Enums\VendorStatus;
use App\Models\Vendor;
use Illuminate\Validation\ValidationException;

class ReactivateVendor
{
    public function handle(Vendor $vendor): Vendor
    {
        if ($vendor->status !== VendorStatus::Suspended) {
            throw ValidationException::withMessages([
                'status' => 'Only suspended vendors can be reactivated.',
            ]);
        }

        $vendor->forceFill([
            'status' => VendorStatus::Approved,
        ])->save();

        return $vendor;
    }
}
