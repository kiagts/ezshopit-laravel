<?php

namespace Tests\Feature\Product;

use App\Actions\Products\CreateProduct;
use App\Actions\Products\GenerateVariantCombinationKey;
use App\Enums\ProductApprovalStatus;
use App\Enums\ProductAttributeInputType;
use App\Enums\ProductAttributeType;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeSet;
use App\Models\ProductAttributeValue;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_product_routes_are_role_and_status_protected(): void
    {
        $approved = $this->vendor(VendorStatus::Approved);
        $pending = $this->vendor(VendorStatus::Pending);
        $rejected = $this->vendor(VendorStatus::Rejected);
        $suspended = $this->vendor(VendorStatus::Suspended);
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($approved->user)->get('/vendor/products')->assertOk();
        $this->actingAs($pending->user)->get('/vendor/products')->assertRedirect('/vendor/application-status');
        $this->actingAs($rejected->user)->get('/vendor/products')->assertRedirect('/vendor/application-status');
        $this->actingAs($suspended->user)->get('/vendor/products')->assertRedirect('/vendor/application-status');
        $this->actingAs($customer)->get('/vendor/products')->assertForbidden();
        $this->actingAs($admin)->get('/vendor/products')->assertForbidden();
        auth()->logout();
        $this->get('/vendor/products')->assertRedirect('/login');
    }

    public function test_vendor_cannot_manage_another_vendor_product(): void
    {
        [$owner, $category, $brand] = $this->baseCatalog();
        $intruder = $this->vendor(VendorStatus::Approved);
        $product = app(CreateProduct::class)->handle($owner, $this->simplePayload($category, $brand));

        $this->actingAs($intruder->user)->get("/vendor/products/{$product->id}/edit")->assertForbidden();
        $this->actingAs($intruder->user)->put("/vendor/products/{$product->id}", $this->simplePayload($category, $brand, ['name' => 'Stolen Product']))->assertForbidden();
        $this->actingAs($intruder->user)->post("/vendor/products/{$product->id}/submit")->assertForbidden();
        $this->actingAs($intruder->user)->delete("/vendor/products/{$product->id}")->assertForbidden();
    }

    public function test_product_create_page_lists_only_usable_categories_and_active_brands(): void
    {
        $vendor = $this->vendor(VendorStatus::Approved);
        $parent = Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);
        $leaf = Category::factory()->childOf($parent)->create(['name' => 'Smartphones', 'slug' => 'smartphones']);
        $inactiveLeaf = Category::factory()->inactive()->create(['name' => 'Inactive Leaf']);
        $activeBrand = Brand::factory()->create(['name' => 'Active Brand', 'is_active' => true]);
        $inactiveBrand = Brand::factory()->inactive()->create(['name' => 'Inactive Brand']);

        $response = $this->actingAs($vendor->user)->get('/vendor/products/create');

        $response->assertOk()
            ->assertSee('Electronics / Smartphones')
            ->assertDontSee('Inactive Leaf')
            ->assertSee($activeBrand->name)
            ->assertDontSee($inactiveBrand->name)
            ->assertSee('Simple Product')
            ->assertSee('Product With Variants');
    }

    public function test_category_configuration_endpoint_returns_public_attribute_configuration(): void
    {
        [$vendor, $category, , $attrs, $values] = $this->variantCatalog();
        $parent = Category::factory()->create();
        Category::factory()->childOf($parent)->create();
        $inactive = Category::factory()->inactive()->create();

        $response = $this->actingAs($vendor->user)->getJson("/vendor/catalog/categories/{$category->id}/configuration");

        $response->assertOk()
            ->assertJsonPath('variant_attributes.0.name', 'Color')
            ->assertJsonPath('variant_attributes.0.is_required', true)
            ->assertJsonPath('variant_attributes.0.values.0.value', 'Black')
            ->assertJsonPath('specification_attributes.0.name', 'Processor');

        $this->actingAs($vendor->user)->getJson("/vendor/catalog/categories/{$parent->id}/configuration")->assertNotFound();
        $this->actingAs($vendor->user)->getJson("/vendor/catalog/categories/{$inactive->id}/configuration")->assertNotFound();

        $this->assertSame($attrs['color']->id, $response->json('variant_attributes.0.id'));
        $this->assertSame($values['black']->id, $response->json('variant_attributes.0.values.0.id'));
    }

    public function test_vendor_can_create_simple_product_with_decimal_prices_and_specifications(): void
    {
        [$vendor, $category, $brand, $processor] = $this->specCatalog();

        $response = $this->actingAs($vendor->user)->post('/vendor/products', [
            ...$this->simpleHttpPayload($category, $brand),
            'variants' => [['sku' => 'simple-decimal', 'base_price' => '19.99', 'sale_price' => '9.99']],
            'specifications' => [$processor->id => ['text_value' => 'A19 Pro']],
            'role' => UserRole::Admin->value,
            'approval_status' => ProductApprovalStatus::Approved->value,
        ]);

        $product = Product::query()->where('slug', 'simple-http-product')->firstOrFail();

        $response->assertRedirect("/vendor/products/{$product->id}/edit");
        $this->assertSame($vendor->id, $product->vendor_id);
        $this->assertSame(ProductType::Simple, $product->type);
        $this->assertSame(ProductStatus::Draft, $product->status);
        $this->assertSame(ProductApprovalStatus::Draft, $product->approval_status);
        $this->assertSame('SIMPLE-DECIMAL', $product->defaultVariant->sku);
        $this->assertSame(1999, $product->defaultVariant->base_price);
        $this->assertSame(999, $product->defaultVariant->sale_price);
        $this->assertTrue($product->defaultVariant->attributeValues->isEmpty());
        $this->assertSame('A19 Pro', $product->specifications()->where('product_attribute_id', $processor->id)->firstOrFail()->text_value);
    }

    public function test_vendor_can_create_variant_product_and_tampering_is_rejected(): void
    {
        [$vendor, $category, $brand, $attrs, $values] = $this->variantCatalog();
        $payload = $this->variantHttpPayload($category, $brand, $attrs, $values);

        $this->actingAs($vendor->user)->post('/vendor/products', $payload)->assertRedirect();

        $product = Product::query()->where('slug', 'variant-http-product')->firstOrFail();
        $this->assertSame(4, $product->variants()->count());
        $this->assertTrue($product->variants()->where('is_default', true)->exists());

        $first = $product->variants()->where('sku', 'IPH-BLK-256')->firstOrFail();
        $this->assertSame(
            app(GenerateVariantCombinationKey::class)->handle([$attrs['color']->id => $values['black']->id, $attrs['storage']->id => $values['256gb']->id]),
            $first->combination_key,
        );

        $bad = $payload;
        $bad['slug'] = 'bad-variant-value';
        foreach ($bad['variants'] as $index => &$variant) {
            $variant['sku'] = 'BAD-VALUE-'.$index;
        }
        unset($variant);
        $bad['variants'][0]['attribute_value_ids'][$attrs['color']->id] = $values['256gb']->id;
        $this->actingAs($vendor->user)->from('/vendor/products/create')->post('/vendor/products', $bad)->assertRedirect('/vendor/products/create')->assertSessionHasErrors('variants.attribute_value_ids');

        $outside = ProductAttribute::factory()->variant()->create();
        $outsideValue = ProductAttributeValue::factory()->create(['product_attribute_id' => $outside->id]);
        $bad['slug'] = 'outside-variant-attribute';
        foreach ($bad['variants'] as $index => &$variant) {
            $variant['sku'] = 'OUTSIDE-'.$index;
        }
        unset($variant);
        $bad['variants'][0]['attribute_value_ids'] = [$outside->id => $outsideValue->id];
        $this->actingAs($vendor->user)->from('/vendor/products/create')->post('/vendor/products', $bad)->assertRedirect('/vendor/products/create')->assertSessionHasErrors('variants.attribute_value_ids');
    }

    public function test_draft_can_be_incomplete_but_submission_requires_readiness(): void
    {
        [$vendor, $category, $brand, $processor] = $this->specCatalog();

        $this->actingAs($vendor->user)->post('/vendor/products', $this->simpleHttpPayload($category, $brand, [
            'slug' => 'incomplete-draft',
        ]))->assertRedirect();

        $product = Product::query()->where('slug', 'incomplete-draft')->firstOrFail();
        $this->assertSame(ProductApprovalStatus::Draft, $product->approval_status);

        $this->actingAs($vendor->user)->post("/vendor/products/{$product->id}/submit")
            ->assertRedirect("/vendor/products/{$product->id}/edit")
            ->assertSessionHasErrors('readiness');
        $this->assertSame(ProductApprovalStatus::Draft, $product->fresh()->approval_status);

        $this->actingAs($vendor->user)->put("/vendor/products/{$product->id}", [
            ...$this->simpleHttpPayload($category, $brand, ['slug' => 'incomplete-draft']),
            'specifications' => [$processor->id => ['text_value' => 'A19 Pro']],
        ])->assertRedirect();
        $this->addPrimaryImage($product);

        $this->actingAs($vendor->user)->post("/vendor/products/{$product->id}/submit")->assertRedirect();
        $this->assertSame(ProductApprovalStatus::Pending, $product->fresh()->approval_status);
        $this->assertNotNull($product->fresh()->submitted_at);
    }

    public function test_pending_lock_admin_approval_rejection_and_resubmission(): void
    {
        [$vendor, $category, $brand, $processor] = $this->specCatalog();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $product = app(CreateProduct::class)->handle($vendor, [
            ...$this->simplePayload($category, $brand, ['slug' => 'moderation-product']),
            'specifications' => [$processor->id => ['text_value' => 'A19 Pro']],
        ]);
        $this->addPrimaryImage($product);

        $this->actingAs($vendor->user)->post("/vendor/products/{$product->id}/submit")->assertRedirect();
        $product->refresh();
        $this->assertSame(ProductApprovalStatus::Pending, $product->approval_status);

        $this->actingAs($vendor->user)->put("/vendor/products/{$product->id}", $this->simpleHttpPayload($category, $brand, ['slug' => 'moderation-product']))
            ->assertRedirect()
            ->assertSessionHasErrors('approval_status');
        $this->actingAs($vendor->user)->post("/vendor/products/{$product->id}/submit")
            ->assertRedirect()
            ->assertSessionHasErrors('approval_status');

        $this->actingAs($admin)->from("/admin/products/{$product->id}")->post("/admin/products/{$product->id}/reject", [])
            ->assertRedirect("/admin/products/{$product->id}")
            ->assertSessionHasErrors('rejection_reason');

        $this->actingAs($admin)->post("/admin/products/{$product->id}/reject", ['rejection_reason' => 'Please make the product description clearer.'])->assertRedirect();
        $product->refresh();
        $this->assertSame(ProductApprovalStatus::Rejected, $product->approval_status);
        $this->assertSame(ProductStatus::Draft, $product->status);
        $this->assertSame($admin->id, $product->rejected_by);

        $this->actingAs($vendor->user)->put("/vendor/products/{$product->id}", [
            ...$this->simpleHttpPayload($category, $brand, ['slug' => 'moderation-product', 'description' => 'A clearer description for review.']),
            'specifications' => [$processor->id => ['text_value' => 'A19 Pro']],
        ])->assertRedirect();
        $this->assertSame(ProductApprovalStatus::Rejected, $product->fresh()->approval_status);

        $this->actingAs($vendor->user)->post("/vendor/products/{$product->id}/submit")->assertRedirect();
        $this->assertSame(ProductApprovalStatus::Pending, $product->fresh()->approval_status);
        $this->assertNull($product->fresh()->rejection_reason);

        $this->actingAs($admin)->post("/admin/products/{$product->id}/approve")->assertRedirect();
        $product->refresh();
        $this->assertSame(ProductApprovalStatus::Approved, $product->approval_status);
        $this->assertSame($admin->id, $product->approved_by);
        $this->assertNull($product->rejected_by);
    }

    public function test_approved_content_edit_requires_reapproval_but_activation_does_not(): void
    {
        [$vendor, $category, $brand, $processor] = $this->specCatalog();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $product = app(CreateProduct::class)->handle($vendor, [
            ...$this->simplePayload($category, $brand, ['slug' => 'approved-edit-product']),
            'specifications' => [$processor->id => ['text_value' => 'A19 Pro']],
        ]);
        $this->addPrimaryImage($product);
        $product->forceFill([
            'status' => ProductStatus::Inactive,
            'approval_status' => ProductApprovalStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ])->save();

        $this->actingAs($vendor->user)->post("/vendor/products/{$product->id}/activate")->assertRedirect();
        $this->assertSame(ProductStatus::Active, $product->fresh()->status);
        $this->assertSame(ProductApprovalStatus::Approved, $product->fresh()->approval_status);

        $this->actingAs($vendor->user)->post("/vendor/products/{$product->id}/deactivate")->assertRedirect();
        $this->assertSame(ProductStatus::Inactive, $product->fresh()->status);
        $this->assertSame(ProductApprovalStatus::Approved, $product->fresh()->approval_status);

        $this->actingAs($vendor->user)->put("/vendor/products/{$product->id}", [
            ...$this->simpleHttpPayload($category, $brand, ['slug' => 'approved-edit-product', 'name' => 'Edited Approved Product']),
            'specifications' => [$processor->id => ['text_value' => 'A19 Pro']],
        ])->assertRedirect();

        $product->refresh();
        $this->assertSame(ProductApprovalStatus::Draft, $product->approval_status);
        $this->assertSame(ProductStatus::Draft, $product->status);
        $this->assertNull($product->approved_at);
        $this->assertNull($product->approved_by);
    }

    public function test_unapproved_product_cannot_activate_and_archive_sets_archived_status(): void
    {
        [$vendor, $category, $brand] = $this->baseCatalog();
        $product = app(CreateProduct::class)->handle($vendor, $this->simplePayload($category, $brand, ['slug' => 'archive-product']));

        $this->actingAs($vendor->user)->post("/vendor/products/{$product->id}/activate")
            ->assertRedirect()
            ->assertSessionHasErrors('approval_status');

        $this->actingAs($vendor->user)->delete("/vendor/products/{$product->id}")->assertRedirect('/vendor/products');
        $this->assertSame(ProductStatus::Archived, $product->fresh()->status);
        $this->assertFalse($product->fresh()->trashed());
    }

    public function test_variant_sync_preserves_existing_ids_and_soft_deletes_removed_combinations(): void
    {
        [$vendor, $category, $brand, $attrs, $values] = $this->variantCatalog();
        $product = app(CreateProduct::class)->handle($vendor, [
            ...$this->variantPayload($category, $brand, $attrs, $values),
            'slug' => 'sync-product',
        ]);
        $original = $product->variants()->pluck('id', 'combination_key');

        $blue = ProductAttributeValue::factory()->create(['product_attribute_id' => $attrs['color']->id, 'value' => 'Blue', 'code' => 'blue']);
        $payload = $this->variantHttpPayload($category, $brand, $attrs, ['black' => $values['black'], 'white' => $blue, '256gb' => $values['256gb'], '512gb' => $values['512gb']], [
            'slug' => 'sync-product',
        ]);
        $payload['variants'][0]['sku'] = 'SYNC-BLK-256';
        $payload['variants'][1]['sku'] = 'SYNC-BLK-512';
        $payload['variants'][2]['sku'] = 'SYNC-BLU-256';
        $payload['variants'][3]['sku'] = 'SYNC-BLU-512';

        $this->actingAs($vendor->user)->put("/vendor/products/{$product->id}", $payload)->assertRedirect();

        $keptKey = app(GenerateVariantCombinationKey::class)->handle([$attrs['color']->id => $values['black']->id, $attrs['storage']->id => $values['256gb']->id]);
        $removedKey = app(GenerateVariantCombinationKey::class)->handle([$attrs['color']->id => $values['white']->id, $attrs['storage']->id => $values['256gb']->id]);
        $blueKey = app(GenerateVariantCombinationKey::class)->handle([$attrs['color']->id => $blue->id, $attrs['storage']->id => $values['256gb']->id]);

        $this->assertSame($original[$keptKey], $product->variants()->where('combination_key', $keptKey)->firstOrFail()->id);
        $this->assertNotNull(ProductVariant::withTrashed()->where('product_id', $product->id)->where('combination_key', $removedKey)->firstOrFail()->deleted_at);
        $this->assertNotNull($product->variants()->where('combination_key', $blueKey)->first());
    }

    public function test_type_and_category_are_immutable_after_configuration(): void
    {
        [$vendor, $category, $brand] = $this->baseCatalog();
        $otherCategory = Category::factory()->create();
        $product = app(CreateProduct::class)->handle($vendor, $this->simplePayload($category, $brand, ['slug' => 'immutable-http']));

        $this->actingAs($vendor->user)->put("/vendor/products/{$product->id}", $this->simpleHttpPayload($category, $brand, [
            'slug' => 'immutable-http',
            'type' => ProductType::Variant->value,
        ]))->assertRedirect()->assertSessionHasErrors('type');

        $this->actingAs($vendor->user)->put("/vendor/products/{$product->id}", $this->simpleHttpPayload($otherCategory, $brand, [
            'slug' => 'immutable-http',
        ]))->assertRedirect()->assertSessionHasErrors('category_id');
    }

    public function test_money_parsing_is_precise_and_rejects_invalid_values(): void
    {
        $this->assertSame(1999, Money::parseDecimal('19.99'));
        $this->assertSame(129900, Money::parseDecimal('1299'));
        $this->assertSame(99, Money::parseDecimal('0.99'));
        $this->assertSame(10000, Money::parseDecimal('100.00'));

        foreach (['-1', '12.999', 'abc'] as $value) {
            try {
                Money::parseDecimal($value);
                $this->fail("Expected {$value} to be rejected.");
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_vendor_and_admin_listings_search_and_filter_products(): void
    {
        [$vendor, $category, $brand] = $this->baseCatalog();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $product = app(CreateProduct::class)->handle($vendor, $this->simplePayload($category, $brand, [
            'name' => 'Searchable Camera',
            'slug' => 'searchable-camera',
            'variants' => [['sku' => 'CAMERA-SKU', 'base_price' => 1000]],
        ]));
        $product->forceFill(['approval_status' => ProductApprovalStatus::Pending])->save();

        $this->actingAs($vendor->user)->get('/vendor/products?q=CAMERA-SKU')->assertOk()->assertSee('Searchable Camera');
        $this->actingAs($vendor->user)->get('/vendor/products?approval=pending')->assertOk()->assertSee('Searchable Camera');
        $this->actingAs($vendor->user)->get('/vendor/products?status=inactive')->assertOk()->assertDontSee('Searchable Camera');
        $this->actingAs($vendor->user)->get('/vendor/products?type=simple')->assertOk()->assertSee('Searchable Camera');

        $this->actingAs($admin)->get('/admin/products?q=Tech')->assertOk()->assertSee('Searchable Camera');
        $this->actingAs($admin)->get('/admin/products?approval=pending')->assertOk()->assertSee('Searchable Camera');
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
            'display_name' => 'Tech Test Store',
        ]);
    }

    private function baseCatalog(): array
    {
        return [
            $this->vendor(VendorStatus::Approved),
            Category::factory()->create(['is_active' => true]),
            Brand::factory()->create(['is_active' => true]),
        ];
    }

    private function specCatalog(): array
    {
        [$vendor, $category, $brand] = $this->baseCatalog();
        $set = ProductAttributeSet::factory()->create();
        $processor = ProductAttribute::factory()->create([
            'name' => 'Processor',
            'code' => 'processor-test',
            'type' => ProductAttributeType::Specification,
            'input_type' => ProductAttributeInputType::Text,
        ]);
        $set->attributes()->attach($processor->id, ['is_required' => true, 'sort_order' => 10]);
        $category->update(['product_attribute_set_id' => $set->id]);

        return [$vendor, $category, $brand, $processor];
    }

    private function variantCatalog(): array
    {
        [$vendor, $category, $brand] = $this->baseCatalog();
        $set = ProductAttributeSet::factory()->create();
        $category->update(['product_attribute_set_id' => $set->id]);
        $color = ProductAttribute::factory()->variant()->create(['name' => 'Color', 'code' => 'color-test']);
        $storage = ProductAttribute::factory()->variant()->create(['name' => 'Storage', 'code' => 'storage-test']);
        $processor = ProductAttribute::factory()->create(['name' => 'Processor', 'code' => 'processor-variant-test', 'type' => ProductAttributeType::Specification, 'input_type' => ProductAttributeInputType::Text]);
        $set->attributes()->attach($color->id, ['is_required' => true, 'sort_order' => 10]);
        $set->attributes()->attach($storage->id, ['is_required' => true, 'sort_order' => 20]);
        $set->attributes()->attach($processor->id, ['is_required' => false, 'sort_order' => 30]);
        $values = [
            'black' => ProductAttributeValue::factory()->create(['product_attribute_id' => $color->id, 'value' => 'Black', 'code' => 'black']),
            'white' => ProductAttributeValue::factory()->create(['product_attribute_id' => $color->id, 'value' => 'White', 'code' => 'white']),
            '256gb' => ProductAttributeValue::factory()->create(['product_attribute_id' => $storage->id, 'value' => '256GB', 'code' => '256gb']),
            '512gb' => ProductAttributeValue::factory()->create(['product_attribute_id' => $storage->id, 'value' => '512GB', 'code' => '512gb']),
        ];

        return [$vendor, $category, $brand, ['color' => $color, 'storage' => $storage, 'processor' => $processor], $values];
    }

    private function simplePayload(Category $category, Brand $brand, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Simple Product',
            'slug' => 'simple-product-'.fake()->unique()->numberBetween(1000, 9999),
            'type' => ProductType::Simple->value,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'short_description' => 'Short product summary.',
            'description' => 'A complete simple product description.',
            'variants' => [['sku' => 'SIMPLE-'.fake()->unique()->numberBetween(1000, 9999), 'base_price' => 1000]],
            'specifications' => [],
        ], $overrides);
    }

    private function simpleHttpPayload(Category $category, Brand $brand, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Simple HTTP Product',
            'slug' => 'simple-http-product',
            'type' => ProductType::Simple->value,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'short_description' => 'HTTP summary.',
            'description' => 'A complete HTTP simple product description.',
            'variants' => [['sku' => 'SIMPLE-HTTP-SKU', 'base_price' => '10.00', 'sale_price' => '']],
            'specifications' => [],
        ], $overrides);
    }

    private function variantPayload(Category $category, Brand $brand, array $attrs, array $values): array
    {
        $payload = $this->variantHttpPayload($category, $brand, $attrs, $values);
        $payload['variants'] = collect($payload['variants'])->map(function (array $variant): array {
            $variant['base_price'] = 1000;
            $variant['sale_price'] = null;

            return $variant;
        })->all();

        return $payload;
    }

    private function variantHttpPayload(Category $category, Brand $brand, array $attrs, array $values, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Variant HTTP Product',
            'slug' => 'variant-http-product',
            'type' => ProductType::Variant->value,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'short_description' => 'Variant summary.',
            'description' => 'A complete HTTP variant product description.',
            'variants' => [
                ['sku' => 'IPH-BLK-256', 'base_price' => '10.00', 'sale_price' => '', 'is_default' => '1', 'is_active' => '1', 'attribute_value_ids' => [$attrs['color']->id => $values['black']->id, $attrs['storage']->id => $values['256gb']->id]],
                ['sku' => 'IPH-BLK-512', 'base_price' => '12.00', 'sale_price' => '', 'is_default' => '0', 'is_active' => '1', 'attribute_value_ids' => [$attrs['color']->id => $values['black']->id, $attrs['storage']->id => $values['512gb']->id]],
                ['sku' => 'IPH-WHT-256', 'base_price' => '10.00', 'sale_price' => '', 'is_default' => '0', 'is_active' => '1', 'attribute_value_ids' => [$attrs['color']->id => $values['white']->id, $attrs['storage']->id => $values['256gb']->id]],
                ['sku' => 'IPH-WHT-512', 'base_price' => '12.00', 'sale_price' => '', 'is_default' => '0', 'is_active' => '1', 'attribute_value_ids' => [$attrs['color']->id => $values['white']->id, $attrs['storage']->id => $values['512gb']->id]],
            ],
            'specifications' => [],
        ], $overrides);
    }

    private function addPrimaryImage(Product $product): ProductImage
    {
        return ProductImage::factory()->primary()->create(['product_id' => $product->id]);
    }
}
