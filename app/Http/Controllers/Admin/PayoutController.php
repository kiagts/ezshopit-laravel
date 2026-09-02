<?php

namespace App\Http\Controllers\Admin;

use App\Actions\VendorPayouts\ApproveVendorPayout;
use App\Actions\VendorPayouts\CancelVendorPayout;
use App\Actions\VendorPayouts\MarkVendorPayoutPaid;
use App\Actions\VendorPayouts\RejectVendorPayout;
use App\Actions\VendorPayouts\StartVendorPayoutProcessing;
use App\Enums\VendorPayoutStatus;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorPayout;
use App\Services\VendorPayouts\VendorPayoutBalanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PayoutController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(VendorPayoutStatus::class)],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'search' => ['nullable', 'string', 'max:120'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        return view('admin.payouts.index', [
            'payouts' => VendorPayout::query()
                ->with('vendor.user')
                ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
                ->when($validated['vendor_id'] ?? null, fn (Builder $query, int $vendorId) => $query->where('vendor_id', $vendorId))
                ->when($validated['search'] ?? null, fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('payout_number', 'like', "%{$search}%")
                        ->orWhereHas('vendor', fn (Builder $query) => $query->where('display_name', 'like', "%{$search}%"));
                }))
                ->when($validated['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('requested_at', '>=', $date))
                ->when($validated['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('requested_at', '<=', $date))
                ->latest('requested_at')
                ->paginate(15)
                ->withQueryString(),
            'statuses' => VendorPayoutStatus::cases(),
            'vendors' => Vendor::query()->orderBy('display_name')->get(['id', 'display_name']),
            'filters' => [
                'status' => $validated['status'] ?? '',
                'vendor_id' => $validated['vendor_id'] ?? '',
                'search' => $validated['search'] ?? '',
                'date_from' => $validated['date_from'] ?? '',
                'date_to' => $validated['date_to'] ?? '',
            ],
        ]);
    }

    public function show(VendorPayout $payout, VendorPayoutBalanceService $balances): View
    {
        $payout->load(['vendor.user', 'payoutMethod', 'ledgerEntry']);

        return view('admin.payouts.show', [
            'payout' => $payout,
            'balance' => $balances->summaryFor($payout->vendor, $payout->currency),
        ]);
    }

    public function approve(Request $request, VendorPayout $payout, ApproveVendorPayout $approve): RedirectResponse
    {
        $approve->handle($payout, $request->user());

        return back()->with('status', 'Payout approved.');
    }

    public function reject(Request $request, VendorPayout $payout, RejectVendorPayout $reject): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $reject->handle($payout, $request->user(), $validated['reason']);

        return back()->with('status', 'Payout rejected.');
    }

    public function processing(Request $request, VendorPayout $payout, StartVendorPayoutProcessing $processing): RedirectResponse
    {
        $processing->handle($payout, $request->user());

        return back()->with('status', 'Payout marked processing.');
    }

    public function paid(Request $request, VendorPayout $payout, MarkVendorPayoutPaid $paid): RedirectResponse
    {
        $validated = $request->validate([
            'external_reference' => ['nullable', 'string', 'max:120'],
            'internal_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $paid->handle(
            $payout,
            $request->user(),
            $validated['external_reference'] ?? null,
            $validated['internal_notes'] ?? null,
        );

        return back()->with('status', 'Payout marked paid.');
    }

    public function cancel(Request $request, VendorPayout $payout, CancelVendorPayout $cancel): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $cancel->handle($payout, $request->user(), $validated['reason']);

        return back()->with('status', 'Payout cancelled.');
    }
}
