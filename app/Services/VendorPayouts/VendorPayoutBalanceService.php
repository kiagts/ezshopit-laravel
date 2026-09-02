<?php

namespace App\Services\VendorPayouts;

use App\Enums\VendorLedgerEntryType;
use App\Enums\VendorPayoutStatus;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Models\VendorPayout;
use App\Services\VendorLedger\VendorEarningsService;
use App\Support\Money;
use Illuminate\Support\Carbon;

class VendorPayoutBalanceService
{
    public function __construct(private readonly VendorEarningsService $earnings) {}

    public function summaryFor(Vendor $vendor, ?string $currency = null, ?Carbon $asOf = null): VendorPayoutBalance
    {
        $currency = strtoupper($currency ?: $this->earnings->currenciesFor($vendor)[0] ?? Money::currencyCode());
        $earnings = $this->earnings->summaryFor($vendor, $currency, $asOf);
        $activeReserved = $this->activeReserved($vendor, $currency);

        return new VendorPayoutBalance(
            currency: $currency,
            availableLedgerBalance: $earnings->availableBalance,
            activePayoutReserved: $activeReserved,
            requestableBalance: max(0, $earnings->availableBalance - $activeReserved),
            minimumPayout: $this->minimumPayout(),
            paidOutTotal: $this->paidOutTotal($vendor, $currency),
        );
    }

    public function activeReserved(Vendor $vendor, string $currency): int
    {
        return (int) VendorPayout::query()
            ->where('vendor_id', $vendor->id)
            ->where('currency', strtoupper($currency))
            ->whereIn('status', $this->activeStatuses())
            ->sum('amount');
    }

    public function activePayoutFor(Vendor $vendor, string $currency): ?VendorPayout
    {
        return VendorPayout::query()
            ->where('vendor_id', $vendor->id)
            ->where('currency', strtoupper($currency))
            ->whereIn('status', $this->activeStatuses())
            ->latest('requested_at')
            ->first();
    }

    public function paidOutTotal(Vendor $vendor, string $currency): int
    {
        return abs((int) VendorLedgerEntry::query()
            ->where('vendor_id', $vendor->id)
            ->where('currency', strtoupper($currency))
            ->where('type', VendorLedgerEntryType::Payout)
            ->sum('amount'));
    }

    public function minimumPayout(): int
    {
        return max(1, (int) config('marketplace.minimum_vendor_payout', 5000));
    }

    public function activeStatuses(): array
    {
        return [
            VendorPayoutStatus::Pending->value,
            VendorPayoutStatus::Approved->value,
            VendorPayoutStatus::Processing->value,
        ];
    }
}
