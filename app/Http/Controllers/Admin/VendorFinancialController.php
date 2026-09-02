<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Services\VendorLedger\VendorEarningsService;
use App\Services\VendorPayouts\VendorPayoutBalanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VendorFinancialController extends Controller
{
    public function show(Request $request, Vendor $vendor, VendorEarningsService $earnings, VendorPayoutBalanceService $payoutBalances): View
    {
        $validated = $request->validate([
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        $vendor->load('user');
        $currency = strtoupper($validated['currency'] ?? $earnings->currenciesFor($vendor)[0]);

        return view('admin.vendors.financials', [
            'vendor' => $vendor,
            'summary' => $earnings->summaryFor($vendor, $currency),
            'payoutBalance' => $payoutBalances->summaryFor($vendor, $currency),
            'recentPayouts' => $vendor->payouts()->latest('requested_at')->limit(5)->get(),
            'currencies' => $earnings->currenciesFor($vendor),
            'filters' => ['currency' => $currency],
            'entries' => VendorLedgerEntry::query()
                ->with(['vendorOrder:id,vendor_order_number', 'order:id,order_number', 'refund:id,refund_number', 'commission:id,vendor_order_id'])
                ->where('vendor_id', $vendor->id)
                ->where('currency', $currency)
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }
}
