<?php

namespace App\Services\Notifications;

use App\Enums\PaymentStatus;
use App\Enums\ProductReviewStatus;
use App\Enums\RefundStatus;
use App\Enums\VendorOrderStatus;
use App\Enums\VendorPayoutStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Refund;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Models\VendorPayout;
use App\Notifications\Admin\PaymentRequiresReviewNotification;
use App\Notifications\Orders\OrderCancelledNotification;
use App\Notifications\Orders\OrderPlacedNotification;
use App\Notifications\Orders\PaymentFailedNotification;
use App\Notifications\Orders\PaymentSucceededNotification;
use App\Notifications\Orders\VendorOrderCancelledNotification;
use App\Notifications\Orders\VendorOrderCreatedNotification;
use App\Notifications\Orders\VendorOrderDeliveredNotification;
use App\Notifications\Orders\VendorOrderShippedNotification;
use App\Notifications\Payouts\PayoutApprovedNotification;
use App\Notifications\Payouts\PayoutPaidNotification;
use App\Notifications\Payouts\PayoutProcessingNotification;
use App\Notifications\Payouts\PayoutRejectedNotification;
use App\Notifications\Payouts\PayoutRequestedNotification;
use App\Notifications\Products\ProductApprovedNotification;
use App\Notifications\Products\ProductRejectedNotification;
use App\Notifications\Products\ProductSubmittedNotification;
use App\Notifications\Refunds\RefundFailedNotification;
use App\Notifications\Refunds\RefundSucceededNotification;
use App\Notifications\Refunds\VendorRefundNotification;
use App\Notifications\Reviews\ProductReviewSubmittedNotification;
use App\Notifications\Reviews\ReviewApprovedNotification;
use App\Notifications\Reviews\ReviewRejectedNotification;
use App\Notifications\Vendors\VendorApplicationSubmittedNotification;
use App\Notifications\Vendors\VendorApprovedNotification;
use App\Notifications\Vendors\VendorReactivatedNotification;
use App\Notifications\Vendors\VendorRejectedNotification;
use App\Notifications\Vendors\VendorSuspendedNotification;
use App\Support\Money;

class CommerceNotificationListener
{
    public function __construct(
        private readonly SendUniqueNotification $sendUnique,
        private readonly AdminRecipientResolver $admins,
    ) {}

    public function orderPlaced(Order $order): void
    {
        $order->loadMissing(['user', 'vendorOrders.vendor.user']);

        $this->sendUnique->handle($order->user, new OrderPlacedNotification(
            'Order placed',
            "Your order {$order->order_number} has been placed for ".Money::format($order->grand_total, $order->currency).'.',
            route('account.orders.show', $order, false),
            "order:{$order->id}:placed:user:{$order->user_id}",
            'success',
            true,
            "Your order {$order->order_number} has been placed",
        ));

        foreach ($order->vendorOrders as $vendorOrder) {
            $this->newVendorOrder($vendorOrder);
        }
    }

    public function paymentChanged(Payment $payment, PaymentStatus $status): void
    {
        if ($payment->fresh()->status !== $status) {
            return;
        }
        $payment->loadMissing('order.user');

        if ($status === PaymentStatus::Paid) {
            foreach ($payment->order->vendorOrders as $vendorOrder) {
                $this->newVendorOrder($vendorOrder);
            }
            $this->sendUnique->handle($payment->order->user, new PaymentSucceededNotification(
                'Payment confirmed',
                "Payment for order {$payment->order->order_number} was confirmed.",
                route('account.orders.show', $payment->order, false),
                "payment:{$payment->id}:paid:user:{$payment->order->user_id}",
                'success',
                true,
                "Payment confirmed for {$payment->order->order_number}",
            ));
        }

        if ($status === PaymentStatus::Failed) {
            $this->sendUnique->handle($payment->order->user, new PaymentFailedNotification(
                'Payment needs attention',
                "Payment for order {$payment->order->order_number} could not be completed.",
                route('account.orders.show', $payment->order, false),
                "payment:{$payment->id}:failed:user:{$payment->order->user_id}",
                'warning',
                true,
                "Payment issue for {$payment->order->order_number}",
            ));
        }
    }

