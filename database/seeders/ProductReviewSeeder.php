<?php

namespace Database\Seeders;

use App\Enums\ProductReviewStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\VendorOrderStatus;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Reviews\ProductRatingService;
use App\Services\Reviews\ReviewerName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ProductReviewSeeder extends Seeder
{
    public function run(): void
    {
        $items = OrderItem::query()
            ->with(['vendorOrder.order.user', 'product'])
            ->whereNotNull('product_id')
            ->whereHas('vendorOrder', fn ($query) => $query->where('status', VendorOrderStatus::Delivered))
            ->whereHas('vendorOrder.order.user')
            ->orderBy('id')
            ->get()
            ->unique('product_id')
            ->take(4)
            ->values();

        if ($items->count() < 4) {
            $items = $items
                ->merge($this->createDemoReviewItems(4 - $items->count(), $items->pluck('product_id')->all()))
                ->unique('product_id')
                ->take(4)
                ->values();
        }

        $reviews = [
            [ProductReviewStatus::Approved, 5, 'Excellent product quality', 'The product arrived as described and felt reliable after a few days of use.'],
            [ProductReviewStatus::Approved, 4, 'Good value overall', 'Delivery was smooth and the item matched the listing. Packaging could be a little better.'],
            [ProductReviewStatus::Pending, 3, 'Still evaluating it', 'The item arrived safely and I am still checking how well it fits my daily use.'],
            [ProductReviewStatus::Rejected, 2, 'Needs a clearer listing', 'The purchase was delivered, but the listing details should be easier to compare before buying.'],
        ];

        foreach ($items as $index => $item) {
            [$status, $rating, $title, $body] = $reviews[$index];
            $user = $item->vendorOrder->order->user;

            $review = ProductReview::query()->firstOrNew(['order_item_id' => $item->id]);
            $review->forceFill([
                'user_id' => $user->id,
                'product_id' => $item->product_id,
                'rating' => $rating,
                'title' => $title,
                'body' => $body,
                'status' => $status,
                'verified_purchase' => true,
                'reviewer_display_name' => app(ReviewerName::class)->display($user),
                'variant_snapshot' => $item->variant_description,
                'submitted_at' => now()->subDays(5 - $index),
                'approved_at' => $status === ProductReviewStatus::Approved ? now()->subDays(4 - $index) : null,
                'rejected_at' => $status === ProductReviewStatus::Rejected ? now()->subDay() : null,
                'moderated_by' => $status === ProductReviewStatus::Pending ? null : User::query()->where('email', 'admin@example.com')->value('id'),
                'moderation_reason' => $status === ProductReviewStatus::Rejected ? 'Demo moderation feedback for a rejected review.' : null,
            ])->save();
        }

        app(ProductRatingService::class)->rebuild();
    }

    private function createDemoReviewItems(int $needed, array $excludedProductIds): \Illuminate\Support\Collection
    {
        if ($needed <= 0) {
            return collect();
        }

        return Product::query()
            ->publiclyVisible()
            ->with(['vendor', 'variants' => fn ($query) => $query->active()->orderByDesc('is_default')->oldest()])
            ->whereNotIn('id', $excludedProductIds)
            ->oldest('id')
            ->take($needed)
            ->get()
            ->map(fn (Product $product, int $index): OrderItem => $this->createDemoReviewItem($product, $index + 1));
    }

    private function createDemoReviewItem(Product $product, int $index): OrderItem
    {
        $user = User::query()->firstOrCreate(
            ['email' => "review.customer.{$index}@example.com"],
            [
                'name' => "Review Customer {$index}",
                'role' => \App\Enums\UserRole::Customer,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $address = CustomerAddress::query()->firstOrCreate(
            ['user_id' => $user->id, 'label' => 'Review Demo'],
            [
                'first_name' => 'Review',
                'last_name' => "Customer {$index}",
                'phone' => '+1 555 0100',
                'address_line_1' => '100 Review Lane',
                'address_line_2' => null,
                'city' => 'Demo City',
                'state_region' => 'Demo State',
                'postal_code' => '10000',
                'country_code' => 'US',
                'is_default' => true,
            ],
        );

        $variant = $product->variants->first()
            ?? ProductVariant::factory()->default()->create(['product_id' => $product->id]);
        $number = "DEMO-REVIEW-{$index}";
        $placedAt = now()->subDays(8 - $index);

        $order = Order::query()->updateOrCreate(
            ['order_number' => $number],
            [
                'user_id' => $user->id,
                'customer_address_id' => $address->id,
                'idempotency_key' => 'seed-'.$number,
                'status' => OrderStatus::Completed,
                'payment_status' => PaymentStatus::Paid,
                'currency' => 'USD',
                'items_subtotal' => $variant->base_price,
                'shipping_total' => 0,
                'grand_total' => $variant->base_price,
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
                'completed_at' => $placedAt->copy()->addHours(2),
            ],
        );

        $vendorOrder = $order->vendorOrders()->updateOrCreate(
            ['vendor_order_number' => "{$number}-01"],
            [
                'vendor_id' => $product->vendor_id,
                'shipping_method_id' => null,
                'status' => VendorOrderStatus::Delivered,
                'items_subtotal' => $variant->base_price,
                'shipping_total' => 0,
                'grand_total' => $variant->base_price,
                'shipping_method_name' => 'Review demo delivery',
                'shipping_method_code' => 'review-demo',
                'shipping_method_description' => 'Delivered purchase evidence for seeded product reviews.',
                'shipping_min_delivery_days' => 1,
                'shipping_max_delivery_days' => 3,
                'confirmed_at' => $placedAt,
                'processing_at' => $placedAt,
                'ready_to_ship_at' => $placedAt,
                'shipped_at' => $placedAt,
                'delivered_at' => $placedAt->copy()->addDay(),
            ],
        );

        $item = $vendorOrder->items()->updateOrCreate(
            ['product_id' => $product->id, 'product_variant_id' => $variant->id],
            [
                'product_name' => $product->name,
                'variant_description' => $variant->displayName(),
                'sku' => $variant->sku,
                'quantity' => 1,
                'unit_price' => $variant->base_price,
                'line_total' => $variant->base_price,
                'attributes_snapshot' => null,
            ],
        );

        Payment::query()->updateOrCreate(
            ['order_id' => $order->id],
            [
                'method' => PaymentMethod::CashOnDelivery,
                'provider' => PaymentMethod::CashOnDelivery->value,
                'status' => PaymentStatus::Paid,
                'currency' => $order->currency,
                'amount' => $order->grand_total,
                'paid_at' => $placedAt->copy()->addHour(),
            ],
        );

        return $item->load(['vendorOrder.order.user', 'product']);
    }
}
