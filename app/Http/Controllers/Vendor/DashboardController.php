<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\ProductApprovalStatus;
use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $vendor = $request->user()->vendor;
        $variantQuery = ProductVariant::query()
            ->whereHas('product', fn ($query) => $query->where('vendor_id', $vendor->id));
        $inventoryQuery = Inventory::query()
            ->whereHas('variant.product', fn ($query) => $query->where('vendor_id', $vendor->id));

        return view('vendor.dashboard', [
            'user' => $request->user(),
            'vendor' => $vendor,
            'productStats' => [
                'total' => $vendor->products()->count(),
                'pending' => $vendor->products()->where('approval_status', ProductApprovalStatus::Pending)->count(),
                'approved' => $vendor->products()->where('approval_status', ProductApprovalStatus::Approved)->count(),
            ],
            'inventoryStats' => [
                'variants' => (clone $variantQuery)->count(),
                'lowStock' => (clone $inventoryQuery)->lowStock()->count(),
                'outOfStock' => (clone $inventoryQuery)->outOfStock()->count(),
            ],
        ]);
    }
}
