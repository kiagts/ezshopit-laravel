<?php

namespace Tests\Feature\Storefront;

use App\Enums\ProductApprovalStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WishlistItem;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_save_public_product_idempotently(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $product = $this->createPublicProduct('Wishlist Product');

        $this->actingAs($customer)
            ->post(route('wishlist.store', $product))
            ->assertRedirect();

        $this->actingAs($customer)
            ->post(route('wishlist.store', $product))
            ->assertRedirect();

        $this->assertDatabaseHas('wishlist_items', [
            'user_id' => $customer->id,
            'product_id' => $product->id,
        ]);
        $this->assertSame(1, WishlistItem::query()->where('user_id', $customer->id)->where('product_id', $product->id)->count());
    }

    public function test_guest_vendor_and_admin_cannot_mutate_customer_wishlist(): void
    {
        $product = $this->createPublicProduct('Protected Wishlist Product');
        $vendorUser = User::factory()->create(['role' => UserRole::Vendor]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->post(route('wishlist.store', $product))->assertRedirect(route('login'));

        $this->actingAs($vendorUser)
            ->post(route('wishlist.store', $product))
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete(route('wishlist.destroy', $product))
            ->assertForbidden();
    }

    public function test_customer_can_only_save_currently_public_products_but_out_of_stock_is_allowed(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $category = Category::factory()->create();
        $inactiveParent = Category::factory()->inactive()->create();
        $hiddenChild = Category::factory()->childOf($inactiveParent)->create();

        $public = $this->createPublicProduct('Public Save', category: $category);
        $outOfStock = $this->createPublicProduct('Out Of Stock Save', category: $category, variants: [['sku' => 'OOS-SAVE', 'base_price' => 10000, 'stock' => 0]]);
        $draft = $this->createPublicProduct('Draft Save', category: $category, productState: ['status' => ProductStatus::Draft]);
        $pending = $this->createPublicProduct('Pending Save', category: $category, productState: ['approval_status' => ProductApprovalStatus::Pending]);
        $rejected = $this->createPublicProduct('Rejected Save', category: $category, productState: ['approval_status' => ProductApprovalStatus::Rejected]);
        $inactive = $this->createPublicProduct('Inactive Save', category: $category, productState: ['status' => ProductStatus::Inactive]);
        $archived = $this->createPublicProduct('Archived Save', category: $category, productState: ['status' => ProductStatus::Archived]);
        $suspendedVendor = $this->createPublicProduct('Suspended Vendor Save', category: $category, vendorState: ['status' => VendorStatus::Suspended]);
        $inactiveCategory = $this->createPublicProduct('Inactive Category Save', category: Category::factory()->inactive()->create());
        $inactiveAncestor = $this->createPublicProduct('Inactive Ancestor Save', category: $hiddenChild);

        foreach ([$public, $outOfStock] as $product) {
            $this->actingAs($customer)
                ->post(route('wishlist.store', $product))
                ->assertRedirect();
        }

        foreach ([$draft, $pending, $rejected, $inactive, $archived, $suspendedVendor, $inactiveCategory, $inactiveAncestor] as $product) {
            $this->actingAs($customer)
                ->post(route('wishlist.store', $product))
                ->assertNotFound();
        }

        $this->assertSame(2, WishlistItem::query()->where('user_id', $customer->id)->count());
    }

    public function test_customer_can_remove_only_their_own_saved_product(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $otherCustomer = User::factory()->create(['role' => UserRole::Customer]);
        $product = $this->createPublicProduct('Shared Saved Product');

        WishlistItem::factory()->create(['user_id' => $customer->id, 'product_id' => $product->id]);
        WishlistItem::factory()->create(['user_id' => $otherCustomer->id, 'product_id' => $product->id]);

        $this->actingAs($customer)
            ->delete(route('wishlist.destroy', $product))
            ->assertRedirect();

        $this->assertDatabaseMissing('wishlist_items', [
            'user_id' => $customer->id,
            'product_id' => $product->id,
        ]);
        $this->assertDatabaseHas('wishlist_items', [
            'user_id' => $otherCustomer->id,
            'product_id' => $product->id,
        ]);

        $this->actingAs($customer)
            ->delete(route('wishlist.destroy', $product))
            ->assertRedirect();
    }

    public function test_wishlist_index_shows_customers_saved_products_recent_first_and_paginates(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $otherCustomer = User::factory()->create(['role' => UserRole::Customer]);
        $oldProduct = $this->createPublicProduct('Older Saved Product', variants: [['sku' => 'OLDER-SAVED', 'base_price' => 12345, 'stock' => 4]]);
        $newProduct = $this->createPublicProduct('Newest Saved Product');
        $otherProduct = $this->createPublicProduct('Other Customer Saved Product');

        WishlistItem::factory()->create(['user_id' => $customer->id, 'product_id' => $oldProduct->id, 'created_at' => now()->subMinute()]);
        WishlistItem::factory()->create(['user_id' => $customer->id, 'product_id' => $newProduct->id, 'created_at' => now()]);
        WishlistItem::factory()->create(['user_id' => $otherCustomer->id, 'product_id' => $otherProduct->id]);

        foreach (range(1, 24) as $index) {
            WishlistItem::factory()->create([
                'user_id' => $customer->id,
                'product_id' => $this->createPublicProduct('Paged Wishlist Product '.$index)->id,
                'created_at' => now()->subMinutes($index + 2),
            ]);
        }

        $this->actingAs($customer)
            ->get(route('account.wishlist.index'))
            ->assertOk()
            ->assertSeeText('Saved Products')
            ->assertSeeTextInOrder(['Newest Saved Product', 'Older Saved Product'])
            ->assertSeeText('USD 123.45')
            ->assertSee('page=2', false)
            ->assertDontSeeText('Other Customer Saved Product');
    }

    public function test_wishlist_index_handles_out_of_stock_and_unavailable_products_safely(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $outOfStock = $this->createPublicProduct('Saved Sold Out Product', variants: [['sku' => 'SAVED-SOLD-OUT', 'base_price' => 10000, 'stock' => 0]]);
        $unavailable = $this->createPublicProduct('Private Moderation Name', productState: ['rejection_reason' => 'SECRET-REJECTION']);

        WishlistItem::factory()->create(['user_id' => $customer->id, 'product_id' => $outOfStock->id]);
        WishlistItem::factory()->create(['user_id' => $customer->id, 'product_id' => $unavailable->id]);
        $unavailable->forceFill(['approval_status' => ProductApprovalStatus::Pending])->save();

        $this->actingAs($customer)
            ->get(route('account.wishlist.index'))
            ->assertOk()
            ->assertSeeText('Saved Sold Out Product')
            ->assertSeeText('Out of stock')
            ->assertSeeText('This product is currently unavailable.')
            ->assertDontSeeText('Private Moderation Name')
            ->assertDontSeeText('SECRET-REJECTION');
    }

    public function test_product_detail_header_and_cards_show_wishlist_state(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $savedProduct = $this->createPublicProduct('Saved State Product');
        $unsavedProduct = $this->createPublicProduct('Unsaved State Product');
        WishlistItem::factory()->create(['user_id' => $customer->id, 'product_id' => $savedProduct->id]);

        $this->actingAs($customer)
            ->get(route('storefront.products.show', $savedProduct))
            ->assertOk()
            ->assertSeeText('Saved')
            ->assertSee(route('wishlist.destroy', $savedProduct), false);

        $this->actingAs($customer)
            ->get(route('storefront.products.show', $unsavedProduct))
            ->assertOk()
            ->assertSeeText('Save')
            ->assertSee(route('wishlist.store', $unsavedProduct), false);

        $this->actingAs($customer)
            ->get(route('home'))
            ->assertOk()
            ->assertSeeText('Wishlist')
            ->assertSee('>1<', false)
            ->assertSee(route('wishlist.destroy', $savedProduct), false);

        auth()->guard('web')->logout();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('login'), false)
            ->assertDontSee('>1<', false);
    }

    public function test_wishlist_changes_do_not_touch_inventory_or_product_moderation(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $product = $this->createPublicProduct('Inventory Neutral Save', variants: [['sku' => 'INV-NEUTRAL', 'base_price' => 10000, 'stock' => 7]]);
        $variant = $product->variants()->firstOrFail();
        $inventory = $variant->inventory()->firstOrFail();

        $movementCount = InventoryMovement::query()->count();

        $this->actingAs($customer)->post(route('wishlist.store', $product))->assertRedirect();
        $this->actingAs($customer)->delete(route('wishlist.destroy', $product))->assertRedirect();

        $inventory->refresh();
        $product->refresh();

        $this->assertSame(7, $inventory->on_hand);
        $this->assertSame(0, $inventory->reserved);
        $this->assertSame($movementCount, InventoryMovement::query()->count());
        $this->assertSame(ProductApprovalStatus::Approved, $product->approval_status);
        $this->assertSame(ProductStatus::Active, $product->status);
    }

    public function test_wishlist_seeder_is_repeatable(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $customer = User::query()->where('email', 'customer@example.com')->firstOrFail();

        $this->assertSame(
            WishlistItem::query()->where('user_id', $customer->id)->count(),
            WishlistItem::query()->where('user_id', $customer->id)->distinct('product_id')->count('product_id'),
        );
        $this->assertGreaterThanOrEqual(3, WishlistItem::query()->where('user_id', $customer->id)->count());
    }

    /**
     * @param array<int, array<string, mixed>> $variants
     * @param array<string, mixed> $productState
     * @param array<string, mixed> $vendorState
     */
    private function createPublicProduct(
        string $name,
        ?Category $category = null,
        ?Brand $brand = null,
        ?Vendor $vendor = null,
        array $variants = [],
        array $productState = [],
        array $vendorState = [],
    ): Product {
        $category ??= Category::factory()->create();
        $brand ??= Brand::factory()->create();
        $vendor ??= Vendor::factory()->approved()->state($vendorState)->create();

        $product = Product::factory()
            ->approved()
            ->state($productState)
            ->create([
                'name' => $name,
                'type' => $productState['type'] ?? ProductType::Simple,
                'category_id' => $category->id,
                'brand_id' => $brand->id,
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
                'combination_key' => 'wishlist-'.$index,
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
