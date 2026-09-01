<?php

namespace Tests\Feature\Orders;

use App\Enums\InventoryReservationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductApprovalStatus;
use App\Enums\ProductAttributeInputType;
use App\Enums\ProductAttributeType;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Services\Checkout\CheckoutSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_place_multi_vendor_order_with_snapshots_and_reservations(): void
    {
        $customer = User::factory()->customer()->create();
        $address = CustomerAddress::factory()->default()->create([
            'user_id' => $customer->id,
            'first_name' => 'Jane',
            'last_name' => 'Buyer',
            'country_code' => 'PK',
        ]);
        $vendorA = Vendor::factory()->approved()->create(['display_name' => 'TechVerse']);
        $vendorB = Vendor::factory()->approved()->create(['display_name' => 'HomeCraft']);
        $variantA = $this->variantFor($vendorA, 'Snapshot Phone', 'SNAP-PHONE', 10000, 8000, 10);
        $variantB = $this->variantFor($vendorB, 'Snapshot Mixer', 'SNAP-MIXER', 5000, null, 10);
        $methodA = ShippingMethod::factory()->create(['vendor_id' => $vendorA->id, 'name' => 'A Standard', 'code' => 'a_standard', 'flat_rate' => 1000, 'free_shipping_threshold' => 10000]);
        $methodB = ShippingMethod::factory()->create(['vendor_id' => $vendorB->id, 'name' => 'B Standard', 'code' => 'b_standard', 'flat_rate' => 700, 'min_delivery_days' => 2, 'max_delivery_days' => 4]);

        $cart = Cart::factory()->customer()->create(['user_id' => $customer->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $variantA->id, 'quantity' => 2]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $variantB->id, 'quantity' => 1]);

        $key = $this->readyCheckout($customer, [
            CheckoutSession::ADDRESS_KEY => $address->id,
            CheckoutSession::SHIPPING_METHODS_KEY => [$vendorA->id => $methodA->id, $vendorB->id => $methodB->id],
        ]);

        $this->actingAs($customer)
            ->post(route('checkout.place-order'), ['idempotency_key' => $key])
            ->assertRedirect();

        $order = Order::query()->with(['vendorOrders.items.reservation'])->firstOrFail();

        $this->assertSame($customer->id, $order->user_id);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame('USD', $order->currency);
        $this->assertSame(21000, $order->items_subtotal);
        $this->assertSame(700, $order->shipping_total);
        $this->assertSame(21700, $order->grand_total);
        $this->assertSame('Jane', $order->shipping_first_name);
        $this->assertSame('Buyer', $order->shipping_last_name);
        $this->assertStringStartsWith('ORD-', $order->order_number);

        $this->assertCount(2, $order->vendorOrders);
        $this->assertSame($order->grand_total, $order->vendorOrders->sum('grand_total'));

        $techVerseOrder = $order->vendorOrders->firstWhere('vendor_id', $vendorA->id);
        $this->assertSame('A Standard', $techVerseOrder->shipping_method_name);
        $this->assertSame('a_standard', $techVerseOrder->shipping_method_code);
        $this->assertSame(16000, $techVerseOrder->items_subtotal);
        $this->assertSame(0, $techVerseOrder->shipping_total);
        $this->assertSame(16000, $techVerseOrder->grand_total);

        $phoneItem = $techVerseOrder->items->first();
        $this->assertSame('Snapshot Phone', $phoneItem->product_name);
        $this->assertSame('SNAP-PHONE', $phoneItem->sku);
        $this->assertSame(8000, $phoneItem->unit_price);
        $this->assertSame(16000, $phoneItem->line_total);
        $this->assertNull($phoneItem->variant_description);
        $this->assertSame(2, $phoneItem->reservation->quantity);
        $this->assertSame(InventoryReservationStatus::Active, $phoneItem->reservation->status);

        $this->assertSame(10, $variantA->inventory->refresh()->on_hand);
        $this->assertSame(2, $variantA->inventory->reserved);
        $this->assertSame(8, $variantA->inventory->availableQuantity());
        $this->assertSame(1, $variantB->inventory->refresh()->reserved);
        $this->assertSame(0, CartItem::query()->count());
        $this->assertNull(session(CheckoutSession::IDEMPOTENCY_KEY));
    }

    public function test_order_snapshots_survive_live_address_shipping_product_and_price_mutation(): void
    {
        $customer = User::factory()->customer()->create();
        $address = CustomerAddress::factory()->default()->create(['user_id' => $customer->id, 'first_name' => 'Original', 'country_code' => 'PK']);
        $vendor = Vendor::factory()->approved()->create();
        $variant = $this->variantFor($vendor, 'Original Product', 'ORIGINAL-SKU', 12000, null, 10);
        $method = ShippingMethod::factory()->create(['vendor_id' => $vendor->id, 'name' => 'Original Ship', 'code' => 'original_ship', 'flat_rate' => 500, 'min_delivery_days' => 3, 'max_delivery_days' => 5]);
        $this->cartWith($customer, [$variant->id => 1]);

        $key = $this->readyCheckout($customer, [CheckoutSession::ADDRESS_KEY => $address->id]);

        $this->actingAs($customer)->post(route('checkout.place-order'), ['idempotency_key' => $key])->assertRedirect();

        $order = Order::query()->with('vendorOrders.items')->firstOrFail();
        $address->update(['first_name' => 'Changed']);
        $method->update(['name' => 'Changed Ship', 'flat_rate' => 9999]);
        $variant->product->update(['name' => 'Changed Product']);
        $variant->update(['sku' => 'CHANGED-SKU', 'base_price' => 50000]);

        $this->assertSame('Original', $order->refresh()->shipping_first_name);
        $this->assertSame('Original Ship', $order->vendorOrders->first()->shipping_method_name);
        $this->assertSame(500, $order->vendorOrders->first()->shipping_total);
        $this->assertSame('Original Product', $order->vendorOrders->first()->items->first()->product_name);
        $this->assertSame('ORIGINAL-SKU', $order->vendorOrders->first()->items->first()->sku);
        $this->assertSame(12000, $order->vendorOrders->first()->items->first()->unit_price);
    }

    public function test_variant_attribute_snapshot_is_customer_relevant_and_simple_product_has_no_default_label(): void
    {
        $customer = User::factory()->customer()->create();
        CustomerAddress::factory()->default()->create(['user_id' => $customer->id, 'country_code' => 'PK']);
        $vendor = Vendor::factory()->approved()->create();
        $variant = $this->variantProductFor($vendor);
        $simple = $this->variantFor($vendor, 'Simple Product', 'SIMPLE-SKU', 4000, null, 10);
        ShippingMethod::factory()->create(['vendor_id' => $vendor->id, 'flat_rate' => 500]);
        $this->cartWith($customer, [$variant->id => 1, $simple->id => 1]);

        $key = $this->readyCheckout($customer);
        $this->actingAs($customer)->post(route('checkout.place-order'), ['idempotency_key' => $key])->assertRedirect();

        $variantItem = OrderItem::query()->where('sku', 'PHONE-BLACK-256')->firstOrFail();
        $simpleItem = OrderItem::query()->where('sku', 'SIMPLE-SKU')->firstOrFail();

        $this->assertSame('Black / 256GB', $variantItem->variant_description);
        $this->assertSame([
            ['attribute_code' => 'color', 'attribute_name' => 'Color', 'value_code' => 'black', 'value_label' => 'Black'],
            ['attribute_code' => 'storage', 'attribute_name' => 'Storage', 'value_code' => '256gb', 'value_label' => '256GB'],
        ], $variantItem->attributes_snapshot);
        $this->assertNull($simpleItem->variant_description);
        $this->assertNull($simpleItem->attributes_snapshot);
    }

    public function test_insufficient_inventory_rolls_back_everything_and_keeps_cart(): void
    {
        $customer = User::factory()->customer()->create();
        CustomerAddress::factory()->default()->create(['user_id' => $customer->id, 'country_code' => 'PK']);
        $vendorA = Vendor::factory()->approved()->create();
        $vendorB = Vendor::factory()->approved()->create();
        $available = $this->variantFor($vendorA, 'Available Product', 'AVAILABLE-SKU', 10000, null, 10);
        $unavailable = $this->variantFor($vendorB, 'Unavailable Product', 'UNAVAILABLE-SKU', 10000, null, 10);
        ShippingMethod::factory()->create(['vendor_id' => $vendorA->id]);
        ShippingMethod::factory()->create(['vendor_id' => $vendorB->id]);
        $this->cartWith($customer, [$available->id => 1, $unavailable->id => 2]);

        $key = $this->readyCheckout($customer);
        $unavailable->inventory->update(['on_hand' => 1, 'reserved' => 0]);

        $this->actingAs($customer)
            ->post(route('checkout.place-order'), ['idempotency_key' => $key])
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHasErrors('cart');

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, VendorOrder::query()->count());
        $this->assertSame(0, OrderItem::query()->count());
        $this->assertSame(0, InventoryReservation::query()->count());
        $this->assertSame(0, $available->inventory->refresh()->reserved);
        $this->assertSame(2, CartItem::query()->count());
        $this->assertNotNull(session(CheckoutSession::IDEMPOTENCY_KEY));
    }

    public function test_stale_checkout_data_is_rejected_without_clearing_cart(): void
    {
        $customer = User::factory()->customer()->create();
        $address = CustomerAddress::factory()->default()->create(['user_id' => $customer->id, 'country_code' => 'PK']);
        $vendor = Vendor::factory()->approved()->create();
        $variant = $this->variantFor($vendor, 'Stale Product', 'STALE-SKU', 10000, null, 10);
        $method = ShippingMethod::factory()->restricted(['PK'])->create(['vendor_id' => $vendor->id]);
        $this->cartWith($customer, [$variant->id => 1]);

        $key = $this->readyCheckout($customer, [
            CheckoutSession::ADDRESS_KEY => $address->id,
            CheckoutSession::SHIPPING_METHODS_KEY => [$vendor->id => $method->id],
        ]);

        $method->update(['is_active' => false]);

        $this->actingAs($customer)
            ->post(route('checkout.place-order'), ['idempotency_key' => $key])
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHasErrors('shipping');

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(1, CartItem::query()->count());

        $method->update(['is_active' => true]);
        $key = $this->readyCheckout($customer, [CheckoutSession::SHIPPING_METHODS_KEY => [$vendor->id => $method->id]]);
        $variant->product->update(['status' => ProductStatus::Inactive]);

        $this->actingAs($customer)
            ->post(route('checkout.place-order'), ['idempotency_key' => $key])
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHasErrors('cart');

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(1, CartItem::query()->count());
    }

    public function test_price_and_free_shipping_are_recalculated_at_placement(): void
    {
        $customer = User::factory()->customer()->create();
        CustomerAddress::factory()->default()->create(['user_id' => $customer->id, 'country_code' => 'PK']);
        $vendor = Vendor::factory()->approved()->create();
        $variant = $this->variantFor($vendor, 'Changing Price', 'PRICE-SKU', 9000, null, 10);
        ShippingMethod::factory()->create(['vendor_id' => $vendor->id, 'flat_rate' => 600, 'free_shipping_threshold' => 10000]);
        $this->cartWith($customer, [$variant->id => 1]);

        $key = $this->readyCheckout($customer);
        $variant->update(['base_price' => 11000]);

        $this->actingAs($customer)->post(route('checkout.place-order'), ['idempotency_key' => $key])->assertRedirect();

        $order = Order::query()->with('vendorOrders.items')->firstOrFail();
        $this->assertSame(11000, $order->items_subtotal);
        $this->assertSame(0, $order->shipping_total);
        $this->assertSame(11000, $order->vendorOrders->first()->items->first()->unit_price);
    }

    public function test_idempotency_reuses_existing_order_and_empty_cart_cannot_create_another(): void
    {
        $customer = User::factory()->customer()->create();
        CustomerAddress::factory()->default()->create(['user_id' => $customer->id, 'country_code' => 'PK']);
        $vendor = Vendor::factory()->approved()->create();
        $variant = $this->variantFor($vendor, 'Idempotent Product', 'IDEMPOTENT-SKU', 10000, null, 10);
        ShippingMethod::factory()->create(['vendor_id' => $vendor->id]);
        $this->cartWith($customer, [$variant->id => 1]);

        $key = $this->readyCheckout($customer);

        $this->actingAs($customer)->post(route('checkout.place-order'), ['idempotency_key' => $key])->assertRedirect();
        $this->actingAs($customer)->post(route('checkout.place-order'), ['idempotency_key' => $key])->assertRedirect();

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, InventoryReservation::query()->count());
        $this->assertSame(1, $variant->inventory->refresh()->reserved);

        $this->actingAs($customer)
            ->post(route('checkout.place-order'), ['idempotency_key' => (string) str()->uuid()])
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHasErrors('cart');

        $this->assertSame(1, Order::query()->count());
    }

    public function test_customer_vendor_and_admin_order_access_boundaries(): void
    {
        $customer = User::factory()->customer()->create();
        $otherCustomer = User::factory()->customer()->create();
        CustomerAddress::factory()->default()->create(['user_id' => $customer->id, 'country_code' => 'PK']);
        $vendorA = Vendor::factory()->approved()->create(['display_name' => 'Seller A']);
        $vendorB = Vendor::factory()->approved()->create(['display_name' => 'Seller B']);
        $variantA = $this->variantFor($vendorA, 'Seller A Product', 'SELLER-A-SKU', 10000, null, 10);
        $variantB = $this->variantFor($vendorB, 'Seller B Secret', 'SELLER-B-SKU', 20000, null, 10);
        ShippingMethod::factory()->create(['vendor_id' => $vendorA->id, 'flat_rate' => 100]);
        ShippingMethod::factory()->create(['vendor_id' => $vendorB->id, 'flat_rate' => 200]);
        $this->cartWith($customer, [$variantA->id => 1, $variantB->id => 1]);

        $key = $this->readyCheckout($customer);
        $this->actingAs($customer)->post(route('checkout.place-order'), ['idempotency_key' => $key]);

        $order = Order::query()->with('vendorOrders')->firstOrFail();
        $vendorAOrder = $order->vendorOrders->firstWhere('vendor_id', $vendorA->id);
        $vendorBOrder = $order->vendorOrders->firstWhere('vendor_id', $vendorB->id);
        $admin = User::factory()->admin()->create();

        $this->actingAs($customer)->get(route('account.orders.index'))->assertOk()->assertSeeText($order->order_number);
        $this->actingAs($customer)->get(route('account.orders.show', $order))->assertOk()->assertSeeText('Seller B Secret');
        $this->actingAs($otherCustomer)->get(route('account.orders.show', $order))->assertNotFound();

        $this->actingAs($vendorA->user)
            ->get(route('vendor.orders.show', $vendorAOrder))
            ->assertOk()
            ->assertSeeText('Seller A Product')
            ->assertDontSeeText('Seller B Secret')
            ->assertDontSeeText($vendorBOrder->vendor_order_number);

        $this->actingAs($vendorA->user)->get(route('vendor.orders.show', $vendorBOrder))->assertNotFound();

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSeeText('Seller A Product')
            ->assertSeeText('Seller B Secret');
    }

    private function readyCheckout(User $customer, array $session = []): string
    {
        $this->withSession($session)
            ->actingAs($customer)
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSeeText('Place Order');

        return (string) session(CheckoutSession::IDEMPOTENCY_KEY);
    }

    private function cartWith(User $customer, array $variantQuantities): Cart
    {
        $cart = Cart::factory()->customer()->create(['user_id' => $customer->id]);

        foreach ($variantQuantities as $variantId => $quantity) {
            CartItem::factory()->create([
                'cart_id' => $cart->id,
                'product_variant_id' => $variantId,
                'quantity' => $quantity,
            ]);
        }

        return $cart;
    }

    private function variantFor(Vendor $vendor, string $name, string $sku, int $basePrice, ?int $salePrice, int $stock): ProductVariant
    {
        $product = Product::factory()
            ->approved()
            ->create([
                'vendor_id' => $vendor->id,
                'category_id' => Category::factory()->create()->id,
                'name' => $name,
                'type' => ProductType::Simple,
                'status' => ProductStatus::Active,
                'approval_status' => ProductApprovalStatus::Approved,
            ]);

        $variant = ProductVariant::factory()->default()->create([
            'product_id' => $product->id,
            'sku' => $sku,
            'base_price' => $basePrice,
            'sale_price' => $salePrice,
        ]);

        Inventory::query()->updateOrCreate(
            ['product_variant_id' => $variant->id],
            ['on_hand' => $stock, 'reserved' => 0, 'low_stock_threshold' => 2],
        );

        return $variant->load(['inventory', 'product']);
    }

    private function variantProductFor(Vendor $vendor): ProductVariant
    {
        $color = ProductAttribute::factory()->create([
            'name' => 'Color',
            'code' => 'color',
            'type' => ProductAttributeType::Variant,
            'input_type' => ProductAttributeInputType::Select,
            'sort_order' => 1,
        ]);
        $storage = ProductAttribute::factory()->create([
            'name' => 'Storage',
            'code' => 'storage',
            'type' => ProductAttributeType::Variant,
            'input_type' => ProductAttributeInputType::Select,
            'sort_order' => 2,
        ]);
        $black = ProductAttributeValue::factory()->create(['product_attribute_id' => $color->id, 'value' => 'Black', 'code' => 'black']);
        $storage256 = ProductAttributeValue::factory()->create(['product_attribute_id' => $storage->id, 'value' => '256GB', 'code' => '256gb']);
        $product = Product::factory()->approved()->create([
            'vendor_id' => $vendor->id,
            'category_id' => Category::factory()->create()->id,
            'name' => 'Variant Phone',
            'type' => ProductType::Variant,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'PHONE-BLACK-256',
            'name' => null,
            'base_price' => 30000,
            'is_default' => false,
            'combination_key' => 'color:black|storage:256gb',
        ]);

        $variant->attributeValues()->attach($black->id, ['product_attribute_id' => $color->id]);
        $variant->attributeValues()->attach($storage256->id, ['product_attribute_id' => $storage->id]);
        Inventory::query()->updateOrCreate(
            ['product_variant_id' => $variant->id],
            ['on_hand' => 10, 'reserved' => 0, 'low_stock_threshold' => 2],
        );

        return $variant->load(['inventory', 'product', 'attributeValues.productAttribute']);
    }
}
