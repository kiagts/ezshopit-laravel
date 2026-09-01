<?php

namespace Tests\Feature\Shipping;

use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Checkout\CheckoutSession;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShippingFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_vendor_can_create_update_and_delete_shipping_method(): void
    {
        $vendor = Vendor::factory()->approved()->create();

        $this->actingAs($vendor->user)
            ->post(route('vendor.shipping-methods.store'), $this->methodData(['flat_rate' => '12.50', 'free_shipping_threshold' => '100.00']))
            ->assertRedirect(route('vendor.shipping-methods.index'));

        $method = ShippingMethod::query()->firstOrFail();

        $this->assertSame($vendor->id, $method->vendor_id);
        $this->assertSame(1250, $method->flat_rate);
        $this->assertSame(10000, $method->free_shipping_threshold);
        $this->assertTrue($method->ships_to_all_supported_countries);

        $this->actingAs($vendor->user)
            ->put(route('vendor.shipping-methods.update', $method), $this->methodData([
                'name' => 'Express Delivery',
                'code' => 'express',
                'flat_rate' => '15.00',
                'ships_to_all_supported_countries' => '0',
                'countries' => ['pk', 'US'],
            ]))
            ->assertRedirect(route('vendor.shipping-methods.index'));

        $this->assertSame(1500, $method->refresh()->flat_rate);
        $this->assertFalse($method->ships_to_all_supported_countries);
        $this->assertEqualsCanonicalizing(['PK', 'US'], $method->countries()->pluck('country_code')->all());

        $this->actingAs($vendor->user)
            ->delete(route('vendor.shipping-methods.destroy', $method))
            ->assertRedirect(route('vendor.shipping-methods.index'));

        $this->assertDatabaseMissing('shipping_methods', ['id' => $method->id]);
    }

    public function test_shipping_method_validation_and_authorization(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $otherVendor = Vendor::factory()->approved()->create();
        $pending = Vendor::factory()->create(['status' => VendorStatus::Pending]);
        $method = ShippingMethod::factory()->create(['vendor_id' => $otherVendor->id, 'code' => 'standard']);

        $this->actingAs($vendor->user)
            ->put(route('vendor.shipping-methods.update', $method), $this->methodData())
            ->assertNotFound();

        $this->actingAs($pending->user)
            ->get(route('vendor.shipping-methods.index'))
            ->assertRedirect(route('vendor.application-status'));

        $this->actingAs($vendor->user)
            ->post(route('vendor.shipping-methods.store'), $this->methodData([
                'flat_rate' => '-1',
                'max_delivery_days' => 2,
                'min_delivery_days' => 5,
                'ships_to_all_supported_countries' => '0',
                'countries' => [],
            ]))
            ->assertSessionHasErrors(['flat_rate', 'max_delivery_days', 'countries']);
    }

    public function test_checkout_uses_country_coverage_active_methods_and_default_selection(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $address = CustomerAddress::factory()->default()->create(['user_id' => $customer->id, 'country_code' => 'PK']);
        $vendor = Vendor::factory()->approved()->create(['display_name' => 'TechVerse']);
        $variant = $this->cartFor($customer, $vendor, 'Coverage Product', 10000, 2);

        $inactive = ShippingMethod::factory()->inactive()->create(['vendor_id' => $vendor->id, 'name' => 'Inactive Delivery', 'code' => 'inactive']);
        ShippingMethod::factory()->restricted(['US'])->create(['vendor_id' => $vendor->id, 'name' => 'US Only', 'code' => 'us_only']);
        $standard = ShippingMethod::factory()->restricted(['PK'])->create(['vendor_id' => $vendor->id, 'name' => 'Standard Delivery', 'code' => 'standard', 'flat_rate' => 500, 'sort_order' => 10]);

        $this->actingAs($customer)
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSeeText('Standard Delivery')
            ->assertSeeText('USD 5.00')
            ->assertDontSeeText('Inactive Delivery')
            ->assertDontSeeText('US Only')
            ->assertSessionHas(CheckoutSession::SHIPPING_METHODS_KEY.'.'.$vendor->id, $standard->id);

        $this->assertSame(10, $variant->inventory->refresh()->on_hand);
        $this->assertSame(0, $variant->inventory->reserved);
        $this->assertTrue($address->is_default);
        $this->assertDatabaseHas('shipping_methods', ['id' => $inactive->id, 'is_active' => false]);
    }

    public function test_free_shipping_threshold_is_per_vendor_and_totals_are_summed(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        CustomerAddress::factory()->default()->create(['user_id' => $customer->id, 'country_code' => 'PK']);
        $vendorA = Vendor::factory()->approved()->create(['display_name' => 'Vendor A']);
        $vendorB = Vendor::factory()->approved()->create(['display_name' => 'Vendor B']);
        $cart = Cart::factory()->customer()->create(['user_id' => $customer->id]);

        CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $this->variantFor($vendorA, 'Vendor A Product', 12000)->id, 'quantity' => 1]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $this->variantFor($vendorB, 'Vendor B Product', 4000)->id, 'quantity' => 1]);

        ShippingMethod::factory()->create(['vendor_id' => $vendorA->id, 'name' => 'A Standard', 'code' => 'a_standard', 'flat_rate' => 1000, 'free_shipping_threshold' => 10000]);
        ShippingMethod::factory()->create(['vendor_id' => $vendorB->id, 'name' => 'B Standard', 'code' => 'b_standard', 'flat_rate' => 700, 'free_shipping_threshold' => 10000]);

        $this->actingAs($customer)
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSeeText('A Standard')
            ->assertSeeText('B Standard')
            ->assertSeeText('Free')
            ->assertSeeText('USD 7.00')
            ->assertSeeText('USD 167.00');
    }

    public function test_customer_cannot_select_wrong_vendor_unsupported_or_inactive_method(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        CustomerAddress::factory()->default()->create(['user_id' => $customer->id, 'country_code' => 'PK']);
        $vendorA = Vendor::factory()->approved()->create();
        $vendorB = Vendor::factory()->approved()->create();
        $this->cartFor($customer, $vendorA);

        $valid = ShippingMethod::factory()->restricted(['PK'])->create(['vendor_id' => $vendorA->id, 'code' => 'valid']);
        $otherVendor = ShippingMethod::factory()->create(['vendor_id' => $vendorB->id, 'code' => 'other']);
        $unsupported = ShippingMethod::factory()->restricted(['US'])->create(['vendor_id' => $vendorA->id, 'code' => 'us']);
        $inactive = ShippingMethod::factory()->inactive()->create(['vendor_id' => $vendorA->id, 'code' => 'inactive']);

        foreach ([$otherVendor, $unsupported, $inactive] as $method) {
            $this->actingAs($customer)
                ->from(route('checkout.index'))
                ->put(route('checkout.shipping.update'), ['shipping_methods' => [$vendorA->id => $method->id]])
                ->assertSessionHasErrors("shipping_methods.{$vendorA->id}");
        }

        $this->actingAs($customer)
            ->put(route('checkout.shipping.update'), ['shipping_methods' => [$vendorA->id => $valid->id]])
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas(CheckoutSession::SHIPPING_METHODS_KEY.'.'.$vendorA->id, $valid->id);
    }

    public function test_stale_shipping_selections_are_replaced_or_cleared_when_checkout_changes(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        CustomerAddress::factory()->default()->create(['user_id' => $customer->id, 'country_code' => 'PK']);
        $vendorA = Vendor::factory()->approved()->create(['display_name' => 'Vendor A']);
        $vendorB = Vendor::factory()->approved()->create(['display_name' => 'Vendor B']);
        $cart = Cart::factory()->customer()->create(['user_id' => $customer->id]);
        $itemA = CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $this->variantFor($vendorA, 'A Product')->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $this->variantFor($vendorB, 'B Product')->id]);
        $methodA = ShippingMethod::factory()->create(['vendor_id' => $vendorA->id, 'code' => 'a']);
        ShippingMethod::factory()->create(['vendor_id' => $vendorB->id, 'code' => 'b']);

        $this->withSession([CheckoutSession::SHIPPING_METHODS_KEY => [
            $vendorA->id => $methodA->id,
            $vendorB->id => 999999,
            999999 => 123,
        ]])->actingAs($customer)
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSessionMissing(CheckoutSession::SHIPPING_METHODS_KEY.'.999999');

        $itemA->delete();

        $this->actingAs($customer)
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSessionMissing(CheckoutSession::SHIPPING_METHODS_KEY.'.'.$vendorA->id);
    }

    public function test_vendor_without_methods_blocks_checkout_but_does_not_mutate_inventory_or_create_orders(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        CustomerAddress::factory()->default()->create(['user_id' => $customer->id, 'country_code' => 'PK']);
        $vendor = Vendor::factory()->approved()->create(['display_name' => 'No Ship Vendor']);
        $variant = $this->cartFor($customer, $vendor);
        $inventory = $variant->inventory()->firstOrFail();
        $movementCount = InventoryMovement::query()->count();

        $this->actingAs($customer)
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSeeText("Delivery isn't available from this seller to your selected country.")
            ->assertSeeText('Resolve delivery and payment selections before placing your order.');

        $this->assertSame(10, $inventory->refresh()->on_hand);
        $this->assertSame(0, $inventory->reserved);
        $this->assertSame($movementCount, InventoryMovement::query()->count());
        $this->assertTrue(Schema::hasTable('orders'));
    }

    public function test_shipping_update_revalidates_invalid_cart_before_saving_selection(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        CustomerAddress::factory()->default()->create(['user_id' => $customer->id, 'country_code' => 'PK']);
        $vendor = Vendor::factory()->approved()->create();
        $variant = $this->cartFor($customer, $vendor);
        $method = ShippingMethod::factory()->create(['vendor_id' => $vendor->id]);

        $variant->inventory->forceFill(['on_hand' => 0, 'reserved' => 0])->save();

        $this->actingAs($customer)
            ->put(route('checkout.shipping.update'), ['shipping_methods' => [$vendor->id => $method->id]])
            ->assertRedirect(route('cart.index'))
            ->assertSessionMissing(CheckoutSession::SHIPPING_METHODS_KEY.'.'.$vendor->id);
    }

    public function test_delivery_estimates_admin_index_and_seeder_repeatability(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $vendor = Vendor::factory()->approved()->create(['display_name' => 'Admin Ship Vendor']);
        ShippingMethod::factory()->create(['vendor_id' => $vendor->id, 'name' => 'Three Day', 'code' => 'three', 'min_delivery_days' => 3, 'max_delivery_days' => 3]);
        ShippingMethod::factory()->create(['vendor_id' => $vendor->id, 'name' => 'Range Day', 'code' => 'range', 'min_delivery_days' => 3, 'max_delivery_days' => 5]);

        $this->actingAs($admin)
            ->get(route('admin.shipping-methods.index', ['q' => 'Admin Ship']))
            ->assertOk()
            ->assertSeeText('Three Day')
            ->assertSeeText('3 business days')
            ->assertSeeText('Range Day')
            ->assertSeeText('3-5 business days');

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $demoVendor = Vendor::query()->where('slug', 'techverse')->firstOrFail();
        $this->assertSame(2, $demoVendor->shippingMethods()->count());
        $this->assertDatabaseHas('shipping_methods', ['vendor_id' => $demoVendor->id, 'code' => 'standard']);
        $this->assertDatabaseHas('shipping_methods', ['vendor_id' => $demoVendor->id, 'code' => 'express']);
    }

    private function cartFor(?User $customer = null, ?Vendor $vendor = null, string $name = 'Shipping Cart Product', int $price = 10000, int $quantity = 1): ProductVariant
    {
        $customer ??= User::factory()->create(['role' => UserRole::Customer]);
        $vendor ??= Vendor::factory()->approved()->create();
        $variant = $this->variantFor($vendor, $name, $price);
        $cart = Cart::query()->firstOrCreate(['user_id' => $customer->id], ['token' => null]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => $quantity]);

        return $variant;
    }

    private function variantFor(Vendor $vendor, string $name, int $price = 10000): ProductVariant
    {
        return $this->createPublicProduct($name, vendor: $vendor, variants: [['sku' => str($name)->slug()->upper().'-SKU', 'base_price' => $price, 'stock' => 10]])->variants()->firstOrFail();
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

        foreach ($variants as $index => $variantData) {
            $variant = ProductVariant::factory()->create([
                'product_id' => $product->id,
                'sku' => $variantData['sku'],
                'base_price' => $variantData['base_price'],
                'sale_price' => $variantData['sale_price'] ?? null,
                'is_default' => $index === 0,
                'combination_key' => 'shipping-'.$index,
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

    private function methodData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Standard Delivery',
            'code' => 'standard',
            'description' => 'Tracked flat-rate delivery.',
            'flat_rate' => '5.00',
            'free_shipping_threshold' => '',
            'min_delivery_days' => 3,
            'max_delivery_days' => 5,
            'ships_to_all_supported_countries' => '1',
            'countries' => [],
            'is_active' => '1',
            'sort_order' => 10,
        ], $overrides);
    }
}
