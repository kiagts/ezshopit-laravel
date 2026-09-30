<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Vendors\ApproveVendor;
use App\Actions\Vendors\ReactivateVendor;
use App\Actions\Vendors\RejectVendor;
use App\Actions\Vendors\SuspendVendor;
use App\Enums\VendorStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectVendorRequest;
use App\Models\Vendor;
use App\Services\VendorLedger\VendorEarningsService;
use App\Services\VendorPayouts\VendorPayoutBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VendorController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(VendorStatus::class)],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $vendors = Vendor::query()
            ->with('user')
            ->withCount(['products', 'vendorOrders'])
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->search($validated['search'] ?? null)
            ->latest('submitted_at')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.vendors.index', [
            'vendors' => $vendors,
            'statuses' => VendorStatus::cases(),
            'activeStatus' => $validated['status'] ?? null,
            'search' => $validated['search'] ?? '',
        ]);
    }

    public function show(Vendor $vendor, VendorEarningsService $earnings, VendorPayoutBalanceService $payoutBalances): View
    {
        $vendor->loadCount(['products', 'vendorOrders', 'payouts']);
        $currency = $earnings->currenciesFor($vendor)[0];

        return view('admin.vendors.show', [
            'vendor' => $vendor->load(['user', 'approver', 'rejecter']),
            'financialSummary' => $earnings->summaryFor($vendor, $currency),
            'payoutBalance' => $payoutBalances->summaryFor($vendor, $currency),
            'recentPayouts' => $vendor->payouts()->latest('requested_at')->limit(3)->get(),
        ]);
    }

    public function approve(Vendor $vendor, ApproveVendor $approveVendor): RedirectResponse
    {
        $approveVendor->handle($vendor, request()->user());

        return redirect()->route('admin.vendors.show', $vendor)->with('status', 'Vendor application approved.');
    }

    public function reject(RejectVendorRequest $request, Vendor $vendor, RejectVendor $rejectVendor): RedirectResponse
    {
        $rejectVendor->handle($vendor, $request->user(), $request->validated('rejection_reason'));

        return redirect()->route('admin.vendors.show', $vendor)->with('status', 'Vendor application rejected.');
    }

    public function suspend(Vendor $vendor, SuspendVendor $suspendVendor): RedirectResponse
    {
        $suspendVendor->handle($vendor);

        return redirect()->route('admin.vendors.show', $vendor)->with('status', 'Vendor suspended.');
    }

    public function reactivate(Vendor $vendor, ReactivateVendor $reactivateVendor): RedirectResponse
    {
        $reactivateVendor->handle($vendor);

        return redirect()->route('admin.vendors.show', $vendor)->with('status', 'Vendor reactivated.');
    }
}
