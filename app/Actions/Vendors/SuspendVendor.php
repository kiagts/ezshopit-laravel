<?php

namespace App\Actions\Vendors;

use App\Enums\VendorStatus;
use App\Models\Vendor;
use App\Services\Notifications\MarketplaceNotifier;
use Illuminate\Validation\ValidationException;

class SuspendVendor
{
    public function __construct(private readonly MarketplaceNotifier $notifier) {}

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

        $this->notifier->vendorStatusChanged($vendor, 'suspended');

        return $vendor;
    }
}