    public function paymentRequiresReview(Payment $payment): void
    {
        $payment->loadMissing('order');

        foreach ($this->admins->recipients() as $admin) {
            $this->sendUnique->handle($admin, new PaymentRequiresReviewNotification(
                'Payment requires review',
                "Payment for order {$payment->order->order_number} requires admin review.",
                route('admin.payments.show', $payment, false),
                "payment:{$payment->id}:requires-review:admin:{$admin->id}",
                'warning',
            ));
        }
    }

    public function newVendorOrder(VendorOrder $vendorOrder): void
    {
        if (! app(\App\Services\Payments\CanFulfillVendorOrder::class)->check($vendorOrder)->allowed) {
            return;
        }
        $vendorOrder->loadMissing(['vendor.user']);

        if (! $vendorOrder->vendor?->user) {
            return;
        }

        $this->sendUnique->handle($vendorOrder->vendor->user, new VendorOrderCreatedNotification(
            'New seller order',
            "You received seller order {$vendorOrder->vendor_order_number}: ".$vendorOrder->items()->sum('quantity').' items, '.Money::format($vendorOrder->grand_total, $vendorOrder->order->currency).'.',
            route('vendor.orders.show', $vendorOrder, false),
            "vendor-order:{$vendorOrder->id}:created:vendor-user:{$vendorOrder->vendor->user_id}",
            'success',
            true,
            "New order {$vendorOrder->vendor_order_number}",
        ));
    }

    public function vendorOrderStatusChanged(VendorOrder $vendorOrder, VendorOrderStatus $status): void
    {
        $vendorOrder->loadMissing(['order.user', 'vendor.user']);

        if ($status === VendorOrderStatus::Shipped) {
            $this->sendUnique->handle($vendorOrder->order->user, new VendorOrderShippedNotification(
                'Your order is on the way',
                "{$vendorOrder->vendor->display_name} marked seller order {$vendorOrder->vendor_order_number} as shipped.",
                route('account.orders.show', $vendorOrder->order, false),
                "vendor-order:{$vendorOrder->id}:shipped:user:{$vendorOrder->order->user_id}",
                'success',
                true,
                'Your order is on the way',
            ));
        }

        if ($status === VendorOrderStatus::Delivered) {
            $this->sendUnique->handle($vendorOrder->order->user, new VendorOrderDeliveredNotification(
                'Order delivered',
                "{$vendorOrder->vendor->display_name} marked seller order {$vendorOrder->vendor_order_number} as delivered.",
                route('account.orders.show', $vendorOrder->order, false),
                "vendor-order:{$vendorOrder->id}:delivered:user:{$vendorOrder->order->user_id}",
                'success',
                true,
                'Order delivered',
            ));
        }

        if ($status === VendorOrderStatus::Cancelled && $vendorOrder->vendor?->user) {
            $this->sendUnique->handle($vendorOrder->vendor->user, new VendorOrderCancelledNotification(
                'Seller order cancelled',
                "Seller order {$vendorOrder->vendor_order_number} was cancelled.",
                route('vendor.orders.show', $vendorOrder, false),
                "vendor-order:{$vendorOrder->id}:cancelled:vendor-user:{$vendorOrder->vendor->user_id}",
                'warning',
            ));
        }
    }

    public function orderCancelled(Order $order): void
    {
        $order->loadMissing(['user', 'vendorOrders.vendor.user']);

        $this->sendUnique->handle($order->user, new OrderCancelledNotification(
            'Order cancelled',
            "Order {$order->order_number} was cancelled.",
            route('account.orders.show', $order, false),
            "order:{$order->id}:cancelled:user:{$order->user_id}",
            'warning',
            true,
            "Order {$order->order_number} was cancelled",
        ));

        foreach ($order->vendorOrders as $vendorOrder) {
            $this->vendorOrderStatusChanged($vendorOrder, VendorOrderStatus::Cancelled);
        }
    }

