<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorOrderCommission;
use App\Services\Commissions\CommissionCalculator;
use App\Services\Commissions\CommissionFinancials;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommissionController extends Controller
{
    public function index(Request $request, CommissionCalculator $calculator, CommissionFinancials $financials): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
        ]);

        $search = $validated['q'] ?? null;

        return view('admin.commissions.index', [
            'commissions' => VendorOrderCommission::query()
                ->with(['vendorOrder.vendor', 'vendorOrder.order', 'adjustments'])
                ->when(filled($validated['vendor_id'] ?? null), fn (Builder $query) => $query->whereHas('vendorOrder', fn (Builder $query) => $query->where('vendor_id', $validated['vendor_id'])))
                ->when(filled($search), fn (Builder $query) => $query->whereHas('vendorOrder', fn (Builder $query) => $query
                    ->where('vendor_order_number', 'like', "%{$search}%")
                    ->orWhereHas('order', fn (Builder $query) => $query->where('order_number', 'like', "%{$search}%"))))
                ->latest('recognized_at')
                ->paginate(20)
                ->withQueryString(),
            'vendors' => Vendor::query()->approved()->orderBy('display_name')->get(['id', 'display_name']),
            'filters' => ['q' => $validated['q'] ?? '', 'vendor_id' => $validated['vendor_id'] ?? ''],
            'calculator' => $calculator,
            'financials' => $financials,
        ]);
    }

    public function show(VendorOrderCommission $commission, CommissionCalculator $calculator, CommissionFinancials $financials): View
    {
        $commission->load([
            'vendorOrder.vendor',
            'vendorOrder.order.user',
            'vendorOrder.refundAllocations.refund',
            'adjustments.refund',
            'adjustments.refundAllocation.orderItem',
        ]);

        return view('admin.commissions.show', [
            'commission' => $commission,
            'calculator' => $calculator,
            'summary' => $financials->summaryFor($commission),
        ]);
    }
}
