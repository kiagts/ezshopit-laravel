<?php

namespace Tests\Feature\Promotions;

use App\Actions\Commissions\RecognizeVendorOrderCommission;
use App\Enums\InventoryReservationStatus;
use App\Enums\ProductApprovalStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\PromotionDiscountType;
use App\Enums\PromotionFundingSource;
use App\Enums\VendorLedgerEntryType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\CommissionRule;
use App\Models\CustomerAddress;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderDiscountAllocation;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Services\Cart\CartResolver;
use App\Services\Checkout\CheckoutSession;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_apply_valid_coupon_and_invalid_replacement_keeps_existing_coupon(): void
    {
        [$variant] = $this->catalog();
        $promotion = Promotion::factory()->create(['code' => 'SAVE10']);
        $cart = Cart::factory()->create(['token' => 'promo-guest']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => 1]);

        $this->withSession([CartResolver::SESSION_KEY => 'promo-guest'])
            ->post(route('cart.promotion.store'), ['code' => ' save10 '])
            ->assertRedirect()
            ->assertSessionHas('status', 'Coupon applied.');

        $this->assertSame($promotion->id, $cart->refresh()->promotion_id);

        $this->withSession([CartResolver::SESSION_KEY => 'promo-guest'])
            ->post(route('cart.promotion.store'), ['code' => 'missing'])
            ->assertSessionHasErrors('code');

        $this->assertSame($promotion->id, $cart->refresh()->promotion_id);

        $this->withSession([CartResolver::SESSION_KEY => 'promo-guest'])
            ->get(route('cart.index'))
            ->assertOk()
            ->assertSeeText('SAVE10')
            ->assertSeeText('Coupon Discount')
            ->assertSeeText('USD 90.00');
    }

    public function test_coupon_validates_minimum_usage_customer_and_scope_rules(): void
    {
        [$variant, $product] = $this->catalog(price: 4000);
        $customer = User::factory()->customer()->create();
        $cart = Cart::factory()->customer()->create(['user_id' => $customer->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => 1]);

        $minimum = Promotion::factory()->create(['code' => 'MINIMUM', 'minimum_spend' => 5000]);
        $this->actingAs($customer)->post(route('cart.promotion.store'), ['code' => $minimum->code])->assertSessionHasErrors('code');

        $used = Promotion::factory()->create(['code' => 'USED', 'per_customer_limit' => 1]);
        PromotionRedemption::query()->create([
            'promotion_id' => $used->id,
            'order_id' => Order::factory()->create(['user_id' => $customer->id])->id,
            'user_id' => $customer->id,
            'code_snapshot' => $used->code,
            'funding_source' => $used->funding_source,
            'discount_amount' => 100,
            'currency' => 'USD',
            'redeemed_at' => now(),
        ]);
        $this->actingAs($customer)->post(route('cart.promotion.store'), ['code' => $used->code])->assertSessionHasErrors('code');

        $wrongProduct = Promotion::factory()->create(['code' => 'OTHERITEM']);
        $wrongProduct->products()->sync([Product::factory()->approved()->create(['category_id' => $product->category_id])->id]);
        $this->actingAs($customer)->post(route('cart.promotion.store'), ['code' => $wrongProduct->code])->assertSessionHasErrors('code');
    }

    public function test_marketplace_coupon_checkout_writes_redemption_allocations_and_keeps_seller_value(): void
    {
        [$variant, , $vendor] = $this->catalog(price: 10000);
        $customer = User::factory()->customer()->create();
        CustomerAddress::factory()->default()->create(['user_id' => $customer->id, 'country_code' => 'PK']);
        ShippingMethod::factory()->create(['vendor_id' => $vendor->id, 'flat_rate' => 500]);
        $cart = Cart::factory()->customer()->create(['user_id' => $customer->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => 2]);
        $promotion = Promotion::factory()->create(['code' => 'MARKET20', 'discount_value' => 2000]);
        $cart->forceFill(['promotion_id' => $promotion->id])->save();

        $key = $this->readyCheckout($customer);
        $this->actingAs($customer)->post(route('checkout.place-order'), ['idempotency_key' => $key])->assertRedirect();

        $order = Order::query()->with('vendorOrders.items')->firstOrFail();

        $this->assertSame(20000, $order->items_subtotal);
        $this->assertSame(4000, $order->discount_total);
        $this->assertSame(16500, $order->grand_total);
        $this->assertSame(1, PromotionRedemption::query()->where('promotion_id', $promotion->id)->count());
        $this->assertSame(4000, OrderDiscountAllocation::query()->sum('amount'));
        $this->assertSame(4000, $order->vendorOrders->first()->marketplace_discount_total);
        $this->assertSame(0, $order->vendorOrders->first()->vendor_discount_total);
        $this->assertSame(16000, $order->vendorOrders->first()->items->first()->customerLineTotal());
        $this->assertSame(20000, $order->vendorOrders->first()->items->first()->sellerLineTotal());
    }

    public function test_vendor_funded_coupon_reduces_commission_basis_and_posts_one_promotion_ledger_debit(): void
    {
        [$variant, , $vendor] = $this->catalog(price: 10000);
        CommissionRule::factory()->create(['rate_basis_points' => 1000, 'include_shipping' => false]);
        $customer = User::factory()->customer()->create();
        CustomerAddress::factory()->default()->create(['user_id' => $customer->id, 'country_code' => 'PK']);
        ShippingMethod::factory()->create(['vendor_id' => $vendor->id, 'flat_rate' => 0]);
        $cart = Cart::factory()->customer()->create(['user_id' => $customer->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => 1]);
        $promotion = Promotion::factory()->vendorFunded($vendor)->fixed(2000)->create(['code' => 'STORE20']);
        $cart->forceFill(['promotion_id' => $promotion->id])->save();

        $key = $this->readyCheckout($customer);
        $this->actingAs($customer)->post(route('checkout.place-order'), ['idempotency_key' => $key])->assertRedirect();

        $vendorOrder = Order::query()->firstOrFail()->vendorOrders()->firstOrFail();
        $vendorOrder->order->payment()->update(['status' => \App\Enums\PaymentStatus::Paid]);
        $vendorOrder->forceFill([
            'status' => \App\Enums\VendorOrderStatus::Delivered,
            'delivered_at' => now(),
        ])->save();

        $commission = app(RecognizeVendorOrderCommission::class)->handle($vendorOrder);
        app(RecognizeVendorOrderCommission::class)->handle($vendorOrder);

        $this->assertSame(8000, $commission->original_items_amount);
        $this->assertSame(2000, $vendorOrder->refresh()->vendor_discount_total);
        $this->assertSame(1, VendorLedgerEntry::query()->where('type', VendorLedgerEntryType::Promotion)->count());
        $this->assertSame(-2000, VendorLedgerEntry::query()->where('type', VendorLedgerEntryType::Promotion)->firstOrFail()->amount);
    }

    public function test_promotion_management_is_role_scoped(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        $vendor = Vendor::factory()->approved()->create();
        $otherVendor = Vendor::factory()->approved()->create();
        $promotion = Promotion::factory()->vendorFunded($vendor)->create(['code' => 'OWNED']);

        $this->actingAs($admin)->get(route('admin.promotions.index'))->assertOk();
        $this->actingAs($customer)->get(route('admin.promotions.index'))->assertForbidden();
        $this->actingAs($vendor->user)->get(route('vendor.promotions.index'))->assertOk();
        $this->actingAs($otherVendor->user)->get(route('vendor.promotions.edit', $promotion))->assertNotFound();
    }

    public function test_promotion_seeder_is_repeatable(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Promotion::query()->where('code', 'WELCOME10')->count());
        $this->assertSame(1, Promotion::query()->where('code', 'TECH500')->count());
    }

    private function readyCheckout(User $customer): string
    {
        $this->actingAs($customer)
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSeeText('Place Order');

        return (string) session(CheckoutSession::IDEMPOTENCY_KEY);
    }

    private function catalog(int $price = 10000): array
    {
        $vendor = Vendor::factory()->approved()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->approved()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Promotion Product',
            'type' => ProductType::Simple,
            'status' => ProductStatus::Active,
            'approval_status' => ProductApprovalStatus::Approved,
        ]);
        $variant = ProductVariant::factory()->default()->create([
            'product_id' => $product->id,
            'sku' => 'PROMO-SKU',
            'base_price' => $price,
            'sale_price' => null,
        ]);
        Inventory::query()->updateOrCreate([
            'product_variant_id' => $variant->id,
        ], [
            'on_hand' => 20,
            'reserved' => 0,
            'low_stock_threshold' => 2,
        ]);

        return [$variant, $product, $vendor];
    }
}
