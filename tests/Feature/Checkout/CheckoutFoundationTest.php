<?php

namespace Tests\Feature\Checkout;

use App\Enums\ProductApprovalStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Cart\CartResolver;
use App\Services\Checkout\CheckoutSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CheckoutFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_requires_customer_authentication_and_non_empty_valid_cart(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $vendorUser = User::factory()->create(['role' => UserRole::Vendor]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->get(route('checkout.index'))->assertRedirect(route('login'));
        $this->actingAs($vendorUser)->get(route('checkout.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('checkout.index'))->assertForbidden();

        $this->actingAs($customer)
            ->get(route('checkout.index'))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('status', 'Your cart is empty.');
    }

    public function test_customer_with_valid_cart_can_access_checkout_and_review_seller_grouped_exact_variants(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $home = CustomerAddress::factory()->default()->create(['user_id' => $customer->id, 'label' => 'Home']);
        $techVerse = Vendor::factory()->approved()->create(['display_name' => 'TechVerse', 'slug' => 'techverse-checkout']);
        $homeCraft = Vendor::factory()->approved()->create(['display_name' => 'HomeCraft', 'slug' => 'homecraft-checkout']);
        $phone = $this->createPublicProduct('Checkout Phone', vendor: $techVerse, variants: [['sku' => 'CHECKOUT-PHONE-BLK', 'base_price' => 10000, 'sale_price' => 8000, 'stock' => 10]]);
        $mixer = $this->createPublicProduct('Checkout Mixer', vendor: $homeCraft, variants: [['sku' => 'CHECKOUT-MIXER', 'base_price' => 5000, 'stock' => 10]]);
        $cart = Cart::factory()->customer()->create(['user_id' => $customer->id]);

        CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $phone->variants()->first()->id, 'quantity' => 2]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $mixer->variants()->first()->id, 'quantity' => 1]);

        $this->actingAs($customer)
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSeeText('Delivery Address')
            ->assertSeeText('Home')
            ->assertSeeText('Selected')
            ->assertSeeText('TechVerse')
            ->assertSeeText('HomeCraft')
            ->assertSeeText('Checkout Phone')
            ->assertSeeText('CHECKOUT-PHONE-BLK')
            ->assertSeeText('Checkout Mixer')
            ->assertSeeText('USD 80.00 each')
            ->assertSeeText('USD 160.00')
            ->assertSeeText('USD 210.00')
            ->assertSeeText('Shipping')
            ->assertSeeText('Resolve delivery and payment selections before placing your order.');
    }

    public function test_checkout_blocks_current_cart_issues(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);

        foreach ($this->blockingVariants() as $variant) {
            Cart::query()->delete();
            $cart = Cart::factory()->customer()->create(['user_id' => $customer->id]);
            CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => 2]);

            $this->actingAs($customer)
                ->get(route('checkout.index'))
                ->assertRedirect(route('cart.index'))
                ->assertSessionHas('status', 'Some items in your cart need attention before checkout.');
        }
    }

    public function test_customer_can_select_checkout_address_without_changing_default(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $home = CustomerAddress::factory()->default()->create(['user_id' => $customer->id, 'label' => 'Home']);
        $office = CustomerAddress::factory()->create(['user_id' => $customer->id, 'label' => 'Office']);
        $this->cartFor($customer);

        $this->actingAs($customer)
            ->put(route('checkout.address.update'), ['address_id' => $office->id])
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas(CheckoutSession::ADDRESS_KEY, $office->id);

        $this->assertTrue($home->refresh()->is_default);
        $this->assertFalse($office->refresh()->is_default);
    }

    public function test_cross_customer_and_stale_checkout_address_selection_are_safe(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $other = User::factory()->create(['role' => UserRole::Customer]);
        $home = CustomerAddress::factory()->default()->create(['user_id' => $customer->id, 'label' => 'Home']);
        $otherAddress = CustomerAddress::factory()->create(['user_id' => $other->id]);
        $this->cartFor($customer);

        $this->actingAs($customer)
            ->put(route('checkout.address.update'), ['address_id' => $otherAddress->id])
            ->assertNotFound();

        $this->withSession([CheckoutSession::ADDRESS_KEY => $otherAddress->id])
            ->actingAs($customer)
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSeeText('Home');

        $this->assertSame($home->id, $customer->addresses()->firstWhere('is_default', true)->id);
    }

    public function test_checkout_address_creation_selects_new_address_and_first_one_becomes_default(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $this->cartFor($customer);

        $this->actingAs($customer)
            ->post(route('checkout.addresses.store'), $this->addressData(['label' => 'Checkout Home']))
            ->assertRedirect(route('checkout.index'));

        $address = CustomerAddress::query()->firstOrFail();

        $this->assertTrue($address->is_default);
        $this->assertSame('Checkout Home', $address->label);
        $this->assertSame($address->id, session(CheckoutSession::ADDRESS_KEY));
    }

    public function test_checkout_does_not_mutate_inventory_or_create_order_tables(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $variant = $this->cartFor($customer);
        CustomerAddress::factory()->default()->create(['user_id' => $customer->id]);
        $inventory = $variant->inventory()->firstOrFail();
        $movementCount = InventoryMovement::query()->count();

        $this->actingAs($customer)->get(route('checkout.index'))->assertOk();
        $this->actingAs($customer)->put(route('checkout.address.update'), [
            'address_id' => $customer->addresses()->first()->id,
        ])->assertRedirect(route('checkout.index'));
        $this->actingAs($customer)->post(route('checkout.addresses.store'), $this->addressData(['label' => 'No Stock Mutation']))->assertRedirect(route('checkout.index'));

        $this->assertSame(10, $inventory->refresh()->on_hand);
        $this->assertSame(0, $inventory->reserved);
        $this->assertSame($movementCount, InventoryMovement::query()->count());
        $this->assertTrue(Schema::hasTable('orders'));
        $this->assertSame(0, Order::query()->count());
    }

    public function test_cart_checkout_cta_and_guest_intended_redirect_with_cart_merge(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer, 'email' => 'checkout-merge@example.com', 'password' => bcrypt('password')]);
        $variant = $this->createPublicProduct('Guest Checkout Merge')->variants()->firstOrFail();
        $guestCart = Cart::factory()->create(['token' => 'checkout-guest-token']);
        CartItem::factory()->create(['cart_id' => $guestCart->id, 'product_variant_id' => $variant->id, 'quantity' => 1]);

        $this->withSession([CartResolver::SESSION_KEY => 'checkout-guest-token'])
            ->get(route('cart.index'))
            ->assertOk()
            ->assertSee(route('checkout.index'), false)
            ->assertSeeText('Proceed to Checkout');

        $this->withSession([CartResolver::SESSION_KEY => 'checkout-guest-token'])
            ->get(route('checkout.index'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('url.intended', route('checkout.index'));

        $this->withSession([CartResolver::SESSION_KEY => 'checkout-guest-token', 'url.intended' => route('checkout.index')])
            ->post(route('login'), ['email' => 'checkout-merge@example.com', 'password' => 'password'])
            ->assertRedirect(route('checkout.index'));

        $this->assertDatabaseHas('cart_items', ['product_variant_id' => $variant->id, 'quantity' => 1]);
        $this->assertDatabaseMissing('carts', ['id' => $guestCart->id]);
    }

    private function cartFor(User $customer): ProductVariant
    {
        $variant = $this->createPublicProduct('Checkout Cart Product', variants: [['sku' => 'CHECKOUT-CART', 'base_price' => 10000, 'stock' => 10]])->variants()->firstOrFail();
        $cart = Cart::factory()->customer()->create(['user_id' => $customer->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => 1]);

        return $variant;
    }

    /**
     * @return array<int, ProductVariant>
     */
    private function blockingVariants(): array
    {
        $inactiveProduct = $this->createPublicProduct('Inactive Checkout', productState: ['status' => ProductStatus::Inactive])->variants()->first();
        $pendingProduct = $this->createPublicProduct('Pending Checkout', productState: ['approval_status' => ProductApprovalStatus::Pending])->variants()->first();
        $suspendedVendor = $this->createPublicProduct('Suspended Checkout', vendorState: ['status' => VendorStatus::Suspended])->variants()->first();
        $outOfStock = $this->createPublicProduct('Out Stock Checkout', variants: [['sku' => 'CHECKOUT-OUT', 'base_price' => 10000, 'stock' => 0]])->variants()->first();
        $insufficient = $this->createPublicProduct('Insufficient Checkout', variants: [['sku' => 'CHECKOUT-LOW', 'base_price' => 10000, 'stock' => 1]])->variants()->first();
        $inactiveVariant = $this->createPublicProduct('Inactive Variant Checkout')->variants()->first();
        $inactiveVariant->update(['is_active' => false]);

        return [$inactiveProduct, $pendingProduct, $suspendedVendor, $outOfStock, $insufficient, $inactiveVariant];
    }

    private function createPublicProduct(
        string $name,
        ?Category $category = null,
        ?Vendor $vendor = null,
        array $variants = [],
        array $productState = [],
        array $vendorState = [],
    ): Product {
        $category ??= Category::factory()->create();
        $vendor ??= Vendor::factory()->approved()->state($vendorState)->create();

        $product = Product::factory()
            ->approved()
            ->state($productState)
            ->create([
                'name' => $name,
                'type' => $productState['type'] ?? ProductType::Simple,
                'category_id' => $category->id,
                'vendor_id' => $vendor->id,
                'short_description' => "{$name} short description",
            ]);

        $variants = $variants ?: [['sku' => str($name)->slug()->upper().'-SKU', 'base_price' => 10000, 'stock' => 10]];

        foreach ($variants as $index => $variantData) {
            $variant = ProductVariant::factory()->create([
                'product_id' => $product->id,
                'sku' => $variantData['sku'],
                'base_price' => $variantData['base_price'],
                'sale_price' => $variantData['sale_price'] ?? null,
                'is_default' => $index === 0,
                'combination_key' => 'checkout-'.$index,
            ]);

            Inventory::query()->updateOrCreate(
                ['product_variant_id' => $variant->id],
                [
                    'on_hand' => $variantData['stock'] ?? 10,
                    'reserved' => 0,
                    'low_stock_threshold' => 2,
                ],
            );
        }

        return $product;
    }

    private function addressData(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Home',
            'first_name' => 'Jane',
            'last_name' => 'Customer',
            'phone' => '+1 555 0100',
            'address_line_1' => '123 Example Street',
            'address_line_2' => null,
            'city' => 'Example City',
            'state_region' => 'California',
            'postal_code' => '90210',
            'country_code' => 'US',
            'is_default' => '0',
        ], $overrides);
    }
}
