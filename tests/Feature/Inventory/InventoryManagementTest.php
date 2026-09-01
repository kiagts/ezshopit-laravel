<?php

namespace Tests\Feature\Inventory;

use App\Actions\Inventory\AdjustInventory;
use App\Actions\Inventory\DecreaseInventory;
use App\Actions\Inventory\IncreaseInventory;
use App\Enums\InventoryMovementType;
use App\Enums\ProductApprovalStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_product_variant_gets_default_inventory(): void
    {
        $variant = ProductVariant::factory()->create();

        $this->assertNotNull($variant->fresh()->inventory);
        $this->assertSame(0, $variant->inventory->on_hand);
        $this->assertSame(0, $variant->inventory->reserved);
        $this->assertSame(config('marketplace.inventory.default_low_stock_threshold'), $variant->inventory->low_stock_threshold);
    }

    public function test_inventory_seeder_reconciles_missing_inventory_and_is_repeatable(): void
    {
        [$vendor, $product] = $this->product('missing-inventory-product');
        $variant = $product->defaultVariant;
        $variant->inventory()->delete();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\InventorySeeder']);
        $this->assertDatabaseHas('inventories', ['product_variant_id' => $variant->id]);

        $firstInventoryCount = Inventory::query()->count();
        $firstMovementCount = InventoryMovement::query()->count();

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\InventorySeeder']);

        $this->assertSame($firstInventoryCount, Inventory::query()->count());
        $this->assertSame($firstMovementCount, InventoryMovement::query()->count());
    }

    public function test_first_stock_increase_records_initial_movement_and_later_increase_records_increase(): void
    {
        [$vendor, $product] = $this->product();
        $variant = $product->defaultVariant;

        app(IncreaseInventory::class)->handle($variant, 5, $vendor->user, 'Opening stock');
        app(IncreaseInventory::class)->handle($variant, 4, $vendor->user, 'Restock');

        $this->assertSame(9, $variant->inventory->fresh()->on_hand);
        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $variant->id,
            'type' => InventoryMovementType::Initial->value,
            'quantity' => 5,
            'before_quantity' => 0,
            'after_quantity' => 5,
            'created_by' => $vendor->user_id,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $variant->id,
            'type' => InventoryMovementType::Increase->value,
            'quantity' => 4,
            'before_quantity' => 5,
            'after_quantity' => 9,
        ]);
    }

    public function test_decrease_respects_reserved_quantity_and_does_not_create_failed_movement(): void
    {
        [$vendor, $product] = $this->product();
        $inventory = $product->defaultVariant->inventory;
        $inventory->forceFill(['on_hand' => 10, 'reserved' => 2])->save();

        app(DecreaseInventory::class)->handle($product->defaultVariant, 5, $vendor->user, 'Manual reduction');

        $this->assertSame(5, $inventory->fresh()->on_hand);
        $this->assertSame(3, $inventory->fresh()->availableQuantity());
        $this->assertSame(-5, $inventory->movements()->latest('id')->first()->quantity);

        try {
            app(DecreaseInventory::class)->handle($product->defaultVariant, 4, $vendor->user, 'Too much');
        } catch (\Illuminate\Validation\ValidationException) {
        }

        $this->assertSame(5, $inventory->fresh()->on_hand);
        $this->assertSame(1, $inventory->movements()->count());
    }

    public function test_adjustment_requires_reason_over_http_and_rejects_below_reserved(): void
    {
        [$vendor, $product] = $this->product();
        $variant = $product->defaultVariant;
        $variant->inventory->forceFill(['on_hand' => 10, 'reserved' => 3])->save();

        $this->actingAs($vendor->user)
            ->from(route('vendor.products.inventory.show', $product))
            ->post(route('vendor.products.variants.inventory.adjust', [$product, $variant]), ['on_hand' => 7])
            ->assertRedirect(route('vendor.products.inventory.show', $product))
            ->assertSessionHasErrors('reason');

        $this->actingAs($vendor->user)
            ->from(route('vendor.products.inventory.show', $product))
            ->post(route('vendor.products.variants.inventory.adjust', [$product, $variant]), ['on_hand' => 2, 'reason' => 'Count'])
            ->assertRedirect(route('vendor.products.inventory.show', $product))
            ->assertSessionHasErrors('on_hand');

        app(AdjustInventory::class)->handle($variant, 7, $vendor->user, 'Physical count correction');

        $movement = $variant->inventory->movements()->latest('id')->first();
        $this->assertSame(InventoryMovementType::Adjustment, $movement->type);
        $this->assertSame(-3, $movement->quantity);
        $this->assertSame(10, $movement->before_quantity);
        $this->assertSame(7, $movement->after_quantity);
    }

    public function test_threshold_update_creates_no_inventory_movement(): void
    {
        [$vendor, $product] = $this->product();
        $variant = $product->defaultVariant;

        $this->actingAs($vendor->user)
            ->put(route('vendor.products.variants.inventory.threshold', [$product, $variant]), ['low_stock_threshold' => 10])
            ->assertRedirect();

        $this->assertSame(10, $variant->inventory->fresh()->low_stock_threshold);
        $this->assertSame(0, $variant->inventory->movements()->count());
    }

    public function test_available_quantity_and_stock_status_rules(): void
    {
        $inventory = Inventory::factory()->create(['on_hand' => 20, 'reserved' => 7, 'low_stock_threshold' => 5]);
        $this->assertSame(13, $inventory->availableQuantity());
        $this->assertTrue($inventory->isInStock());

        $inventory->forceFill(['on_hand' => 5, 'reserved' => 0, 'low_stock_threshold' => 5])->save();
        $this->assertTrue($inventory->fresh()->isLowStock());

        $inventory->forceFill(['on_hand' => 1, 'reserved' => 0, 'low_stock_threshold' => 5])->save();
        $this->assertTrue($inventory->fresh()->isLowStock());

        $inventory->forceFill(['on_hand' => 0, 'reserved' => 0, 'low_stock_threshold' => 5])->save();
        $this->assertTrue($inventory->fresh()->isOutOfStock());

        $inventory->forceFill(['on_hand' => 1, 'reserved' => 0, 'low_stock_threshold' => 0])->save();
        $this->assertTrue($inventory->fresh()->isInStock());
    }

    public function test_vendor_ownership_and_product_variant_scoping_are_enforced(): void
    {
        [$owner, $product] = $this->product('owner-inventory-product');
        [$otherVendor, $otherProduct] = $this->product('other-inventory-product');
        $variant = $product->defaultVariant;
        $otherVariant = $otherProduct->defaultVariant;

        $this->actingAs($otherVendor->user)->get(route('vendor.products.inventory.show', $product))->assertForbidden();
        $this->actingAs($otherVendor->user)->post(route('vendor.products.variants.inventory.increase', [$product, $variant]), ['quantity' => 1])->assertForbidden();
        $this->actingAs($otherVendor->user)->post(route('vendor.products.variants.inventory.decrease', [$product, $variant]), ['quantity' => 1])->assertForbidden();
        $this->actingAs($otherVendor->user)->post(route('vendor.products.variants.inventory.adjust', [$product, $variant]), ['on_hand' => 1, 'reason' => 'Audit'])->assertForbidden();
        $this->actingAs($otherVendor->user)->get(route('vendor.products.variants.inventory.history', [$product, $variant]))->assertForbidden();

        $this->actingAs($owner->user)
            ->post(route('vendor.products.variants.inventory.increase', [$product, $otherVariant]), ['quantity' => 1])
            ->assertNotFound();
    }

    public function test_vendor_status_and_product_lifecycle_rules(): void
    {
        foreach ([ProductApprovalStatus::Draft, ProductApprovalStatus::Pending, ProductApprovalStatus::Rejected, ProductApprovalStatus::Approved] as $approvalStatus) {
            [$vendor, $product] = $this->product('lifecycle-'.$approvalStatus->value);
            $product->forceFill([
                'approval_status' => $approvalStatus,
                'status' => $approvalStatus === ProductApprovalStatus::Approved ? ProductStatus::Active : ProductStatus::Draft,
            ])->save();

            $this->actingAs($vendor->user)
                ->post(route('vendor.products.variants.inventory.increase', [$product, $product->defaultVariant]), ['quantity' => 1])
                ->assertRedirect();
        }

        [$vendor, $archived] = $this->product('archived-inventory-product');
        $archived->forceFill(['status' => ProductStatus::Archived])->save();
        $this->actingAs($vendor->user)
            ->post(route('vendor.products.variants.inventory.increase', [$archived, $archived->defaultVariant]), ['quantity' => 1])
            ->assertRedirect()
            ->assertSessionHasErrors('inventory');

        $pending = $this->vendor(VendorStatus::Pending);
        $this->actingAs($pending->user)->get(route('vendor.inventory.index'))->assertRedirect('/vendor/application-status');
    }

    public function test_stock_changes_do_not_reset_product_approval_or_status(): void
    {
        [$vendor, $product] = $this->product('approved-stock-product');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $product->forceFill([
            'status' => ProductStatus::Active,
            'approval_status' => ProductApprovalStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ])->save();

        $this->actingAs($vendor->user)
            ->post(route('vendor.products.variants.inventory.increase', [$product, $product->defaultVariant]), ['quantity' => 3])
            ->assertRedirect();

        $product->refresh();
        $this->assertSame(ProductApprovalStatus::Approved, $product->approval_status);
        $this->assertSame(ProductStatus::Active, $product->status);
        $this->assertNotNull($product->approved_at);
        $this->assertSame($admin->id, $product->approved_by);
    }

    public function test_inventory_pages_render_and_filter(): void
    {
        [$vendor, $product] = $this->product('render-inventory-product');
        $variant = $product->defaultVariant;
        $variant->inventory->forceFill(['on_hand' => 2, 'reserved' => 0, 'low_stock_threshold' => 5])->save();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($vendor->user)->get(route('vendor.inventory.index'))->assertOk()->assertSee('Low Stock')->assertSee($variant->sku);
        $this->actingAs($vendor->user)->get(route('vendor.inventory.index', ['q' => $variant->sku, 'status' => 'low_stock']))->assertOk()->assertSee($product->name);
        $this->actingAs($vendor->user)->get(route('vendor.products.inventory.show', $product))->assertOk()->assertSee('Add Stock');
        $this->actingAs($vendor->user)->get(route('vendor.products.variants.inventory.history', [$product, $variant]))->assertOk()->assertSee('Inventory History');
        $this->actingAs($admin)->get(route('admin.inventory.index'))->assertOk()->assertSee($product->name);
    }

    public function test_no_vendor_routes_exist_to_edit_or_delete_inventory_movements(): void
    {
        $routes = collect(Route::getRoutes())->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())->implode("\n");

        $this->assertStringNotContainsString('inventory/movements/{movement}', $routes);
        $this->assertStringNotContainsString('inventory-movements/{movement}', $routes);
    }

    private function product(string $slug = 'inventory-product'): array
    {
        $vendor = $this->vendor(VendorStatus::Approved);
        $category = Category::factory()->create(['is_active' => true]);
        $brand = Brand::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => str($slug)->replace('-', ' ')->title()->toString(),
            'slug' => $slug.'-'.fake()->unique()->numberBetween(1000, 9999),
            'type' => ProductType::Simple,
            'status' => ProductStatus::Draft,
            'approval_status' => ProductApprovalStatus::Draft,
        ]);
        ProductVariant::factory()->default()->create([
            'product_id' => $product->id,
            'sku' => 'INV-'.fake()->unique()->numberBetween(1000, 9999),
        ]);

        return [$vendor, $product->refresh()->load('defaultVariant.inventory')];
    }

    private function vendor(VendorStatus $status): Vendor
    {
        $user = User::factory()->create([
            'role' => UserRole::Vendor,
            'password' => Hash::make('password'),
        ]);

        return Vendor::factory()->create([
            'user_id' => $user->id,
            'status' => $status,
        ]);
    }
}