    public function refundChanged(Refund $refund, RefundStatus $status): void
    {
        if ($refund->fresh()->status !== $status) return;
        $refund->loadMissing(['order.user', 'allocations.vendorOrder.vendor.user']);

        if ($status === RefundStatus::Succeeded) {
            $destinations = $refund->fundingAllocations->map(fn ($allocation) => $allocation->source_type->label())->unique()->implode(', ');
            $this->sendUnique->handle($refund->order->user, new RefundSucceededNotification(
                'Refund completed',
                "Refund {$refund->refund_number} for order {$refund->order->order_number} was completed for ".Money::format($refund->amount, $refund->currency).'. Destination: '.($destinations ?: 'Original payment method').'.',
                route('account.orders.show', $refund->order, false),
                "refund:{$refund->id}:succeeded:user:{$refund->order->user_id}",
                'success',
                true,
                "Refund {$refund->refund_number} completed",
            ));
        }

        if ($status === RefundStatus::Failed) {
            $this->sendUnique->handle($refund->order->user, new RefundFailedNotification(
                'Refund not completed',
                "Refund {$refund->refund_number} could not be completed.",
                route('account.orders.show', $refund->order, false),
                "refund:{$refund->id}:failed:user:{$refund->order->user_id}",
                'warning',
                true,
                "Refund {$refund->refund_number} needs attention",
            ));
        }

        if ($status !== RefundStatus::Succeeded) {
            return;
        }

        $refund->allocations
            ->pluck('vendorOrder')
            ->filter()
            ->unique('id')
            ->each(function (VendorOrder $vendorOrder) use ($refund): void {
                $user = $vendorOrder->vendor?->user;

                if (! $user) {
                    return;
                }

                $this->sendUnique->handle($user, new VendorRefundNotification(
                    'Refund affects your seller order',
                    "Refund {$refund->refund_number} includes seller order {$vendorOrder->vendor_order_number}. Seller adjustment: ".Money::format((int) $refund->allocations->where('vendor_order_id',$vendorOrder->id)->sum(fn ($a) => $a->seller_amount ?? $a->amount),$refund->currency).'.',
                    route('vendor.orders.show', $vendorOrder, false),
                    "refund:{$refund->id}:vendor-order:{$vendorOrder->id}:vendor-user:{$user->id}",
                    'warning',
                ));
            });
    }

    public function payoutRequested(VendorPayout $payout): void
    {
        $payout->loadMissing('vendor.user');

        if ($payout->vendor?->user) {
            $this->sendUnique->handle($payout->vendor->user, new PayoutRequestedNotification(
                'Payout request received',
                "Payout {$payout->payout_number} for ".Money::format($payout->amount, $payout->currency).' is pending review.',
                route('vendor.payouts.show', $payout, false),
                "payout:{$payout->id}:requested:vendor-user:{$payout->vendor->user_id}",
                'info',
            ));
        }

        foreach ($this->admins->recipients() as $admin) {
            $this->sendUnique->handle($admin, new PayoutRequestedNotification(
                'New payout request',
                "{$payout->vendor->display_name} requested payout {$payout->payout_number}.",
                route('admin.payouts.show', $payout, false),
                "payout:{$payout->id}:requested:admin:{$admin->id}",
                'warning',
            ));
        }
    }

    public function payoutStatusChanged(VendorPayout $payout, VendorPayoutStatus $status): void
    {
        $payout->loadMissing('vendor.user');
        $user = $payout->vendor?->user;

        if (! $user) {
            return;
        }

        $notification = match ($status) {
            VendorPayoutStatus::Approved => new PayoutApprovedNotification(
                'Payout approved',
                "Payout {$payout->payout_number} was approved.",
                route('vendor.payouts.show', $payout, false),
                "payout:{$payout->id}:approved:vendor-user:{$user->id}",
                'success',
            ),
            VendorPayoutStatus::Processing => new PayoutProcessingNotification(
                'Payout processing',
                "Payout {$payout->payout_number} is being processed.",
                route('vendor.payouts.show', $payout, false),
                "payout:{$payout->id}:processing:vendor-user:{$user->id}",
                'info',
            ),
            VendorPayoutStatus::Rejected => new PayoutRejectedNotification(
                'Payout rejected',
                "Payout {$payout->payout_number} was rejected.",
                route('vendor.payouts.show', $payout, false),
                "payout:{$payout->id}:rejected:vendor-user:{$user->id}",
                'warning',
                true,
                "Payout {$payout->payout_number} was rejected",
            ),
            VendorPayoutStatus::Paid => new PayoutPaidNotification(
                'Payout paid',
                "Payout {$payout->payout_number} for ".Money::format($payout->amount, $payout->currency).' was marked paid.',
                route('vendor.payouts.show', $payout, false),
                "payout:{$payout->id}:paid:vendor-user:{$user->id}",
                'success',
                true,
                "Payout {$payout->payout_number} completed",
            ),
            default => null,
        };

        if ($notification) {
            $this->sendUnique->handle($user, $notification);
        }
    }

