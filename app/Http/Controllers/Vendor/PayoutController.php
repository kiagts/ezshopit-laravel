<?php

namespace App\Http\Controllers\Vendor;

use App\Actions\VendorPayouts\CancelVendorPayout;
use App\Actions\VendorPayouts\RequestVendorPayout;
use App\Http\Controllers\Controller;
use App\Models\VendorPayout;
use App\Models\VendorPayoutMethod;
use App\Services\VendorPayouts\VendorPayoutBalanceService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class PayoutController extends Controller
{
    public function index(Request $request, VendorPayoutBalanceService $balances): View
    {
        $vendor = $request->user()->vendor;
        $currency = Money::currencyCode();

        return view('vendor.payouts.index', [
            'vendor' => $vendor,
            'balance' => $balances->summaryFor($vendor, $currency),
            'activePayout' => $balances->activePayoutFor($vendor, $currency),
            'methods' => $vendor->payoutMethods()->latest()->get(),
            'payouts' => $vendor->payouts()
                ->with('ledgerEntry')
                ->latest('requested_at')
                ->paginate(12),
        ]);
    }

    public function store(Request $request, RequestVendorPayout $requestPayout): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'string'],
            'payout_method_id' => ['required', 'integer'],
        ]);

        try {
            $amount = Money::parseDecimal($validated['amount']);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['amount' => $exception->getMessage()])->withInput();
        }

        $vendor = $request->user()->vendor;
        $method = VendorPayoutMethod::query()
            ->where('vendor_id', $vendor->id)
            ->findOrFail($validated['payout_method_id']);

        $payout = $requestPayout->handle($vendor, $request->user(), $method, (int) $amount, Money::currencyCode());

        return redirect()
            ->route('vendor.payouts.show', $payout)
            ->with('status', 'Payout request submitted.');
    }

    public function show(Request $request, VendorPayout $payout): View
    {
        abort_unless((int) $payout->vendor_id === (int) $request->user()->vendor->id, 404);

        return view('vendor.payouts.show', [
            'payout' => $payout->load(['ledgerEntry', 'payoutMethod']),
        ]);
    }

    public function cancel(Request $request, VendorPayout $payout, CancelVendorPayout $cancel): RedirectResponse
    {
        abort_unless((int) $payout->vendor_id === (int) $request->user()->vendor->id, 404);

        $cancel->handle($payout, $request->user(), 'Cancelled by vendor.');

        return redirect()
            ->route('vendor.payouts.show', $payout)
            ->with('status', 'Payout request cancelled.');
    }
}
