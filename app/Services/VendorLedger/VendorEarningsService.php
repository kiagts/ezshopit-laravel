<?php

namespace App\Services\VendorLedger;

use App\Enums\VendorLedgerEntryType;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Support\Money;
use Illuminate\Support\Carbon;

class VendorEarningsService
{
    public function summaryFor(Vendor $vendor, ?string $currency = null, ?Carbon $asOf = null): VendorBalanceSummary
    {
        $currency ??= $this->defaultCurrencyFor($vendor);
        $asOf ??= now();

        $base = VendorLedgerEntry::query()
            ->where('vendor_id', $vendor->id)
            ->where('currency', $currency);

        return new VendorBalanceSummary(
            currency: $currency,
            availableBalance: (int) (clone $base)->where(fn ($query) => $query
                ->whereNull('available_at')
                ->orWhere('available_at', '<=', $asOf))->sum('amount'),
            pendingBalance: (int) (clone $base)->where('available_at', '>', $asOf)->sum('amount'),
            netEarnings: (int) (clone $base)->sum('amount'),
            grossSales: (int) (clone $base)->where('type', VendorLedgerEntryType::Sale)->sum('amount'),
            commissionCharged: abs((int) (clone $base)->where('type', VendorLedgerEntryType::Commission)->sum('amount')),
            refunds: abs((int) (clone $base)->where('type', VendorLedgerEntryType::Refund)->sum('amount')),
            commissionRefunds: (int) (clone $base)->where('type', VendorLedgerEntryType::CommissionRefund)->sum('amount'),
        );
    }

    public function currenciesFor(Vendor $vendor): array
    {
        $currencies = VendorLedgerEntry::query()
            ->where('vendor_id', $vendor->id)
            ->distinct()
            ->orderBy('currency')
            ->pluck('currency')
            ->all();

        return $currencies ?: [Money::currencyCode()];
    }

    private function defaultCurrencyFor(Vendor $vendor): string
    {
        return VendorLedgerEntry::query()
            ->where('vendor_id', $vendor->id)
            ->orderByDesc('created_at')
            ->value('currency') ?: Money::currencyCode();
    }
}
