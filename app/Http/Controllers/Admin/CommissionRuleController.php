<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommissionRuleScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVendorCommissionRuleRequest;
use App\Http\Requests\Admin\UpdateGlobalCommissionRuleRequest;
use App\Models\CommissionRule;
use App\Models\Vendor;
use App\Services\Commissions\CommissionCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class CommissionRuleController extends Controller
{
    public function index(Request $request, CommissionCalculator $calculator): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $search = $validated['q'] ?? null;

        $globalRule = CommissionRule::query()
            ->where('scope', CommissionRuleScope::Global)
            ->whereNull('vendor_id')
            ->where('is_active', true)
            ->latest()
            ->firstOrFail();

        return view('admin.commission-rules.index', [
            'globalRule' => $globalRule,
            'vendorRules' => CommissionRule::query()
                ->with('vendor.user')
                ->where('scope', CommissionRuleScope::Vendor)
                ->where('is_active', true)
                ->when(filled($search), fn (Builder $query) => $query->whereHas('vendor', fn (Builder $query) => $query
                    ->where('display_name', 'like', "%{$search}%")
                    ->orWhere('business_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'vendors' => Vendor::query()
                ->approved()
                ->whereDoesntHave('commissionRules', fn (Builder $query) => $query->where('scope', CommissionRuleScope::Vendor)->where('is_active', true))
                ->orderBy('display_name')
                ->get(['id', 'display_name', 'business_name']),
            'filters' => ['q' => $validated['q'] ?? ''],
            'calculator' => $calculator,
        ]);
    }

    public function updateGlobal(UpdateGlobalCommissionRuleRequest $request, CommissionCalculator $calculator): RedirectResponse
    {
        $rateBasisPoints = $this->parseRate($calculator, $request->validated('rate'));

        CommissionRule::query()
            ->where('scope', CommissionRuleScope::Global)
            ->whereNull('vendor_id')
            ->where('is_active', true)
            ->latest()
            ->firstOrFail()
            ->forceFill([
                'rate_basis_points' => $rateBasisPoints,
                'include_shipping' => $request->boolean('include_shipping'),
            ])
            ->save();

        return back()->with('status', 'Global commission rule updated. Future recognized commissions will use the new rule.');
    }

    public function storeVendor(StoreVendorCommissionRuleRequest $request, CommissionCalculator $calculator): RedirectResponse
    {
        $validated = $request->validated();

        CommissionRule::query()->updateOrCreate(
            [
                'scope' => CommissionRuleScope::Vendor,
                'vendor_id' => $validated['vendor_id'],
                'is_active' => true,
            ],
            [
                'rate_basis_points' => $this->parseRate($calculator, $validated['rate']),
                'include_shipping' => $request->boolean('include_shipping'),
            ],
        );

        return back()->with('status', 'Vendor commission override saved.');
    }

    public function destroyVendor(Vendor $vendor): RedirectResponse
    {
        CommissionRule::query()
            ->where('scope', CommissionRuleScope::Vendor)
            ->where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->update(['is_active' => false, 'ends_at' => now()]);

        return back()->with('status', 'Vendor override removed. Future orders use the global default.');
    }

    private function parseRate(CommissionCalculator $calculator, mixed $rate): int
    {
        try {
            return $calculator->parseBasisPoints($rate);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'rate' => $exception->getMessage(),
            ]);
        }
    }
}
