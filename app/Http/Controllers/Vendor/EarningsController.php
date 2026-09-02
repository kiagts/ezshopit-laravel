<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\VendorLedgerEntryType;
use App\Http\Controllers\Controller;
use App\Models\VendorLedgerEntry;
use App\Services\VendorLedger\VendorEarningsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EarningsController extends Controller
{
    public function __invoke(Request $request, VendorEarningsService $earnings): View
    {
        $validated = $request->validate([
            'type' => ['nullable', Rule::enum(VendorLedgerEntryType::class)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        $vendor = $request->user()->vendor;
        $currency = strtoupper($validated['currency'] ?? $earnings->currenciesFor($vendor)[0]);

        return view('vendor.earnings.index', [
            'vendor' => $vendor,
            'summary' => $earnings->summaryFor($vendor, $currency),
            'currencies' => $earnings->currenciesFor($vendor),
            'types' => VendorLedgerEntryType::cases(),
            'filters' => [
                'type' => $validated['type'] ?? '',
                'date_from' => $validated['date_from'] ?? '',
                'date_to' => $validated['date_to'] ?? '',
                'currency' => $currency,
            ],
            'entries' => VendorLedgerEntry::query()
                ->with(['vendorOrder:id,vendor_order_number', 'order:id,order_number'])
                ->where('vendor_id', $vendor->id)
                ->where('currency', $currency)
                ->when($validated['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
                ->when($validated['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                ->when($validated['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
        ]);
    }
}