    public function vendorApplicationSubmitted(Vendor $vendor): void
    {
        foreach ($this->admins->recipients() as $admin) {
            $this->sendUnique->handle($admin, new VendorApplicationSubmittedNotification(
                'Vendor application submitted',
                "{$vendor->display_name} is awaiting marketplace approval.",
                route('admin.vendors.show', $vendor, false),
                "vendor:{$vendor->id}:submitted:admin:{$admin->id}",
                'warning',
            ));
        }
    }

    public function vendorStatusChanged(Vendor $vendor, string $status): void
    {
        $vendor->loadMissing('user');

        if (! $vendor->user) {
            return;
        }

        $notification = match ($status) {
            'approved' => new VendorApprovedNotification('Vendor account approved', 'Your vendor account has been approved.', route('vendor.dashboard', absolute: false), "vendor:{$vendor->id}:approved:user:{$vendor->user_id}", 'success', true),
            'rejected' => new VendorRejectedNotification('Vendor application rejected', 'Your vendor application was not approved. Review the application status page for details.', route('vendor.application-status', absolute: false), "vendor:{$vendor->id}:rejected:user:{$vendor->user_id}", 'warning', true),
            'suspended' => new VendorSuspendedNotification('Vendor account suspended', 'Your vendor account has been suspended.', route('vendor.application-status', absolute: false), "vendor:{$vendor->id}:suspended:user:{$vendor->user_id}", 'warning', true),
            'reactivated' => new VendorReactivatedNotification('Vendor account reactivated', 'Your vendor account has been reactivated.', route('vendor.dashboard', absolute: false), "vendor:{$vendor->id}:reactivated:user:{$vendor->user_id}", 'success', true),
            default => null,
        };

        if ($notification) {
            $this->sendUnique->handle($vendor->user, $notification);
        }
    }

    public function productSubmitted(Product $product): void
    {
        $product->loadMissing('vendor');

        foreach ($this->admins->recipients() as $admin) {
            $this->sendUnique->handle($admin, new ProductSubmittedNotification(
                'Product awaiting approval',
                "{$product->name} was submitted by {$product->vendor->display_name}.",
                route('admin.products.show', $product, false),
                "product:{$product->id}:submitted:admin:{$admin->id}",
                'warning',
            ));
        }
    }

    public function productStatusChanged(Product $product, string $status): void
    {
        $product->loadMissing('vendor.user');
        $user = $product->vendor?->user;

        if (! $user) {
            return;
        }

        $notification = match ($status) {
            'approved' => new ProductApprovedNotification('Product approved', "{$product->name} was approved for the marketplace.", route('vendor.products.edit', $product, false), "product:{$product->id}:approved:vendor-user:{$user->id}", 'success'),
            'rejected' => new ProductRejectedNotification('Product rejected', "{$product->name} was rejected. Review the product page for details.", route('vendor.products.edit', $product, false), "product:{$product->id}:rejected:vendor-user:{$user->id}", 'warning'),
            default => null,
        };

        if ($notification) {
            $this->sendUnique->handle($user, $notification);
        }
    }

    public function reviewSubmitted(ProductReview $review): void
    {
        $review->loadMissing('product.vendor.user');
        $user = $review->product?->vendor?->user;

        if (! $user) {
            return;
        }

        $this->sendUnique->handle($user, new ProductReviewSubmittedNotification(
            'New product review',
            "A customer submitted a review for {$review->product->name}.",
            route('vendor.reviews.index', absolute: false),
            "review:{$review->id}:submitted:vendor-user:{$user->id}",
            'info',
        ));
    }

    public function reviewModerated(ProductReview $review): void
    {
        $review->loadMissing('user', 'product');

        if ($review->status === ProductReviewStatus::Approved) {
            $this->sendUnique->handle($review->user, new ReviewApprovedNotification(
                'Review approved',
                "Your review for {$review->product->name} was approved.",
                route('account.reviews.index', absolute: false),
                "review:{$review->id}:approved:user:{$review->user_id}",
                'success',
            ));
        }

        if ($review->status === ProductReviewStatus::Rejected) {
            $this->sendUnique->handle($review->user, new ReviewRejectedNotification(
                'Review rejected',
                "Your review for {$review->product->name} was not approved.",
                route('account.reviews.index', absolute: false),
                "review:{$review->id}:rejected:user:{$review->user_id}",
                'warning',
            ));
        }
    }
}
