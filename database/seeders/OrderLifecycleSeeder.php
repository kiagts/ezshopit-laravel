<?php

namespace Database\Seeders;

use App\Enums\InventoryMovementType;
use App\Enums\InventoryReservationStatus;
use App\Enums\OrderCancellationSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductApprovalStatus;
use App\Enums\ProductStatus;
use App\Enums\RefundAllocationType;
use App\Enums\RefundStatus;
use App\Enums\ProductType;
use App\Enums\VendorBusinessType;
use App\Enums\VendorOrderStatus;
use App\Enums\VendorStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CommissionAdjustment;
use App\Models\CustomerAddress;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Models\VendorOrder;
use App\Models\VendorOrderCommission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrderLifecycleSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->deleteDemoOrders();

            $customer = User::query()->where('email', 'customer@example.com')->first();
            $vendor = Vendor::query()->where('slug', 'techverse')->first();
            $secondVendor = $this->secondVendor();
            $address = CustomerAddress::query()->where('user_id', $customer?->id)->where('is_default', true)->first();
            $method = ShippingMethod::query()->where('vendor_id', $vendor?->id)->where('code', 'standard')->first();
            $secondMethod = ShippingMethod::query()->where('vendor_id', $secondVendor?->id)->where('code', 'standard')->first();

            if (! $customer || ! $vendor || ! $secondVendor || ! $address || ! $method || ! $secondMethod) {
                return;
            }

            $variant = ProductVariant::query()->where('sku', 'IPH17-BLK-8-256')->with('inventory')->first();
            $secondVariant = $this->secondVendorVariant($secondVendor);

            if (! $variant?->inventory || ! $secondVariant->inventory) {
                return;
            }

            $this->singleVendorOrder('DEMO-LIFE-PENDING', $customer, $address, $vendor, $method, $variant, VendorOrderStatus::Pending, 1, now()->subDays(6));
            $this->singleVendorOrder('DEMO-LIFE-PROCESSING', $customer, $address, $vendor, $method, $variant, VendorOrderStatus::Processing, 1, now()->subDays(5));
            $this->singleVendorOrder('DEMO-LIFE-READY', $customer, $address, $vendor, $method, $variant, VendorOrderStatus::ReadyToShip, 1, now()->subDays(4));
            $this->singleVendorOrder('DEMO-LIFE-SHIPPED', $customer, $address, $vendor, $method, $variant, VendorOrderStatus::Shipped, 1, now()->subDays(3));
            $this->singleVendorOrder('DEMO-LIFE-DELIVERED', $customer, $address, $vendor, $method, $variant, VendorOrderStatus::Delivered, 1, now()->subDays(2));
            $this->singleVendorOrder('DEMO-LIFE-CANCELLED', $customer, $address, $vendor, $method, $variant, VendorOrderStatus::Cancelled, 1, now()->subDay());
            $this->cardPendingOrder('DEMO-LIFE-CARD-PENDING', $customer, $address, $vendor, $method, $variant);
            $this->cardCancelledOrder('DEMO-LIFE-CARD-EXPIRED', $customer, $address, $vendor, $method, $variant, OrderCancellationSource::PaymentExpired);
            $this->cardCancelledOrder('DEMO-LIFE-CARD-CUSTOMER-CANCELLED', $customer, $address, $vendor, $method, $variant, OrderCancellationSource::Customer);
            $this->mixedVendorOrder($customer, $address, $vendor, $method, $variant, $secondVendor, $secondMethod, $secondVariant);
            $this->refundDemoOrders($customer, $address, $vendor, $method, $variant);
        });
    }

    private function deleteDemoOrders(): void
    {
        $vendorOrderIds = VendorOrder::query()
            ->whereHas('order', fn ($query) => $query->where('order_number', 'like', 'DEMO-LIFE-%'))
            ->pluck('id');

        InventoryMovement::query()
            ->where('reference_type', VendorOrder::class)
            ->whereIn('reference_id', $vendorOrderIds)
            ->delete();

        VendorLedgerEntry::query()
            ->whereIn('vendor_order_id', $vendorOrderIds)
            ->delete();

        $commissionIds = VendorOrderCommission::query()
            ->whereIn('vendor_order_id', $vendorOrderIds)
            ->pluck('id');

        CommissionAdjustment::query()
            ->whereIn('vendor_order_commission_id', $commissionIds)
            ->delete();

        VendorOrderCommission::query()
            ->whereIn('id', $commissionIds)
            ->delete();

        ProductReview::query()
            ->whereHas('orderItem.vendorOrder.order', fn ($query) => $query->where('order_number', 'like', 'DEMO-LIFE-%'))
            ->forceDelete();

        Refund::query()
            ->whereHas('order', fn ($query) => $query->where('order_number', 'like', 'DEMO-LIFE-%'))
            ->delete();

        Order::query()->where('order_number', 'like', 'DEMO-LIFE-%')->delete();
    }

    private function singleVendorOrder(string $number, User $customer, CustomerAddress $address, Vendor $vendor, ShippingMethod $method, ProductVariant $variant, VendorOrderStatus $status, int $quantity, \DateTimeInterface $placedAt): void
    {
        $order = $this->order($number, $customer, $address, $status, $placedAt, $variant->base_price * $quantity, $method->flat_rate);
        $vendorOrder = $this->vendorOrder($order, $vendor, $method, "{$number}-01", $status, $variant->base_price * $quantity, $method->flat_rate);

        $this->item($vendorOrder, $variant, $quantity, $status);
        $this->payment($order);
    }

    private function cardPendingOrder(string $number, User $customer, CustomerAddress $address, Vendor $vendor, ShippingMethod $method, ProductVariant $variant): void
    {
        $order = $this->order($number, $customer, $address, VendorOrderStatus::Pending, now()->subMinutes(10), $variant->base_price, $method->flat_rate);
        $vendorOrder = $this->vendorOrder($order, $vendor, $method, "{$number}-01", VendorOrderStatus::Pending, $variant->base_price, $method->flat_rate);

        $this->item($vendorOrder, $variant, 1, VendorOrderStatus::Pending);
        $this->payment($order, PaymentMethod::Card, PaymentStatus::Pending, now()->addMinutes(20));
    }

    private function cardCancelledOrder(string $number, User $customer, CustomerAddress $address, Vendor $vendor, ShippingMethod $method, ProductVariant $variant, OrderCancellationSource $source): void
    {
        $order = $this->order($number, $customer, $address, VendorOrderStatus::Cancelled, now()->subHours(2), $variant->base_price, $method->flat_rate);
        $order->forceFill([
            'cancellation_source' => $source,
            'cancellation_reason' => $source === OrderCancellationSource::PaymentExpired
                ? 'Online payment was not completed within the allowed payment window.'
                : 'Customer cancelled unpaid online order.',
        ])->save();
        $vendorOrder = $this->vendorOrder($order, $vendor, $method, "{$number}-01", VendorOrderStatus::Cancelled, $variant->base_price, $method->flat_rate);

        $this->item($vendorOrder, $variant, 1, VendorOrderStatus::Cancelled);
        $this->payment($order, PaymentMethod::Card, PaymentStatus::Cancelled, now()->subMinutes(30));
    }

    private function mixedVendorOrder(User $customer, CustomerAddress $address, Vendor $vendor, ShippingMethod $method, ProductVariant $variant, Vendor $secondVendor, ShippingMethod $secondMethod, ProductVariant $secondVariant): void
    {
        $order = $this->order('DEMO-LIFE-MIXED', $customer, $address, OrderStatus::Processing, now()->subHours(18), $variant->base_price + $secondVariant->base_price, $method->flat_rate + $secondMethod->flat_rate);

        $delivered = $this->vendorOrder($order, $vendor, $method, 'DEMO-LIFE-MIXED-01', VendorOrderStatus::Delivered, $variant->base_price, $method->flat_rate);
        $cancelled = $this->vendorOrder($order, $secondVendor, $secondMethod, 'DEMO-LIFE-MIXED-02', VendorOrderStatus::Cancelled, $secondVariant->base_price, $secondMethod->flat_rate);

        $this->item($delivered, $variant, 1, VendorOrderStatus::Delivered);
        $this->item($cancelled, $secondVariant, 1, VendorOrderStatus::Cancelled);
        $this->payment($order);
    }

    private function refundDemoOrders(User $customer, CustomerAddress $address, Vendor $vendor, ShippingMethod $method, ProductVariant $variant): void
    {
        $this->paidRefundDemo('DEMO-LIFE-REFUND-NONE', $customer, $address, $vendor, $method, $variant, null, null);
        $this->paidRefundDemo('DEMO-LIFE-REFUND-PARTIAL', $customer, $address, $vendor, $method, $variant, RefundStatus::Succeeded, $variant->base_price);
        $this->paidRefundDemo('DEMO-LIFE-REFUND-FULL', $customer, $address, $vendor, $method, $variant, RefundStatus::Succeeded, $variant->base_price + $method->flat_rate, full: true);
        $this->paidRefundDemo('DEMO-LIFE-REFUND-PENDING', $customer, $address, $vendor, $method, $variant, RefundStatus::Pending, 1500);
        $this->paidRefundDemo('DEMO-LIFE-REFUND-FAILED', $customer, $address, $vendor, $method, $variant, RefundStatus::Failed, 1500);

        $order = $this->order('DEMO-LIFE-LATE-REFUNDED', $customer, $address, OrderStatus::Cancelled, now()->subHours(6), $variant->base_price, $method->flat_rate);
        $order->forceFill([
            'payment_status' => PaymentStatus::Refunded,
            'cancellation_source' => OrderCancellationSource::PaymentExpired,
            'cancellation_reason' => 'Online payment expired, then Stripe reported a late success.',
            'cancelled_at' => now()->subHours(5),
        ])->save();
        $vendorOrder = $this->vendorOrder($order, $vendor, $method, 'DEMO-LIFE-LATE-REFUNDED-01', VendorOrderStatus::Cancelled, $variant->base_price, $method->flat_rate);
        $this->item($vendorOrder, $variant, 1, VendorOrderStatus::Cancelled);
        $payment = $this->payment($order, PaymentMethod::Card, PaymentStatus::Refunded, now()->subHours(5));
        $payment->forceFill([
            'provider_reference' => 'pi_demo_late_refunded',
            'paid_at' => now()->subHours(4),
            'requires_review_at' => now()->subHours(4),
            'review_reason' => 'Payment succeeded after order cancellation. Refund review is required.',
            'review_resolved_at' => now()->subHours(3),
        ])->save();
        $this->refund($order, $payment, RefundStatus::Succeeded, $payment->amount, 'Late payment after cancellation', [['type' => RefundAllocationType::Adjustment, 'amount' => $payment->amount]]);
    }

    private function paidRefundDemo(string $number, User $customer, CustomerAddress $address, Vendor $vendor, ShippingMethod $method, ProductVariant $variant, ?RefundStatus $refundStatus, ?int $refundAmount, bool $full = false): void
    {
        $order = $this->order($number, $customer, $address, OrderStatus::Completed, now()->subDays(1), $variant->base_price, $method->flat_rate);
        $order->forceFill(['payment_status' => $refundStatus === RefundStatus::Succeeded && $full ? PaymentStatus::Refunded : ($refundStatus === RefundStatus::Succeeded ? PaymentStatus::PartiallyRefunded : PaymentStatus::Paid)])->save();
        $vendorOrder = $this->vendorOrder($order, $vendor, $method, "{$number}-01", VendorOrderStatus::Delivered, $variant->base_price, $method->flat_rate);
        $this->item($vendorOrder, $variant, 1, VendorOrderStatus::Delivered);
        $payment = $this->payment($order, PaymentMethod::Card, $order->payment_status);
        $payment->forceFill([
            'provider_reference' => 'pi_demo_'.str($number)->lower()->replace('-', '_'),
            'paid_at' => now()->subDay(),
        ])->save();

        if (! $refundStatus || ! $refundAmount) {
            return;
        }

        $allocations = $full
            ? [['type' => RefundAllocationType::Adjustment, 'amount' => $refundAmount]]
            : [['type' => RefundAllocationType::Item, 'amount' => $refundAmount, 'quantity' => 1, 'order_item_id' => $vendorOrder->items()->first()->id, 'vendor_order_id' => $vendorOrder->id]];

        $this->refund($order, $payment, $refundStatus, $refundAmount, match ($refundStatus) {
            RefundStatus::Pending => 'Pending provider refund',
            RefundStatus::Failed => 'Failed provider refund',
            default => $full ? 'Full customer service refund' : 'Partial item refund',
        }, $allocations);
    }

    private function refund(Order $order, \App\Models\Payment $payment, RefundStatus $status, int $amount, string $reason, array $allocations): void
    {
        $refund = $payment->refunds()->create([
            'refund_number' => 'REF-'.$order->order_number,
            'order_id' => $order->id,
            'status' => $status,
            'amount' => $amount,
            'currency' => $payment->currency,
            'reason' => $reason,
            'provider' => $payment->provider,
            'provider_reference' => $status === RefundStatus::Succeeded ? 're_demo_'.$order->id : null,
            'requested_by' => User::query()->where('email', 'admin@example.com')->value('id'),
            'requested_at' => now()->subHours(3),
            'processed_at' => $status === RefundStatus::Succeeded ? now()->subHours(2) : null,
            'failed_at' => $status === RefundStatus::Failed ? now()->subHours(2) : null,
            'failure_code' => $status === RefundStatus::Failed ? 'demo_failure' : null,
            'failure_message' => $status === RefundStatus::Failed ? 'Synthetic failed refund for demo review.' : null,
        ]);

        foreach ($allocations as $allocation) {
            $refund->allocations()->create($allocation);
        }
    }

    private function order(string $number, User $customer, CustomerAddress $address, VendorOrderStatus|OrderStatus $status, \DateTimeInterface $placedAt, int $itemsSubtotal, int $shippingTotal): Order
    {
        $orderStatus = $status instanceof OrderStatus ? $status : $this->orderStatusFor($status);

        return $customer->orders()->create([
            'order_number' => $number,
            'customer_address_id' => $address->id,
            'idempotency_key' => 'seed-'.$number,
            'status' => $orderStatus,
            'payment_status' => PaymentStatus::Pending,
            'currency' => 'USD',
            'items_subtotal' => $itemsSubtotal,
            'shipping_total' => $shippingTotal,
            'grand_total' => $itemsSubtotal + $shippingTotal,
            'shipping_first_name' => $address->first_name,
            'shipping_last_name' => $address->last_name,
            'shipping_phone' => $address->phone,
            'shipping_address_line_1' => $address->address_line_1,
            'shipping_address_line_2' => $address->address_line_2,
            'shipping_city' => $address->city,
            'shipping_state_region' => $address->state_region,
            'shipping_postal_code' => $address->postal_code,
            'shipping_country_code' => $address->country_code,
            'placed_at' => $placedAt,
            'completed_at' => $orderStatus === OrderStatus::Completed ? now() : null,
            'cancelled_at' => $orderStatus === OrderStatus::Cancelled ? now() : null,
        ]);
    }

    private function vendorOrder(Order $order, Vendor $vendor, ShippingMethod $method, string $number, VendorOrderStatus $status, int $subtotal, int $shipping): VendorOrder
    {
        return $order->vendorOrders()->create([
            'vendor_id' => $vendor->id,
            'shipping_method_id' => $method->id,
            'vendor_order_number' => $number,
            'status' => $status,
            'items_subtotal' => $subtotal,
            'shipping_total' => $shipping,
            'grand_total' => $subtotal + $shipping,
            'shipping_method_name' => $method->name,
            'shipping_method_code' => $method->code,
            'shipping_method_description' => $method->description,
            'shipping_min_delivery_days' => $method->min_delivery_days,
            'shipping_max_delivery_days' => $method->max_delivery_days,
            'confirmed_at' => in_array($status, [VendorOrderStatus::Confirmed, VendorOrderStatus::Processing, VendorOrderStatus::ReadyToShip, VendorOrderStatus::Shipped, VendorOrderStatus::Delivered], true) ? now()->subHours(12) : null,
            'processing_at' => in_array($status, [VendorOrderStatus::Processing, VendorOrderStatus::ReadyToShip, VendorOrderStatus::Shipped, VendorOrderStatus::Delivered], true) ? now()->subHours(10) : null,
            'ready_to_ship_at' => in_array($status, [VendorOrderStatus::ReadyToShip, VendorOrderStatus::Shipped, VendorOrderStatus::Delivered], true) ? now()->subHours(8) : null,
            'shipped_at' => in_array($status, [VendorOrderStatus::Shipped, VendorOrderStatus::Delivered], true) ? now()->subHours(6) : null,
            'delivered_at' => $status === VendorOrderStatus::Delivered ? now()->subHours(2) : null,
            'cancelled_at' => $status === VendorOrderStatus::Cancelled ? now()->subHours(4) : null,
            'cancelled_by' => $status === VendorOrderStatus::Cancelled ? $vendor->user_id : null,
            'cancellation_reason' => $status === VendorOrderStatus::Cancelled ? 'Seeded vendor cancellation: unable to fulfill.' : null,
        ]);
    }

    private function item(VendorOrder $vendorOrder, ProductVariant $variant, int $quantity, VendorOrderStatus $status): void
    {
        $item = $vendorOrder->items()->create([
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'product_name' => $variant->product->name,
            'variant_description' => $variant->displayName(),
            'sku' => $variant->sku,
            'quantity' => $quantity,
            'unit_price' => $variant->base_price,
            'line_total' => $variant->base_price * $quantity,
            'attributes_snapshot' => null,
        ]);

        $inventory = $variant->inventory()->lockForUpdate()->firstOrFail();

        if (in_array($status, [VendorOrderStatus::Shipped, VendorOrderStatus::Delivered], true)) {
            $before = $inventory->on_hand;
            $inventory->forceFill(['on_hand' => $before - $quantity])->save();
            $inventory->movements()->create([
                'product_variant_id' => $variant->id,
                'type' => InventoryMovementType::OrderFulfillment,
                'quantity' => -$quantity,
                'before_quantity' => $before,
                'after_quantity' => $before - $quantity,
                'reason' => "Order {$vendorOrder->order->order_number} / Vendor Order {$vendorOrder->vendor_order_number} shipped",
                'reference_type' => VendorOrder::class,
                'reference_id' => $vendorOrder->id,
                'created_by' => $vendorOrder->vendor->user_id,
            ]);
        } elseif ($status !== VendorOrderStatus::Cancelled) {
            $inventory->increment('reserved', $quantity);
        }

        $item->reservation()->create([
            'inventory_id' => $inventory->id,
            'quantity' => $quantity,
            'status' => match (true) {
                in_array($status, [VendorOrderStatus::Shipped, VendorOrderStatus::Delivered], true) => InventoryReservationStatus::Committed,
                $status === VendorOrderStatus::Cancelled => InventoryReservationStatus::Released,
                default => InventoryReservationStatus::Active,
            },
        ]);
    }

    private function payment(Order $order, PaymentMethod $method = PaymentMethod::CashOnDelivery, PaymentStatus $status = PaymentStatus::Pending, ?\DateTimeInterface $expiresAt = null): \App\Models\Payment
    {
        return $order->payment()->create([
            'method' => $method,
            'provider' => $method === PaymentMethod::Card ? 'stripe' : PaymentMethod::CashOnDelivery->value,
            'status' => $status,
            'currency' => $order->currency,
            'amount' => $order->grand_total,
            'paid_at' => in_array($status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded, PaymentStatus::Refunded], true) ? now()->subDay() : null,
            'cancelled_at' => $status === PaymentStatus::Cancelled ? now()->subHour() : null,
            'expires_at' => $expiresAt,
        ]);
    }

    private function orderStatusFor(VendorOrderStatus $status): OrderStatus
    {
        return match ($status) {
            VendorOrderStatus::Pending => OrderStatus::Pending,
            VendorOrderStatus::Confirmed => OrderStatus::Confirmed,
            VendorOrderStatus::Delivered => OrderStatus::Completed,
            VendorOrderStatus::Cancelled => OrderStatus::Cancelled,
            default => OrderStatus::Processing,
        };
    }

    private function secondVendor(): Vendor
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'lifecycle.vendor@example.com'],
            [
                'name' => 'Lifecycle Vendor',
                'role' => \App\Enums\UserRole::Vendor,
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ],
        );

        $vendor = Vendor::query()->firstOrNew(['user_id' => $user->id]);
        $vendor->fill([
            'business_name' => 'Lifecycle Demo Goods Ltd',
            'display_name' => 'Lifecycle Goods',
            'slug' => 'lifecycle-goods',
            'email' => $user->email,
            'phone' => '+92 300 7654321',
            'business_type' => VendorBusinessType::Company,
            'description' => 'A second approved seller used for multi-vendor fulfillment demos.',
            'country' => 'Pakistan',
            'city' => 'Karachi',
            'address_line_1' => 'Lifecycle Market',
            'postal_code' => '74000',
        ]);
        $vendor->forceFill(['status' => VendorStatus::Approved, 'submitted_at' => now()->subDays(3), 'approved_at' => now()->subDays(2)]);
        $vendor->save();

        ShippingMethod::query()->updateOrCreate(
            ['vendor_id' => $vendor->id, 'code' => 'standard'],
            [
                'name' => 'Standard Delivery',
                'description' => 'Seeded lifecycle seller delivery.',
                'type' => \App\Enums\ShippingMethodType::FlatRate,
                'flat_rate' => 500,
                'free_shipping_threshold' => null,
                'min_delivery_days' => 3,
                'max_delivery_days' => 5,
                'ships_to_all_supported_countries' => true,
                'is_active' => true,
                'sort_order' => 10,
            ],
        );

        return $vendor;
    }

    private function secondVendorVariant(Vendor $vendor): ProductVariant
    {
        $product = Product::query()->updateOrCreate(
            ['slug' => 'lifecycle-demo-bag'],
            [
                'vendor_id' => $vendor->id,
                'category_id' => Category::query()->value('id'),
                'brand_id' => Brand::query()->value('id'),
                'name' => 'Lifecycle Demo Bag',
                'type' => ProductType::Simple,
                'status' => ProductStatus::Active,
                'approval_status' => ProductApprovalStatus::Approved,
                'short_description' => 'Demo seller item.',
                'description' => 'A deterministic item used for lifecycle seeding.',
                'submitted_at' => now()->subDays(2),
                'approved_at' => now()->subDay(),
                'published_at' => now()->subDay(),
            ],
        );

        $variant = $product->variants()->updateOrCreate(
            ['sku' => 'LIFE-BAG-STD'],
            [
                'name' => null,
                'base_price' => 4500,
                'sale_price' => null,
                'is_active' => true,
                'is_default' => true,
                'combination_key' => 'default',
                'sort_order' => 10,
            ],
        );

        Inventory::query()->updateOrCreate(
            ['product_variant_id' => $variant->id],
            ['on_hand' => 8, 'reserved' => 0, 'low_stock_threshold' => 2],
        );

        return $variant->load(['inventory', 'product']);
    }
}
