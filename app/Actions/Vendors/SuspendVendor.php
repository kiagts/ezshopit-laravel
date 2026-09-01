<?php

namespace App\Actions\Vendors;

use App\Enums\VendorStatus;
use App\Models\Vendor;
use Illuminate\Validation\ValidationException;

class SuspendVendor
{
    public function handle(Vendor $vendor): Vendor
    {
        if ($vendor->status !== VendorStatus::Approved) {
            throw ValidationException::withMessages([
                'status' => 'Only approved vendors can be suspended.',
            ]);
        }

        $vendor->forceFill([
            'status' => VendorStatus::Suspended,
        ])->save();

        return $vendor;
    }
}
