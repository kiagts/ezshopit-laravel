<?php

namespace Tests\Feature\Reviews;

use App\Actions\Reviews\ApproveProductReview;
use App\Actions\Reviews\RejectProductReview;
use App\Actions\Reviews\UpdateProductReview;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductApprovalStatus;
use App\Enums\ProductReviewStatus;
use App\Enums\ProductStatus;
use App\Enums\RefundStatus;
use App\Enums\VendorOrderStatus;
use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Models\VendorOrder;
use App\Services\Reviews\ProductRatingService;
use App\Services\Reviews\ReviewEligibilityService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_customer_owned_delivered_order_items_are_reviewable_and_refunds_do_not_remove_eligibility(): void
    {
        $customer = User::factory()->customer()->create();
        $delivered = $this->orderItem($customer, VendorOrderStatus::Delivered);
        $statuses = [
            VendorOrderStatus::Pending,
            VendorOrderStatus::Confirmed,
            VendorOrderStatus::Processing,
            VendorOrderStatus::Shipped,
            VendorOrderStatus::Cancelled,
        ];

        $eligibility = app(ReviewEligibilityService::class);

        $this->assertTrue($eligibility->canReview($customer, $delivered));

        foreach ($statuses as $status) {
            $this->assertFalse($eligibility->canReview($customer, $this->orderItem($customer, $status)));
        }

        $payment = Payment::factory()->create([
            'order_id' => $delivered->vendorOrder->order_id,
            'status' => PaymentStatus::Refunded,
            'amount' => $delivered->line_total,
            'currency' => 'USD',
        ]);
        Refund::factory()->create([
            'order_id' => $delivered->vendorOrder->order_id,
            'payment_id' => $payment->id,
            'status' => RefundStatus::Succeeded,
            'amount' => $delivered->line_total,
            'currency' => 'USD',
        ]);

        $this->assertTrue($eligibility->canReview($customer, $delivered->refresh()));
        $this->assertFalse($eligibility->canReview(User::factory()->customer()->create(), $delivered));
    }

    public function test_customer_can_create_pending_verified_review_and_cannot_inject_moderation_fields(): void
    {
        $customer = User::factory()->customer()->create(['name' => 'Sarah Khan', 'email' => 'private@example.com']);
        $item = $this->orderItem($customer, VendorOrderStatus::Delivered, variant: 'Black / 256 GB');

        $this->actingAs($customer)
            ->post(route('account.order-items.reviews.store', $item), [
                'rating' => 5,
                'title' => 'Loved it',
                'body' => 'This product arrived safely and matched the description.',
                'status' => ProductReviewStatus::Approved->value,
                'verified_purchase' => false,
                'approved_at' => now()->toDateTimeString(),
            ])
            ->assertRedirect();

        $review = ProductReview::query()->firstOrFail();

        $this->assertSame(ProductReviewStatus::Pending, $review->status);
        $this->assertTrue($review->verified_purchase);
        $this->assertSame('Sarah K.', $review->reviewer_display_name);
        $this->assertSame('Black / 256 GB', $review->variant_snapshot);
        $this->assertSame(0, $item->product->refresh()->rating_count);
    }

    public function test_duplicate_product_review_redirects_to_edit_even_after_repeat_purchase(): void
    {
        $customer = User::factory()->customer()->create();
        $product = Product::factory()->approved()->create();
        $first = $this->orderItem($customer, VendorOrderStatus::Delivered, product: $product);
        $second = $this->orderItem($customer, VendorOrderStatus::Delivered, product: $product);
        $review = ProductReview::factory()->create([
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'order_item_id' => $first->id,
            'status' => ProductReviewStatus::Pending,
        ]);

        $this->actingAs($customer)
            ->get(route('account.order-items.reviews.create', $second))
            ->assertRedirect(route('account.reviews.edit', $review));
    }

    public function test_review_validation_and_wrong_roles_or_customers_are_blocked(): void
    {
        $customer = User::factory()->customer()->create();
        $item = $this->orderItem($customer, VendorOrderStatus::Delivered);

        foreach ([0, 6, 1.5, 'bad'] as $rating) {
            $this->actingAs($customer)
                ->post(route('account.order-items.reviews.store', $item), [
                    'rating' => $rating,
                    'body' => 'Valid body content for validation.',
                ])
                ->assertSessionHasErrors('rating');
        }

        $this->actingAs($customer)
            ->post(route('account.order-items.reviews.store', $item), ['rating' => 5, 'body' => 'short'])
            ->assertSessionHasErrors('body');

        $this->actingAs(User::factory()->customer()->create())
            ->post(route('account.order-items.reviews.store', $item), ['rating' => 5, 'body' => 'This should not be accepted.'])
            ->assertNotFound();

        $this->actingAs(User::factory()->vendor()->create())
            ->post(route('account.order-items.reviews.store', $item), ['rating' => 5, 'body' => 'This should not be accepted.'])
            ->assertForbidden();
    }

    public function test_admin_approval_and_rejection_update_rating_aggregate_idempotently(): void
    {
        $admin = User::factory()->admin()->create();
        $review = $this->review(ProductReviewStatus::Pending, rating: 5);
        $product = $review->product;

        app(ApproveProductReview::class)->handle($review, $admin);
        app(ApproveProductReview::class)->handle($review->refresh(), $admin);

        $this->assertSame(5, $product->refresh()->rating_sum);
        $this->assertSame(1, $product->rating_count);

        app(RejectProductReview::class)->handle($review->refresh(), $admin, 'Needs revision.');
        app(RejectProductReview::class)->handle($review->refresh(), $admin, 'Still rejected.');

        $this->assertSame(0, $product->refresh()->rating_sum);
        $this->assertSame(0, $product->rating_count);
    }

    public function test_customer_edit_resubmits_and_removes_approved_contribution_then_reapprove_counts_new_rating(): void
    {
        $admin = User::factory()->admin()->create();
        $review = $this->review(ProductReviewStatus::Approved, rating: 5);
        app(ProductRatingService::class)->rebuild($review->product);

        app(UpdateProductReview::class)->handle($review, [
            'rating' => 3,
            'title' => 'Updated',
            'body' => 'Updated review body after more product usage.',
        ]);

        $this->assertSame(ProductReviewStatus::Pending, $review->refresh()->status);
        $this->assertSame(0, $review->product->refresh()->rating_count);

        app(ApproveProductReview::class)->handle($review, $admin);

        $this->assertSame(3, $review->product->refresh()->rating_sum);
        $this->assertSame(1, $review->product->rating_count);
    }

    public function test_average_distribution_zero_reviews_and_rebuild_are_correct(): void
    {
        $product = Product::factory()->approved()->create(['rating_sum' => 99, 'rating_count' => 99]);
        $this->review(ProductReviewStatus::Approved, 5, $product);
        $this->review(ProductReviewStatus::Approved, 4, $product);
        $this->review(ProductReviewStatus::Approved, 3, $product);
        $this->review(ProductReviewStatus::Pending, 1, $product);

        app(ProductRatingService::class)->rebuild($product);
        app(ProductRatingService::class)->rebuild($product);

        $product->refresh();
        $distribution = app(ProductRatingService::class)->distribution($product);

        $this->assertSame(12, $product->rating_sum);
        $this->assertSame(3, $product->rating_count);
        $this->assertSame(4.0, $product->averageRating());
        $this->assertSame(1, $distribution[5]['count']);
        $this->assertSame(33, $distribution[5]['percentage']);
        $this->assertNull(Product::factory()->create()->averageRating());
    }

    public function test_public_product_page_exposes_only_approved_reviews_and_protects_customer_privacy(): void
    {
        $product = $this->publicProduct();
        $approved = $this->review(ProductReviewStatus::Approved, 5, $product, body: '<strong>Great product</strong> and safe text.');
        $pending = $this->review(ProductReviewStatus::Pending, 1, $product, body: 'Pending text should stay hidden.');
        app(ProductRatingService::class)->rebuild($product);

        $response = $this->get(route('storefront.products.show', $product));

        $response->assertOk()
            ->assertSeeText('Verified Product Reviews')
            ->assertSeeText('Great product')
            ->assertSeeText('Verified Purchase')
            ->assertSeeText($approved->variant_snapshot)
            ->assertDontSeeText('Pending text should stay hidden')
            ->assertDontSee($approved->user->email)
            ->assertDontSee($approved->orderItem->vendorOrder->order->order_number)
            ->assertSee('&lt;strong&gt;Great product&lt;/strong&gt;', false)
            ->assertSee('AggregateRating');

        $this->assertSame(ProductReviewStatus::Pending, $pending->refresh()->status);
    }

    public function test_product_card_displays_only_real_approved_rating(): void
    {
        $product = Product::factory()->approved()->create(['rating_sum' => 9, 'rating_count' => 2]);
        $empty = Product::factory()->approved()->create(['rating_sum' => 0, 'rating_count' => 0]);

        $this->blade('<x-storefront.product-card :product="$product" />', ['product' => $product])
            ->assertSee('4.5');
        $this->blade('<x-storefront.product-card :product="$product" />', ['product' => $empty])
            ->assertDontSee('5.0')
            ->assertDontSee('0.0');
    }

    public function test_vendor_review_list_is_scoped_and_vendor_cannot_moderate(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $otherVendor = Vendor::factory()->approved()->create();
        $ownReview = $this->review(ProductReviewStatus::Approved, 5, Product::factory()->approved()->create(['vendor_id' => $vendor->id]));
        $otherReview = $this->review(ProductReviewStatus::Approved, 1, Product::factory()->approved()->create(['vendor_id' => $otherVendor->id]));

        $this->actingAs($vendor->user)
            ->get(route('vendor.reviews.index'))
            ->assertOk()
            ->assertSeeText($ownReview->title)
            ->assertDontSeeText($otherReview->title);

        $this->actingAs($vendor->user)
            ->post(route('admin.reviews.approve', $ownReview))
            ->assertForbidden();
    }

    public function test_admin_can_moderate_and_rejection_reason_is_required(): void
    {
        $admin = User::factory()->admin()->create();
        $review = $this->review(ProductReviewStatus::Pending, 4);

        $this->actingAs($admin)
            ->get(route('admin.reviews.show', $review))
            ->assertOk()
            ->assertSeeText($review->title);

        $this->actingAs($admin)
            ->post(route('admin.reviews.reject', $review), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)
            ->post(route('admin.reviews.approve', $review))
            ->assertRedirect();

        $this->assertSame(ProductReviewStatus::Approved, $review->refresh()->status);
    }

    public function test_reviews_do_not_mutate_product_approval_or_financial_inventory_state(): void
    {
        $review = $this->review(ProductReviewStatus::Pending, 4);
        $product = $review->product;
        $admin = User::factory()->admin()->create();
        $before = [
            'payments' => Payment::query()->count(),
            'refunds' => Refund::query()->count(),
            'ledger' => VendorLedgerEntry::query()->count(),
        ];

        app(ApproveProductReview::class)->handle($review, $admin);
        app(RejectProductReview::class)->handle($review->refresh(), $admin, 'No longer suitable.');

        $this->assertSame(ProductApprovalStatus::Approved, $product->refresh()->approval_status);
        $this->assertSame(ProductStatus::Active, $product->status);
        $this->assertSame($before['payments'], Payment::query()->count());
        $this->assertSame($before['refunds'], Refund::query()->count());
        $this->assertSame($before['ledger'], VendorLedgerEntry::query()->count());
    }

    public function test_seeded_reviews_are_repeatable_and_aggregate_consistent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThanOrEqual(4, ProductReview::query()->count());

        Product::query()->where('rating_count', '>', 0)->get()->each(function (Product $product): void {
            $approved = ProductReview::query()
                ->where('product_id', $product->id)
                ->where('status', ProductReviewStatus::Approved)
                ->selectRaw('COALESCE(SUM(rating), 0) as rating_sum, COUNT(*) as rating_count')
                ->first();

            $this->assertSame((int) $approved->rating_sum, $product->rating_sum);
            $this->assertSame((int) $approved->rating_count, $product->rating_count);
        });
    }

    private function review(ProductReviewStatus $status, int $rating = 5, ?Product $product = null, string $body = 'This verified review has enough detail for validation.'): ProductReview
    {
        $customer = User::factory()->customer()->create(['name' => 'Review Customer', 'email' => 'reviewer@example'.$rating.uniqid().'.test']);
        $item = $this->orderItem($customer, VendorOrderStatus::Delivered, $product, 'Snapshot Variant');

        return ProductReview::query()->forceCreate([
            'user_id' => $customer->id,
            'product_id' => $item->product_id,
            'order_item_id' => $item->id,
            'rating' => $rating,
            'title' => 'Seeded Review '.$rating,
            'body' => $body,
            'status' => $status,
            'verified_purchase' => true,
            'reviewer_display_name' => 'Review C.',
            'variant_snapshot' => $item->variant_description,
            'submitted_at' => now(),
            'approved_at' => $status === ProductReviewStatus::Approved ? now() : null,
            'rejected_at' => $status === ProductReviewStatus::Rejected ? now() : null,
            'moderation_reason' => $status === ProductReviewStatus::Rejected ? 'Needs revision.' : null,
        ]);
    }

    private function orderItem(User $customer, VendorOrderStatus $status, ?Product $product = null, ?string $variant = null): OrderItem
    {
        $product ??= Product::factory()->approved()->create();
        $address = CustomerAddress::factory()->default()->create(['user_id' => $customer->id]);
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'customer_address_id' => $address->id,
            'status' => $status === VendorOrderStatus::Delivered ? OrderStatus::Completed : OrderStatus::Processing,
            'payment_status' => PaymentStatus::Paid,
        ]);
        $vendorOrder = VendorOrder::factory()->create([
            'order_id' => $order->id,
            'vendor_id' => $product->vendor_id,
            'status' => $status,
            'delivered_at' => $status === VendorOrderStatus::Delivered ? now()->subDay() : null,
            'cancelled_at' => $status === VendorOrderStatus::Cancelled ? now()->subDay() : null,
        ]);
        $productVariant = $product->variants()->where('is_default', true)->first()
            ?? ProductVariant::factory()->default()->create(['product_id' => $product->id]);

        return OrderItem::factory()->create([
            'vendor_order_id' => $vendorOrder->id,
            'product_id' => $product->id,
            'product_variant_id' => $productVariant->id,
            'product_name' => $product->name,
            'variant_description' => $variant,
        ])->load(['vendorOrder.order', 'product']);
    }

    private function publicProduct(): Product
    {
        $category = Category::factory()->create(['is_active' => true]);

        return Product::factory()->approved()->create([
            'category_id' => $category->id,
        ]);
    }
}
