<?php

namespace App\Services\AdminDashboard;

use App\Enums\ProductApprovalStatus;
use App\Enums\ProductReviewStatus;
use App\Enums\RefundStatus;
use App\Enums\VendorPayoutStatus;
use App\Enums\VendorStatus;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Refund;
use App\Models\Vendor;
use App\Models\VendorPayout;

class AdminNavigationCounterService
{
    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return [
            'pendingVendors' => Vendor::query()
                ->where('status', VendorStatus::Pending)
                ->count(),
            'pendingProducts' => Product::query()
                ->where('approval_status', ProductApprovalStatus::Pending)
                ->count(),
            'pendingReviews' => ProductReview::query()
                ->where('status', ProductReviewStatus::Pending)
                ->count(),
            'paymentReviews' => Payment::query()
                ->whereNotNull('requires_review_at')
                ->whereNull('review_resolved_at')
                ->count(),
            'refundsRequiringAttention' => Refund::query()
                ->whereIn('status', [
                    RefundStatus::Pending,
                    RefundStatus::Processing,
                    RefundStatus::Failed,
                ])
                ->count(),
            'payoutsAwaitingAction' => VendorPayout::query()
                ->whereIn('status', [
                    VendorPayoutStatus::Pending,
                    VendorPayoutStatus::Approved,
                    VendorPayoutStatus::Processing,
                ])
                ->count(),
        ];
    }
}
