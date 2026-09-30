<?php

namespace App\Actions\VendorPayouts;

use App\Enums\VendorPayoutStatus;
use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayout;
use App\Models\VendorPayoutMethod;
use App\Services\Notifications\MarketplaceNotifier;
use App\Services\VendorPayouts\VendorPayoutBalanceService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestVendorPayout
{
    use GeneratesPayoutNumbers;

    public function __construct(
        private readonly VendorPayoutBalanceService $balances,
        private readonly MarketplaceNotifier $notifier,
    ) {}

    public function handle(Vendor $vendor, User $requester, VendorPayoutMethod $method, int $amount, ?string $currency = null): VendorPayout
    {
        $currency = strtoupper($currency ?: Money::currencyCode());

        $payout = DB::transaction(function () use ($vendor, $requester, $method, $amount, $currency): VendorPayout {
            $lockedVendor = Vendor::query()->whereKey($vendor->id)->lockForUpdate()->firstOrFail();
            $method = VendorPayoutMethod::query()->whereKey($method->id)->lockForUpdate()->firstOrFail();

            if ($lockedVendor->status !== VendorStatus::Approved) {
                throw ValidationException::withMessages([
                    'amount' => 'Your vendor account must be approved before requesting a payout.',
                ]);
            }

            if ((int) $method->vendor_id !== (int) $lockedVendor->id) {
                throw ValidationException::withMessages([
                    'payout_method_id' => 'Choose one of your payout methods.',
                ]);
            }

            if ($this->balances->activePayoutFor($lockedVendor, $currency)) {
                throw ValidationException::withMessages([
                    'amount' => 'You already have a payout in progress.',
                ]);
            }

            $balance = $this->balances->summaryFor($lockedVendor, $currency);

            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Enter a payout amount greater than zero.']);
            }

            if ($amount < $balance->minimumPayout) {
                throw ValidationException::withMessages([
                    'amount' => 'The minimum payout amount is '.Money::format($balance->minimumPayout, $currency).'.',
                ]);
            }

            if ($amount > $balance->requestableBalance) {
                throw ValidationException::withMessages([
                    'amount' => 'The requested payout exceeds your requestable balance.',
                ]);
            }

            return VendorPayout::query()->create([
                'payout_number' => $this->payoutNumber(),
                'vendor_id' => $lockedVendor->id,
                'status' => VendorPayoutStatus::Pending,
                'amount' => $amount,
                'currency' => $currency,
                'payout_method_id' => $method->id,
                'payout_method_type' => $method->type->value,
                'destination_snapshot' => $method->destinationSnapshot(),
                'requested_at' => now(),
                'requested_by' => $requester->id,
            ]);
        });

        $this->notifier->payoutRequested($payout);

        return $payout;
    }
}
