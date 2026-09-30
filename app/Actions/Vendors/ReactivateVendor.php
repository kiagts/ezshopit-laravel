<?php

namespace App\Actions\Vendors;

use App\Enums\VendorStatus;
use App\Models\Vendor;
use App\Services\Notifications\MarketplaceNotifier;
use Illuminate\Validation\ValidationException;

class ReactivateVendor
{
    public function __construct(private readonly MarketplaceNotifier $notifier) {}

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

        $this->notifier->vendorStatusChanged($vendor, 'reactivated');

        return $vendor;
    }
}
