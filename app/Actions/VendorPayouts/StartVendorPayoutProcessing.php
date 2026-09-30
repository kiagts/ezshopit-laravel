<?php

namespace App\Actions\VendorPayouts;

use App\Enums\VendorPayoutStatus;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayout;
use App\Services\Notifications\MarketplaceNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartVendorPayoutProcessing
{
    public function __construct(private readonly MarketplaceNotifier $notifier) {}

    public function handle(VendorPayout $payout, User $admin): VendorPayout
    {
        $payout = DB::transaction(function () use ($payout, $admin): VendorPayout {
            Vendor::query()->whereKey($payout->vendor_id)->lockForUpdate()->firstOrFail();
            $payout = VendorPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if ($payout->status !== VendorPayoutStatus::Approved) {
                throw ValidationException::withMessages(['payout' => 'Only approved payouts can start processing.']);
            }

            $payout->forceFill([
                'status' => VendorPayoutStatus::Processing,
                'processing_at' => now(),
                'processed_by' => $admin->id,
            ])->save();

            return $payout;
        });

        $this->notifier->payoutStatusChanged($payout, VendorPayoutStatus::Processing);

        return $payout;
    }
}
