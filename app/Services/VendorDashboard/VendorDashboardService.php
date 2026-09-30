<?php

namespace App\Services\VendorDashboard;

use App\Enums\ProductApprovalStatus;
use App\Enums\ProductReviewStatus;
use App\Enums\ProductStatus;
use App\Enums\PromotionFundingSource;
use App\Enums\VendorOrderStatus;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Services\VendorLedger\VendorEarningsService;
use App\Services\VendorPayouts\VendorPayoutBalanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class VendorDashboardService
{
    public function __construct(
        private readonly VendorEarningsService $earnings,
        private readonly VendorPayoutBalanceService $payoutBalances,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summaryFor(Vendor $vendor): array
    {
        $earningsSummary = $this->earnings->summaryFor($vendor);
        $payoutBalance = $this->payoutBalances->summaryFor($vendor, $earningsSummary->currency);

        return [
            'earningsSummary' => $earningsSummary,
            'payoutBalance' => $payoutBalance,
            'activePayout' => $this->payoutBalances->activePayoutFor($vendor, $payoutBalance->currency),
            'productStats' => $this->productStats($vendor),
            'inventoryStats' => $this->inventoryStats($vendor),
            'orderStats' => $this->orderStats($vendor),
            'recentOrders' => $this->recentOrders($vendor),
            'productAttention' => $this->productAttention($vendor),
            'recentReviews' => $this->recentReviews($vendor),
            'reviewStats' => $this->reviewStats($vendor),
            'promotionStats' => $this->promotionStats($vendor),
            'latestNotifications' => $vendor->user?->notifications()->latest()->limit(4)->get() ?? collect(),
            'unreadNotificationsCount' => $vendor->user?->unreadNotifications()->count() ?? 0,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function productStats(Vendor $vendor): array
    {
        return [
            'active' => Product::query()
                ->where('vendor_id', $vendor->id)
                ->where('status', ProductStatus::Active)
                ->where('approval_status', ProductApprovalStatus::Approved)
                ->count(),
            'total' => Product::query()->where('vendor_id', $vendor->id)->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function productAttention(Vendor $vendor): array
    {
        return [
            'draft' => Product::query()
                ->where('vendor_id', $vendor->id)
                ->where('status', ProductStatus::Draft)
                ->where('approval_status', ProductApprovalStatus::Draft)
                ->count(),
            'pending' => Product::query()->where('vendor_id', $vendor->id)->where('approval_status', ProductApprovalStatus::Pending)->count(),
            'rejected' => Product::query()->where('vendor_id', $vendor->id)->where('approval_status', ProductApprovalStatus::Rejected)->count(),
            'inactive' => Product::query()->where('vendor_id', $vendor->id)->where('status', ProductStatus::Inactive)->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function inventoryStats(Vendor $vendor): array
    {
        $inventory = Inventory::query()
            ->whereHas('variant.product', fn (Builder $query) => $query->where('vendor_id', $vendor->id));

        return [
            'variants' => (clone $inventory)->count(),
            'lowStock' => (clone $inventory)->lowStock()->count(),
            'outOfStock' => (clone $inventory)->outOfStock()->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function orderStats(Vendor $vendor): array
    {
        return [
            'requiringAction' => VendorOrder::query()
                ->where('vendor_id', $vendor->id)
                ->whereIn('status', $this->actionableOrderStatuses())
                ->count(),
            'pending' => VendorOrder::query()->where('vendor_id', $vendor->id)->where('status', VendorOrderStatus::Pending)->count(),
            'processing' => VendorOrder::query()->where('vendor_id', $vendor->id)->whereIn('status', [
                VendorOrderStatus::Confirmed,
                VendorOrderStatus::Processing,
            ])->count(),
            'readyToShip' => VendorOrder::query()->where('vendor_id', $vendor->id)->where('status', VendorOrderStatus::ReadyToShip)->count(),
        ];
    }

    private function recentOrders(Vendor $vendor)
    {
        return VendorOrder::query()
            ->with(['order:id,order_number,shipping_first_name,shipping_last_name,placed_at', 'items:id,vendor_order_id,quantity'])
            ->where('vendor_id', $vendor->id)
            ->latest()
            ->limit(6)
            ->get();
    }

    private function recentReviews(Vendor $vendor)
    {
        return ProductReview::query()
            ->with('product:id,name,vendor_id')
            ->whereHas('product', fn (Builder $query) => $query->where('vendor_id', $vendor->id))
            ->latest('submitted_at')
            ->limit(4)
            ->get();
    }

    /**
     * @return array<string, int|float|null>
     */
    private function reviewStats(Vendor $vendor): array
    {
        $totals = Product::query()
            ->where('vendor_id', $vendor->id)
            ->selectRaw('COALESCE(SUM(rating_sum), 0) as rating_sum_total, COALESCE(SUM(rating_count), 0) as rating_count_total')
            ->first();

        $ratingCount = (int) $totals->rating_count_total;

        return [
            'published' => ProductReview::query()
                ->where('status', ProductReviewStatus::Approved)
                ->whereHas('product', fn (Builder $query) => $query->where('vendor_id', $vendor->id))
                ->count(),
            'ratingCount' => $ratingCount,
            'averageProductRating' => $ratingCount > 0 ? round(((int) $totals->rating_sum_total) / $ratingCount, 1) : null,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function promotionStats(Vendor $vendor): array
    {
        $vendorPromotions = Promotion::query()
            ->where('vendor_id', $vendor->id)
            ->where('funding_source', PromotionFundingSource::Vendor);

        return [
            'active' => (clone $vendorPromotions)->where('is_active', true)->count(),
            'redemptions' => PromotionRedemption::query()
                ->whereHas('promotion', fn (Builder $query) => $query->where('vendor_id', $vendor->id))
                ->count(),
            'vendorFundedDiscounts' => (int) DB::table('order_discount_allocations')
                ->join('promotions', 'order_discount_allocations.promotion_id', '=', 'promotions.id')
                ->join('vendor_orders', 'order_discount_allocations.vendor_order_id', '=', 'vendor_orders.id')
                ->where('promotions.vendor_id', $vendor->id)
                ->where('promotions.funding_source', PromotionFundingSource::Vendor->value)
                ->where('vendor_orders.vendor_id', $vendor->id)
                ->sum('order_discount_allocations.amount'),
        ];
    }

    /**
     * @return array<int, VendorOrderStatus>
     */
    private function actionableOrderStatuses(): array
    {
        return [
            VendorOrderStatus::Pending,
            VendorOrderStatus::Confirmed,
            VendorOrderStatus::Processing,
            VendorOrderStatus::ReadyToShip,
        ];
    }
}
