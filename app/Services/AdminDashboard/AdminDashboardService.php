<?php

namespace App\Services\AdminDashboard;

use App\Enums\PaymentStatus;
use App\Enums\ProductApprovalStatus;
use App\Enums\ProductReviewStatus;
use App\Enums\ProductStatus;
use App\Enums\RefundStatus;
use App\Enums\UserRole;
use App\Enums\VendorLedgerEntryType;
use App\Enums\VendorPayoutStatus;
use App\Enums\VendorStatus;
use App\Models\CommissionAdjustment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Refund;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Models\VendorOrderCommission;
use App\Models\VendorPayout;
use App\Support\Money;

class AdminDashboardService
{
    public function __construct(private readonly AdminNavigationCounterService $counters)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $currency = Money::currencyCode();

        return [
            'currency' => $currency,
            'counters' => $this->counters->counts(),
            'marketplace' => $this->marketplaceSummary($currency),
            'finance' => $this->financeSummary($currency),
            'vendorQueue' => $this->vendorQueue(),
            'productQueue' => $this->productQueue(),
            'reviewQueue' => $this->reviewQueue(),
            'paymentReviewQueue' => $this->paymentReviewQueue(),
            'refundQueue' => $this->refundQueue(),
            'payoutQueue' => $this->payoutQueue(),
            'recentOrders' => $this->recentOrders(),
            'latestNotifications' => request()->user()
                ? request()->user()->notifications()->latest()->limit(5)->get()
                : collect(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function marketplaceSummary(string $currency): array
    {
        return [
            'approvedVendors' => Vendor::query()->where('status', VendorStatus::Approved)->count(),
            'activeProducts' => Product::query()
                ->where('status', ProductStatus::Active)
                ->where('approval_status', ProductApprovalStatus::Approved)
                ->count(),
            'customers' => User::query()->where('role', UserRole::Customer)->count(),
            'ordersToday' => Order::query()->whereDate('placed_at', today())->count(),
            'grossOrderValue' => (int) Order::query()
                ->where('currency', $currency)
                ->sum('grand_total'),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function financeSummary(string $currency): array
    {
        $ledger = VendorLedgerEntry::query()->where('currency', $currency);
        return [
            'recognizedCommission' => (int) VendorOrderCommission::query()
                ->where('currency', $currency)
                ->sum('original_commission_amount'),
            'commissionAdjustments' => (int) CommissionAdjustment::query()
                ->whereHas('commission', fn ($query) => $query->where('currency', $currency))
                ->sum('commission_amount_delta'),
            'netCommission' => abs((int) (clone $ledger)->where('type', VendorLedgerEntryType::Commission)->sum('amount'))
                - (int) (clone $ledger)->where('type', VendorLedgerEntryType::CommissionRefund)->sum('amount'),
            'ledgerPayouts' => abs((int) (clone $ledger)->where('type', VendorLedgerEntryType::Payout)->sum('amount')),
        ];
    }

    private function vendorQueue()
    {
        return Vendor::query()
            ->with('user:id,name,email')
            ->where('status', VendorStatus::Pending)
            ->latest('submitted_at')
            ->limit(5)
            ->get();
    }

    private function productQueue()
    {
        return Product::query()
            ->with(['vendor:id,display_name', 'category:id,name'])
            ->where('approval_status', ProductApprovalStatus::Pending)
            ->latest('submitted_at')
            ->limit(5)
            ->get();
    }

    private function reviewQueue()
    {
        return ProductReview::query()
            ->with(['product.vendor:id,display_name', 'user:id,name'])
            ->where('status', ProductReviewStatus::Pending)
            ->latest('submitted_at')
            ->limit(5)
            ->get();
    }

    private function paymentReviewQueue()
    {
        return Payment::query()
            ->with(['order.user:id,name,email'])
            ->whereNotNull('requires_review_at')
            ->whereNull('review_resolved_at')
            ->latest('requires_review_at')
            ->limit(5)
            ->get();
    }

    private function refundQueue()
    {
        return Refund::query()
            ->with(['order.user:id,name,email', 'payment:id,provider'])
            ->whereIn('status', [
                RefundStatus::Pending,
                RefundStatus::Processing,
                RefundStatus::Failed,
            ])
            ->latest('requested_at')
            ->limit(5)
            ->get();
    }

    private function payoutQueue()
    {
        return VendorPayout::query()
            ->with('vendor:id,display_name,slug')
            ->whereIn('status', [
                VendorPayoutStatus::Pending,
                VendorPayoutStatus::Approved,
                VendorPayoutStatus::Processing,
            ])
            ->latest('requested_at')
            ->limit(5)
            ->get();
    }

    private function recentOrders()
    {
        return Order::query()
            ->with(['user:id,name,email', 'payment:id,order_id,status,requires_review_at,review_resolved_at'])
            ->withCount('vendorOrders')
            ->latest('placed_at')
            ->limit(6)
            ->get();
    }
}
