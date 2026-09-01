<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
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
        ]);

        $vendor = $request->user()->vendor;
        $search = $validated['q'] ?? null;

        return view('vendor.commissions.index', [
            'commissions' => VendorOrderCommission::query()
                ->with(['vendorOrder.order', 'adjustments'])
                ->whereHas('vendorOrder', fn (Builder $query) => $query->where('vendor_id', $vendor->id))
                ->when(filled($search), fn (Builder $query) => $query->whereHas('vendorOrder', fn (Builder $query) => $query
                    ->where('vendor_order_number', 'like', "%{$search}%")
                    ->orWhereHas('order', fn (Builder $query) => $query->where('order_number', 'like', "%{$search}%"))))
                ->latest('recognized_at')
                ->paginate(15)
                ->withQueryString(),
            'filters' => ['q' => $validated['q'] ?? ''],
            'calculator' => $calculator,
            'financials' => $financials,
        ]);
    }
}
