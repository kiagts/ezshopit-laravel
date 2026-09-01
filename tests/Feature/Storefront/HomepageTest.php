<?php

namespace Tests\Feature\Storefront;

use App\Enums\ProductApprovalStatus;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_for_guests(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSeeText('Shop by Category')
            ->assertSeeText('Sign In')
            ->assertSeeText('Create Account')
            ->assertSeeText('Sell With Us');
    }

    public function test_homepage_shows_only_publicly_visible_products(): void
    {
        $visible = $this->createPublicProduct('Visible Marketplace Product');
        $this->createPublicProduct('Draft Marketplace Product', productState: ['status' => ProductStatus::Draft]);
        $this->createPublicProduct('Pending Marketplace Product', productState: ['approval_status' => ProductApprovalStatus::Pending]);
        $this->createPublicProduct('Inactive Vendor Product', vendorState: ['status' => VendorStatus::Suspended]);

        $inactiveParent = Category::factory()->inactive()->create(['name' => 'Hidden Parent']);
        $inactiveChild = Category::factory()->childOf($inactiveParent)->create(['name' => 'Hidden Child']);
        $this->createPublicProduct('Inactive Category Product', category: $inactiveChild);

        $visible->variants()->update(['is_active' => false]);

        $this->createPublicProduct('Visible Product With Variant');

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSeeText('Visible Marketplace Product')
            ->assertSeeText('Visible Product With Variant')
            ->assertDontSeeText('Draft Marketplace Product')
            ->assertDontSeeText('Pending Marketplace Product')
            ->assertDontSeeText('Inactive Vendor Product')
            ->assertDontSeeText('Inactive Category Product');
    }

    public function test_homepage_category_navigation_uses_active_root_categories_and_children(): void
    {
        $root = Category::factory()->create(['name' => 'Electronics', 'sort_order' => 1]);
        Category::factory()->childOf($root)->create(['name' => 'Mobiles']);
        Category::factory()->childOf($root)->inactive()->create(['name' => 'Hidden Accessories']);
        Category::factory()->inactive()->create(['name' => 'Inactive Department']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSeeText('Electronics')
            ->assertSeeText('Mobiles')
            ->assertDontSeeText('Hidden Accessories')
            ->assertDontSeeText('Inactive Department');
    }

    public function test_homepage_shows_only_approved_featured_stores(): void
    {
        Vendor::factory()->approved()->create(['display_name' => 'Approved Storefront Seller']);
        Vendor::factory()->create(['display_name' => 'Draft Storefront Seller']);
        Vendor::factory()->state(['status' => VendorStatus::Suspended])->create(['display_name' => 'Suspended Storefront Seller']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSeeText('Approved Storefront Seller')
            ->assertDontSeeText('Draft Storefront Seller')
            ->assertDontSeeText('Suspended Storefront Seller');
    }

    public function test_stock_state_is_public_but_quantities_are_not_exposed(): void
    {
        $this->createPublicProduct('Low Stock Storefront Product', inventoryState: [
            'on_hand' => 37,
            'reserved' => 34,
            'low_stock_threshold' => 5,
        ]);

        $this->createPublicProduct('Out Of Stock Storefront Product', inventoryState: [
            'on_hand' => 41,
            'reserved' => 41,
            'low_stock_threshold' => 5,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSeeText('Low Stock Storefront Product')
            ->assertSeeText('Low stock')
            ->assertSeeText('Out Of Stock Storefront Product')
            ->assertSeeText('Out of stock')
            ->assertDontSeeText('37')
            ->assertDontSeeText('34')
            ->assertDontSeeText('41')
            ->assertDontSeeText('reserved')
            ->assertDontSeeText('on hand');
    }

    public function test_authenticated_header_links_to_the_users_role_dashboard(): void
    {
        $admin = User::factory()->create(['name' => 'Admin Example', 'role' => UserRole::Admin]);
        $customer = User::factory()->create(['name' => 'Customer Example', 'role' => UserRole::Customer]);
        $vendorUser = User::factory()->create(['name' => 'Vendor Example', 'role' => UserRole::Vendor]);
        Vendor::factory()->approved()->create(['user_id' => $vendorUser->id]);

        $this->actingAs($admin)->get(route('home'))
            ->assertOk()
            ->assertSee('Hi, Admin')
            ->assertSee(route('admin.dashboard'), false);

        $this->actingAs($vendorUser)->get(route('home'))
            ->assertOk()
            ->assertSee('Hi, Vendor')
            ->assertSee(route('vendor.dashboard'), false);

        $this->actingAs($customer)->get(route('home'))
            ->assertOk()
            ->assertSee('Hi, Customer')
            ->assertSee(route('account.dashboard'), false);
    }

    public function test_storefront_pages_render_without_exceptions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $vendorUser = User::factory()->create(['role' => UserRole::Vendor]);
        Vendor::factory()->approved()->create(['user_id' => $vendorUser->id]);
        $customer = User::factory()->create(['role' => UserRole::Customer]);

        $this->get(route('home'))->assertOk();
        $this->get(route('login'))->assertOk();
        $this->get(route('register'))->assertOk();
        $this->actingAs($customer)->get(route('account.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($vendorUser)->get(route('vendor.dashboard'))->assertOk();
    }

    /**
     * @param array<string, mixed> $productState
     * @param array<string, mixed> $vendorState
     * @param array<string, mixed> $inventoryState
     */
    private function createPublicProduct(
        string $name,
        ?Category $category = null,
        array $productState = [],
        array $vendorState = [],
        array $inventoryState = [],
    ): Product {
        $category ??= Category::factory()->create();

        $vendor = Vendor::factory()
            ->approved()
            ->state($vendorState)
            ->create();

        $product = Product::factory()
            ->approved()
            ->state($productState)
            ->create([
                'name' => $name,
                'category_id' => $category->id,
                'vendor_id' => $vendor->id,
            ]);

        $variant = ProductVariant::factory()->default()->create([
            'product_id' => $product->id,
            'base_price' => 129900,
            'sale_price' => null,
        ]);

        Inventory::query()->where('product_variant_id', $variant->id)->update(array_merge([
            'on_hand' => 20,
            'reserved' => 0,
            'low_stock_threshold' => 5,
        ], $inventoryState));

        return $product;
    }
}
