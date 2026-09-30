<?php

namespace App\Actions\Vendors;

use App\Enums\VendorStatus;
use App\Models\Vendor;
use App\Services\Notifications\MarketplaceNotifier;
use Illuminate\Validation\ValidationException;

class SubmitVendorApplication
{
    public function __construct(private readonly MarketplaceNotifier $notifier) {}

    public function handle(Vendor $vendor): Vendor
    {
        if (! in_array($vendor->status, [VendorStatus::Draft, VendorStatus::Rejected], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only draft or rejected applications can be submitted.',
            ]);
        }

        $vendor->forceFill([
            'status' => VendorStatus::Pending,
            'submitted_at' => now(),
            'rejection_reason' => null,
            'rejected_at' => null,
            'rejected_by' => null,
            'approved_at' => null,
            'approved_by' => null,
        ])->save();

        $this->notifier->vendorApplicationSubmitted($vendor);

        return $vendor;
    }
}
