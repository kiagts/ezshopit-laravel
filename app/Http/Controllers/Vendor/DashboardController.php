<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\ProductApprovalStatus;
use App\Enums\ProductReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Services\VendorLedger\VendorEarningsService;
use App\Services\VendorPayouts\VendorPayoutBalanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, VendorEarningsService $earnings, VendorPayoutBalanceService $payoutBalances): View
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
            'earningsSummary' => $earnings->summaryFor($vendor),
            'payoutBalance' => $payoutBalances->summaryFor($vendor),
            'reviewStats' => [
                'published' => ProductReview::query()
                    ->where('status', ProductReviewStatus::Approved)
                    ->whereHas('product', fn ($query) => $query->where('vendor_id', $vendor->id))
                    ->count(),
                'average' => ProductReview::query()
                    ->where('status', ProductReviewStatus::Approved)
                    ->whereHas('product', fn ($query) => $query->where('vendor_id', $vendor->id))
                    ->avg('rating'),
            ],
            'recentReviews' => ProductReview::query()
                ->with('product:id,name,vendor_id')
                ->whereHas('product', fn ($query) => $query->where('vendor_id', $vendor->id))
                ->latest('submitted_at')
                ->limit(3)
                ->get(),
        ]);
    }
}
