<?php

namespace Tests\Feature\Storefront;

use App\Enums\ProductApprovalStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Cart\CartResolver;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_customer_can_create_carts_by_adding_public_in_stock_variant(): void
    {
        $variant = $this->createPublicProduct('Guest Cart Product', variants: [['sku' => 'GUEST-CART', 'base_price' => 10000, 'stock' => 6]])->variants()->firstOrFail();

        $this->post(route('cart.items.store'), ['product_variant_id' => $variant->id, 'quantity' => 2])
            ->assertRedirect()
            ->assertSessionHas(CartResolver::SESSION_KEY);

        $this->assertDatabaseHas('cart_items', ['product_variant_id' => $variant->id, 'quantity' => 2]);

        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $otherVariant = $this->createPublicProduct('Customer Cart Product', variants: [['sku' => 'CUSTOMER-CART', 'base_price' => 12000, 'stock' => 5]])->variants()->firstOrFail();

        $this->actingAs($customer)
            ->post(route('cart.items.store'), ['product_variant_id' => $otherVariant->id, 'quantity' => 1])
            ->assertRedirect();

        $this->assertDatabaseHas('carts', ['user_id' => $customer->id, 'token' => null]);
        $this->assertDatabaseHas('cart_items', ['product_variant_id' => $otherVariant->id, 'quantity' => 1]);
    }

    public function test_shopper_ownership_protects_cart_item_mutations(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $otherCustomer = User::factory()->create(['role' => UserRole::Customer]);
        $variant = $this->createPublicProduct('Owned Cart Item')->variants()->firstOrFail();
        $otherCart = Cart::factory()->customer()->create(['user_id' => $otherCustomer->id]);
        $otherItem = CartItem::factory()->create(['cart_id' => $otherCart->id, 'product_variant_id' => $variant->id, 'quantity' => 2]);

        $this->actingAs($customer)
            ->patch(route('cart.items.update', $otherItem), ['quantity' => 1])
            ->assertNotFound();

        $this->actingAs($customer)
            ->delete(route('cart.items.destroy', $otherItem))
            ->assertNotFound();

        $this->assertDatabaseHas('cart_items', ['id' => $otherItem->id, 'quantity' => 2]);
    }

    public function test_vendor_and_admin_cannot_use_cart_routes(): void
    {
        $variant = $this->createPublicProduct('Role Blocked Cart')->variants()->firstOrFail();
        $vendor = User::factory()->create(['role' => UserRole::Vendor]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($vendor)
            ->get(route('cart.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('cart.items.store'), ['product_variant_id' => $variant->id, 'quantity' => 1])
            ->assertForbidden();
    }

    public function test_same_variant_increments_but_different_variants_stay_separate(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $product = $this->createPublicProduct('Variant Cart Product', productState: ['type' => ProductType::Variant], variants: [
            ['sku' => 'VAR-128', 'base_price' => 10000, 'stock' => 8],
            ['sku' => 'VAR-256', 'base_price' => 12000, 'stock' => 8],
        ]);
        [$first, $second] = $product->variants()->orderBy('sku')->get();

        $this->actingAs($customer)->post(route('cart.items.store'), ['product_variant_id' => $first->id, 'quantity' => 2])->assertRedirect();
        $this->actingAs($customer)->post(route('cart.items.store'), ['product_variant_id' => $first->id, 'quantity' => 3])->assertRedirect();
        $this->actingAs($customer)->post(route('cart.items.store'), ['product_variant_id' => $second->id, 'quantity' => 1])->assertRedirect();

        $cart = Cart::query()->where('user_id', $customer->id)->firstOrFail();

        $this->assertSame(2, $cart->items()->count());
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'product_variant_id' => $first->id, 'quantity' => 5]);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'product_variant_id' => $second->id, 'quantity' => 1]);
    }

    public function test_cannot_add_non_public_inactive_deleted_or_out_of_stock_variants(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $category = Category::factory()->create();
        $inactiveParent = Category::factory()->inactive()->create();
        $hiddenChild = Category::factory()->childOf($inactiveParent)->create();

        $cases = [
            $this->createPublicProduct('Draft Cart', category: $category, productState: ['status' => ProductStatus::Draft])->variants()->first(),
            $this->createPublicProduct('Pending Cart', category: $category, productState: ['approval_status' => ProductApprovalStatus::Pending])->variants()->first(),
            $this->createPublicProduct('Rejected Cart', category: $category, productState: ['approval_status' => ProductApprovalStatus::Rejected])->variants()->first(),
            $this->createPublicProduct('Inactive Cart', category: $category, productState: ['status' => ProductStatus::Inactive])->variants()->first(),
            $this->createPublicProduct('Archived Cart', category: $category, productState: ['status' => ProductStatus::Archived])->variants()->first(),
            $this->createPublicProduct('Suspended Vendor Cart', category: $category, vendorState: ['status' => VendorStatus::Suspended])->variants()->first(),
            $this->createPublicProduct('Inactive Category Cart', category: Category::factory()->inactive()->create())->variants()->first(),
            $this->createPublicProduct('Inactive Ancestor Cart', category: $hiddenChild)->variants()->first(),
            $this->createPublicProduct('Out Stock Cart', category: $category, variants: [['sku' => 'OUT-STOCK-CART', 'base_price' => 10000, 'stock' => 0]])->variants()->first(),
        ];

        $inactiveVariant = $this->createPublicProduct('Inactive Variant Cart', category: $category)->variants()->first();
        $inactiveVariant->update(['is_active' => false]);
        $cases[] = $inactiveVariant;

        $deletedVariant = $this->createPublicProduct('Deleted Variant Cart', category: $category)->variants()->first();
        $deletedVariant->delete();
        $cases[] = $deletedVariant;

        foreach ($cases as $variant) {
            $this->actingAs($customer)
                ->from(route('cart.index'))
                ->post(route('cart.items.store'), ['product_variant_id' => $variant->id, 'quantity' => 1])
                ->assertStatus($variant->trashed() ? 404 : 302);
        }

        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_quantity_stock_and_max_validation_are_enforced_on_add_and_update(): void
    {
        config(['marketplace.cart.max_quantity_per_item' => 5]);
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $variant = $this->createPublicProduct('Quantity Cart Product', variants: [['sku' => 'QTY-CART', 'base_price' => 10000, 'stock' => 4]])->variants()->firstOrFail();

        $this->actingAs($customer)
            ->post(route('cart.items.store'), ['product_variant_id' => $variant->id, 'quantity' => 6])
            ->assertSessionHasErrors('quantity');

        $this->actingAs($customer)
            ->post(route('cart.items.store'), ['product_variant_id' => $variant->id, 'quantity' => 5])
            ->assertSessionHasErrors('quantity');

        $this->actingAs($customer)
            ->post(route('cart.items.store'), ['product_variant_id' => $variant->id, 'quantity' => 3])
            ->assertRedirect();

        $item = CartItem::query()->firstOrFail();

        $this->actingAs($customer)
            ->patch(route('cart.items.update', $item), ['quantity' => 5])
            ->assertSessionHasErrors('quantity');

        $this->assertSame(3, $item->refresh()->quantity);
    }

    public function test_cart_page_groups_multiple_vendors_and_uses_current_effective_prices_and_totals(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $techVerse = Vendor::factory()->approved()->create(['display_name' => 'TechVerse', 'slug' => 'techverse']);
        $homeCraft = Vendor::factory()->approved()->create(['display_name' => 'HomeCraft', 'slug' => 'homecraft']);
        $phone = $this->createPublicProduct('Cart Phone', vendor: $techVerse, variants: [['sku' => 'CART-PHONE', 'base_price' => 10000, 'sale_price' => 8000, 'stock' => 10]]);
        $mixer = $this->createPublicProduct('Cart Mixer', vendor: $homeCraft, variants: [['sku' => 'CART-MIXER', 'base_price' => 5000, 'stock' => 10]]);
        $cart = Cart::factory()->customer()->create(['user_id' => $customer->id]);

        CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $phone->variants()->first()->id, 'quantity' => 2]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $mixer->variants()->first()->id, 'quantity' => 3]);

        $this->actingAs($customer)
            ->get(route('cart.index'))
            ->assertOk()
            ->assertSeeText('TechVerse')
            ->assertSeeText('HomeCraft')
            ->assertSeeText('Cart Phone')
            ->assertSeeText('Cart Mixer')
            ->assertSeeText('USD 80.00')
            ->assertSeeText('USD 160.00')
            ->assertSeeText('USD 150.00')
            ->assertSeeText('USD 310.00')
            ->assertSeeText('Shipping and taxes are calculated at checkout.')
            ->assertSee(route('checkout.index'), false)
            ->assertSeeText('Proceed to Checkout');
    }

    public function test_cart_page_marks_existing_invalid_items_without_deleting_or_leaking_private_data(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $publicProduct = $this->createPublicProduct('Still Public Product', variants: [['sku' => 'STILL-PUBLIC', 'base_price' => 10000, 'stock' => 0]]);
        $privateProduct = $this->createPublicProduct('Private Cart Product', productState: ['rejection_reason' => 'SECRET CART REASON']);
        $inactiveVariantProduct = $this->createPublicProduct('Inactive Option Product', productState: ['type' => ProductType::Variant], variants: [
            ['sku' => 'INACTIVE-OPTION', 'base_price' => 10000, 'stock' => 4],
            ['sku' => 'ACTIVE-OPTION', 'base_price' => 10000, 'stock' => 4],
        ]);
        $cart = Cart::factory()->customer()->create(['user_id' => $customer->id]);

        CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $publicProduct->variants()->first()->id, 'quantity' => 1]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $privateProduct->variants()->first()->id, 'quantity' => 1]);
        $inactiveVariant = $inactiveVariantProduct->variants()->where('sku', 'INACTIVE-OPTION')->first();
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_variant_id' => $inactiveVariant->id, 'quantity' => 1]);

        $privateProduct->forceFill(['approval_status' => ProductApprovalStatus::Pending])->save();
        $inactiveVariant->update(['is_active' => false]);

        $this->actingAs($customer)
            ->get(route('cart.index'))
            ->assertOk()
            ->assertSeeText('Out of Stock')
            ->assertSeeText('This item is no longer available.')
            ->assertSeeText('This option is no longer available.')
            ->assertDontSeeText('Private Cart Product')
            ->assertDontSeeText('SECRET CART REASON');

        $this->assertSame(3, CartItem::query()->count());
    }

    public function test_remove_clear_and_inventory_neutrality(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $variant = $this->createPublicProduct('Neutral Cart Product', variants: [['sku' => 'NEUTRAL-CART', 'base_price' => 10000, 'stock' => 7]])->variants()->firstOrFail();
        $inventory = $variant->inventory()->firstOrFail();
        $movementCount = InventoryMovement::query()->count();

        $this->actingAs($customer)->post(route('cart.items.store'), ['product_variant_id' => $variant->id, 'quantity' => 2])->assertRedirect();
        $item = CartItem::query()->firstOrFail();
        $this->actingAs($customer)->patch(route('cart.items.update', $item), ['quantity' => 3])->assertRedirect();
        $this->actingAs($customer)->delete(route('cart.items.destroy', $item))->assertRedirect();
        $this->actingAs($customer)->post(route('cart.items.store'), ['product_variant_id' => $variant->id, 'quantity' => 1])->assertRedirect();
        $this->actingAs($customer)->delete(route('cart.clear'))->assertRedirect();

        $this->assertSame(0, CartItem::query()->count());
        $this->assertSame(7, $inventory->refresh()->on_hand);
        $this->assertSame(0, $inventory->reserved);
        $this->assertSame($movementCount, InventoryMovement::query()->count());
    }

    public function test_guest_cart_merges_into_customer_cart_on_login_and_registration(): void
    {
        config(['marketplace.cart.max_quantity_per_item' => 5]);
        $customer = User::factory()->create(['role' => UserRole::Customer, 'email' => 'merge@example.com', 'password' => bcrypt('password')]);
        $first = $this->createPublicProduct('Merge Product A', variants: [['sku' => 'MERGE-A', 'base_price' => 10000, 'stock' => 10]])->variants()->firstOrFail();
        $second = $this->createPublicProduct('Merge Product B', variants: [['sku' => 'MERGE-B', 'base_price' => 10000, 'stock' => 10]])->variants()->firstOrFail();
        $customerCart = Cart::factory()->customer()->create(['user_id' => $customer->id]);
        CartItem::factory()->create(['cart_id' => $customerCart->id, 'product_variant_id' => $first->id, 'quantity' => 3]);

        $guestCart = Cart::factory()->create(['token' => 'guest-merge-token']);
        CartItem::factory()->create(['cart_id' => $guestCart->id, 'product_variant_id' => $first->id, 'quantity' => 4]);
        CartItem::factory()->create(['cart_id' => $guestCart->id, 'product_variant_id' => $second->id, 'quantity' => 2]);

        $this->withSession([CartResolver::SESSION_KEY => 'guest-merge-token'])
            ->post(route('login'), ['email' => 'merge@example.com', 'password' => 'password'])
            ->assertRedirect();

        $this->assertDatabaseHas('cart_items', ['cart_id' => $customerCart->id, 'product_variant_id' => $first->id, 'quantity' => 5]);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $customerCart->id, 'product_variant_id' => $second->id, 'quantity' => 2]);
        $this->assertDatabaseMissing('carts', ['id' => $guestCart->id]);

        auth()->guard('web')->logout();

        $registerVariant = $this->createPublicProduct('Register Merge Product')->variants()->firstOrFail();
        $registerGuestCart = Cart::factory()->create(['token' => 'register-merge-token']);
        CartItem::factory()->create(['cart_id' => $registerGuestCart->id, 'product_variant_id' => $registerVariant->id, 'quantity' => 2]);

        $this->withSession([CartResolver::SESSION_KEY => 'register-merge-token'])
            ->post(route('register'), [
                'name' => 'Register Customer',
                'email' => 'register-merge@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertRedirect(route('account.dashboard'));

        $newUser = User::query()->where('email', 'register-merge@example.com')->firstOrFail();
        $this->assertDatabaseHas('carts', ['user_id' => $newUser->id, 'token' => null]);
        $this->assertDatabaseHas('cart_items', ['product_variant_id' => $registerVariant->id, 'quantity' => 2]);
        $this->assertDatabaseMissing('carts', ['id' => $registerGuestCart->id]);
    }

    public function test_customer_cart_persists_after_logout_and_relogin(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer, 'email' => 'persist@example.com', 'password' => bcrypt('password')]);
        $variant = $this->createPublicProduct('Persistent Cart Product')->variants()->firstOrFail();

        $this->actingAs($customer)->post(route('cart.items.store'), ['product_variant_id' => $variant->id, 'quantity' => 2])->assertRedirect();
        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->get(route('cart.index'))->assertOk()->assertSeeText('Your cart is empty.');

        $this->post(route('login'), ['email' => 'persist@example.com', 'password' => 'password'])->assertRedirect();

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSeeText('Persistent Cart Product')
            ->assertSeeText('2 items');
    }

    public function test_cart_seeder_is_repeatable(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $customer = User::query()->where('email', 'customer@example.com')->firstOrFail();
        $cart = Cart::query()->where('user_id', $customer->id)->firstOrFail();

        $this->assertSame(4, $cart->items()->count());
        $this->assertSame(5, (int) $cart->items()->sum('quantity'));
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
                'combination_key' => 'cart-'.$index,
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
}
