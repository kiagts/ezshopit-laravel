<?php

namespace Tests\Feature\Product;

use App\Actions\Products\CreateProduct;
use App\Actions\Products\GenerateVariantCombinationKey;
use App\Actions\Products\UpdateProduct;
use App\Enums\ProductAttributeInputType;
use App\Enums\ProductAttributeType;
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
use Database\Seeders\AttributeSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ProductAttributeSetSeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_relationships_are_connected(): void
    {
        [$vendor, $category, $brand] = $this->approvedVendorCategoryAndBrand();

        $product = Product::factory()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ]);
        $variant = ProductVariant::factory()->default()->create(['product_id' => $product->id]);

        $this->assertTrue($vendor->products()->whereKey($product)->exists());
        $this->assertTrue($product->vendor->is($vendor));
        $this->assertTrue($category->products()->whereKey($product)->exists());
        $this->assertTrue($product->category->is($category));
        $this->assertTrue($brand->products()->whereKey($product)->exists());
        $this->assertTrue($product->brand->is($brand));
        $this->assertTrue($product->variants()->whereKey($variant)->exists());
        $this->assertTrue($variant->product->is($product));
        $this->assertTrue($product->defaultVariant->is($variant));
    }

    public function test_approved_vendor_can_create_simple_product_with_default_variant(): void
    {
        [$vendor, $category, $brand] = $this->approvedVendorCategoryAndBrand();

        $product = app(CreateProduct::class)->handle($vendor, [
            'name' => 'Sony Headphones',
            'type' => ProductType::Simple->value,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'description' => 'Noise cancelling headphones.',
            'variants' => [
                ['sku' => 'sony-wh-default', 'base_price' => 39900, 'sale_price' => 34900],
            ],
        ]);

        $this->assertSame(ProductType::Simple, $product->type);
        $this->assertSame('SONY-WH-DEFAULT', $product->defaultVariant->sku);
        $this->assertSame(39900, $product->defaultVariant->base_price);
        $this->assertSame(34900, $product->defaultVariant->sale_price);
        $this->assertSame('default', $product->defaultVariant->combination_key);
        $this->assertTrue($product->defaultVariant->attributeValues->isEmpty());
    }

    public function test_unapproved_vendor_parent_category_inactive_category_and_inactive_brand_are_rejected(): void
    {
        [$vendor, $leaf, $brand] = $this->approvedVendorCategoryAndBrand();
        $pending = Vendor::factory()->create(['status' => VendorStatus::Pending]);
        $parent = Category::factory()->create(['is_active' => true]);
        Category::factory()->childOf($parent)->create();
        $inactiveLeaf = Category::factory()->inactive()->create();
        $inactiveBrand = Brand::factory()->inactive()->create();

        $this->expectValidation(fn () => app(CreateProduct::class)->handle($pending, $this->simpleData($leaf, $brand)), 'vendor');
        $this->expectValidation(fn () => app(CreateProduct::class)->handle($vendor, $this->simpleData($parent, $brand)), 'category_id');
        $this->expectValidation(fn () => app(CreateProduct::class)->handle($vendor, $this->simpleData($inactiveLeaf, $brand)), 'category_id');
        $this->expectValidation(fn () => app(CreateProduct::class)->handle($vendor, $this->simpleData($leaf, $inactiveBrand)), 'brand_id');
    }

    public function test_category_with_inactive_ancestor_is_rejected(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $root = Category::factory()->inactive()->create();
        $leaf = Category::factory()->childOf($root)->create(['is_active' => true]);
        $brand = Brand::factory()->create();

        $this->expectValidation(fn () => app(CreateProduct::class)->handle($vendor, $this->simpleData($leaf, $brand)), 'category_id');
    }

    public function test_invalid_prices_and_duplicate_skus_are_rejected(): void
    {
        [$vendor, $category, $brand] = $this->approvedVendorCategoryAndBrand();
        ProductVariant::factory()->create(['sku' => 'DUPLICATE-SKU']);

        $this->expectValidation(fn () => app(CreateProduct::class)->handle($vendor, $this->simpleData($category, $brand, [
            'variants' => [['sku' => 'NEGATIVE', 'base_price' => -1]],
        ])), 'variants.base_price');

        $this->expectValidation(fn () => app(CreateProduct::class)->handle($vendor, $this->simpleData($category, $brand, [
            'variants' => [['sku' => 'SALE-HIGH', 'base_price' => 1000, 'sale_price' => 1200]],
        ])), 'variants.sale_price');

        $this->expectValidation(fn () => app(CreateProduct::class)->handle($vendor, $this->simpleData($category, $brand, [
            'variants' => [['sku' => 'duplicate-sku', 'base_price' => 1000]],
        ])), 'variants.sku');
    }

    public function test_variant_product_creates_relational_attribute_values_and_deterministic_combination_keys(): void
    {
        [$vendor, $category, $brand, $attrs, $values] = $this->variantCatalog();

        $product = app(CreateProduct::class)->handle($vendor, [
            'name' => 'Apple iPhone 17 Pro',
            'type' => ProductType::Variant->value,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'description' => 'A variant product.',
            'variants' => [
                ['sku' => 'IPH-BLK-256', 'base_price' => 129900, 'attribute_value_ids' => [
                    $attrs['storage']->id => $values['256gb']->id,
                    $attrs['color']->id => $values['black']->id,
                ]],
                ['sku' => 'IPH-WHT-256', 'base_price' => 129900, 'attribute_value_ids' => [
                    $attrs['color']->id => $values['white']->id,
                    $attrs['storage']->id => $values['256gb']->id,
                ]],
            ],
        ]);

        $first = $product->variants()->where('sku', 'IPH-BLK-256')->firstOrFail();

        $this->assertSame(
            app(GenerateVariantCombinationKey::class)->handle([$attrs['color']->id => $values['black']->id, $attrs['storage']->id => $values['256gb']->id]),
            $first->combination_key,
        );
        $this->assertTrue($first->attributeValues()->whereKey($values['black'])->exists());
        $this->assertTrue($first->attributeValues()->whereKey($values['256gb'])->exists());
    }

    public function test_duplicate_variant_combinations_and_invalid_variant_values_are_rejected(): void
    {
        [$vendor, $category, $brand, $attrs, $values] = $this->variantCatalog();
        $material = ProductAttribute::factory()->create([
            'type' => ProductAttributeType::Specification,
            'input_type' => ProductAttributeInputType::Select,
        ]);
        $cotton = ProductAttributeValue::factory()->create(['product_attribute_id' => $material->id, 'code' => 'cotton']);
        $inactive = ProductAttributeValue::factory()->inactive()->create(['product_attribute_id' => $attrs['color']->id, 'code' => 'inactive-blue']);

        $this->expectValidation(fn () => app(CreateProduct::class)->handle($vendor, [
            ...$this->variantData($category, $brand),
            'variants' => [
                ['sku' => 'DUP-1', 'base_price' => 1000, 'attribute_value_ids' => [$attrs['color']->id => $values['black']->id, $attrs['storage']->id => $values['256gb']->id]],
                ['sku' => 'DUP-2', 'base_price' => 1000, 'attribute_value_ids' => [$attrs['storage']->id => $values['256gb']->id, $attrs['color']->id => $values['black']->id]],
            ],
        ]), 'variants');

        $this->expectValidation(fn () => app(CreateProduct::class)->handle($vendor, [
            ...$this->variantData($category, $brand),
            'slug' => 'wrong-value-product',
            'variants' => [
                ['sku' => 'WRONG-VALUE', 'base_price' => 1000, 'attribute_value_ids' => [$attrs['color']->id => $values['256gb']->id, $attrs['storage']->id => $values['256gb']->id]],
            ],
        ]), 'variants.attribute_value_ids');

        $this->expectValidation(fn () => app(CreateProduct::class)->handle($vendor, [
            ...$this->variantData($category, $brand),
            'slug' => 'spec-as-variant',
            'variants' => [
                ['sku' => 'SPEC-AS-VARIANT', 'base_price' => 1000, 'attribute_value_ids' => [$material->id => $cotton->id]],
            ],
        ]), 'variants.attribute_value_ids');

        $this->expectValidation(fn () => app(CreateProduct::class)->handle($vendor, [
            ...$this->variantData($category, $brand),
            'slug' => 'inactive-value',
            'variants' => [
                ['sku' => 'INACTIVE-VALUE', 'base_price' => 1000, 'attribute_value_ids' => [$attrs['color']->id => $inactive->id, $attrs['storage']->id => $values['256gb']->id]],
            ],
        ]), 'variants.attribute_value_ids');
    }

    public function test_database_prevents_two_values_for_same_variant_attribute(): void
    {
        [$vendor, $category, $brand, $attrs, $values] = $this->variantCatalog();
        $product = app(CreateProduct::class)->handle($vendor, [
            ...$this->variantData($category, $brand),
            'variants' => [
                ['sku' => 'IPH-BLK-256-ONE', 'base_price' => 1000, 'attribute_value_ids' => [$attrs['color']->id => $values['black']->id, $attrs['storage']->id => $values['256gb']->id]],
            ],
        ]);

        $this->expectException(QueryException::class);
        $product->variants->first()->attributeValues()->attach($values['white']->id, ['product_attribute_id' => $attrs['color']->id]);
    }

    public function test_specifications_support_select_multiselect_text_number_and_boolean(): void
    {
        [$vendor, $category, $brand, $attrs, $values] = $this->specificationCatalog();

        $product = app(CreateProduct::class)->handle($vendor, [
            'name' => 'Specification Product',
            'type' => ProductType::Simple->value,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'description' => 'Specification storage test.',
            'variants' => [['sku' => 'SPEC-SIMPLE', 'base_price' => 1000]],
            'specifications' => [
                $attrs['processor']->id => ['text_value' => 'A19 Pro'],
                $attrs['screen']->id => ['number_value' => 6.7],
                $attrs['water']->id => ['boolean_value' => true],
                $attrs['material']->id => ['product_attribute_value_id' => $values['cotton']->id],
                $attrs['connectivity']->id => ['product_attribute_value_ids' => [$values['wifi']->id, $values['bluetooth']->id]],
            ],
        ]);

        $this->assertSame('A19 Pro', $product->specifications()->where('product_attribute_id', $attrs['processor']->id)->firstOrFail()->text_value);
        $this->assertSame('6.7000', $product->specifications()->where('product_attribute_id', $attrs['screen']->id)->firstOrFail()->number_value);
        $this->assertTrue($product->specifications()->where('product_attribute_id', $attrs['water']->id)->firstOrFail()->boolean_value);
        $this->assertTrue($product->specifications()->where('product_attribute_id', $attrs['material']->id)->firstOrFail()->values()->whereKey($values['cotton'])->exists());
        $this->assertSame(2, $product->specifications()->where('product_attribute_id', $attrs['connectivity']->id)->firstOrFail()->values()->count());
    }

    public function test_invalid_specification_attribute_or_value_shape_is_rejected(): void
    {
        [$vendor, $category, $brand, $attrs, $values] = $this->specificationCatalog();
        $outside = ProductAttribute::factory()->create(['type' => ProductAttributeType::Specification, 'input_type' => ProductAttributeInputType::Text]);

        $this->expectValidation(fn () => app(CreateProduct::class)->handle($vendor, $this->simpleData($category, $brand, [
            'slug' => 'outside-spec',
            'specifications' => [$outside->id => ['text_value' => 'Nope']],
        ])), 'specifications');

        $this->expectValidation(fn () => app(CreateProduct::class)->handle($vendor, $this->simpleData($category, $brand, [
            'slug' => 'wrong-spec-value',
            'specifications' => [$attrs['material']->id => ['product_attribute_value_id' => $values['wifi']->id]],
        ])), 'specifications');

        $this->expectValidation(fn () => app(CreateProduct::class)->handle($vendor, $this->simpleData($category, $brand, [
            'slug' => 'wrong-spec-shape',
            'specifications' => [$attrs['screen']->id => ['text_value' => 'large']],
        ])), 'specifications');
    }

    public function test_submission_readiness_detects_missing_required_specs_and_variants(): void
    {
        [$vendor, $category, $brand, $attrs, $values] = $this->specificationCatalog(includeVariantAttributes: true);
        $validator = app(\App\Services\ProductSubmissionValidator::class);

        $missing = app(CreateProduct::class)->handle($vendor, [
            'name' => 'Incomplete Variant Product',
            'type' => ProductType::Variant->value,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'description' => 'Incomplete.',
            'variants' => [
                ['sku' => 'MISSING-STORAGE', 'base_price' => 1000, 'attribute_value_ids' => [$attrs['color']->id => $values['black']->id]],
            ],
        ]);

        $this->assertFalse($validator->ready($missing));
        $this->assertContains('Every variant must include required variant attributes.', $validator->errors($missing));
        $this->assertContains('Required specifications must be populated.', $validator->errors($missing));
        $this->assertContains('Choose a primary product image before submitting for review.', $validator->errors($missing));

        $ready = app(CreateProduct::class)->handle($vendor, [
            'name' => 'Complete Variant Product',
            'slug' => 'complete-variant-product',
            'type' => ProductType::Variant->value,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'description' => 'Complete.',
            'variants' => [
                ['sku' => 'COMPLETE-BLK-256', 'base_price' => 1000, 'attribute_value_ids' => [$attrs['color']->id => $values['black']->id, $attrs['storage']->id => $values['256gb']->id]],
            ],
            'specifications' => [
                $attrs['processor']->id => ['text_value' => 'A19 Pro'],
            ],
        ]);
        ProductImage::factory()->primary()->create(['product_id' => $ready->id]);

        $this->assertTrue($validator->ready($ready));
    }

    public function test_type_vendor_and_category_mutation_are_protected(): void
    {
        [$vendor, $category, $brand] = $this->approvedVendorCategoryAndBrand();
        $otherVendor = Vendor::factory()->approved()->create();
        $otherLeaf = Category::factory()->create();

        $product = app(CreateProduct::class)->handle($vendor, $this->simpleData($category, $brand, [
            'slug' => 'immutable-product',
            'variants' => [['sku' => 'IMMUTABLE-SKU', 'base_price' => 1000]],
        ]));

        $this->expectValidation(fn () => app(UpdateProduct::class)->handle($vendor, $product, ['type' => ProductType::Variant->value]), 'type');
        $this->expectValidation(fn () => app(UpdateProduct::class)->handle($vendor, $product, ['category_id' => $otherLeaf->id]), 'category_id');
        $this->expectValidation(fn () => app(UpdateProduct::class)->handle($otherVendor, $product, ['name' => 'Stolen']), 'product');
    }

    public function test_product_seeder_is_repeatable(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(AttributeSeeder::class);
        $this->seed(ProductAttributeSetSeeder::class);
        $this->seed(\Database\Seeders\BrandSeeder::class);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $vendor = Vendor::factory()->approved()->create(['slug' => 'techverse', 'approved_by' => $admin->id]);

        $this->seed(ProductSeeder::class);
        $this->seed(ProductSeeder::class);

        $this->assertSame(12, Product::query()->where('vendor_id', $vendor->id)->count());
        $this->assertSame(23, ProductVariant::query()->whereHas('product', fn ($query) => $query->where('vendor_id', $vendor->id))->count());
    }

    private function approvedVendorCategoryAndBrand(): array
    {
        return [
            Vendor::factory()->approved()->create(),
            Category::factory()->create(['is_active' => true]),
            Brand::factory()->create(['is_active' => true]),
        ];
    }

    private function simpleData(Category $category, Brand $brand, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Simple Product',
            'slug' => 'simple-product-'.fake()->unique()->numberBetween(1000, 9999),
            'type' => ProductType::Simple->value,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'description' => 'Simple product description.',
            'variants' => [['sku' => 'SIMPLE-'.fake()->unique()->numberBetween(1000, 9999), 'base_price' => 1000]],
            'specifications' => [],
        ], $overrides);
    }

    private function variantData(Category $category, Brand $brand): array
    {
        return [
            'name' => 'Variant Product',
            'slug' => 'variant-product-'.fake()->unique()->numberBetween(1000, 9999),
            'type' => ProductType::Variant->value,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'description' => 'Variant product description.',
        ];
    }

    private function variantCatalog(): array
    {
        $vendor = Vendor::factory()->approved()->create();
        $category = Category::factory()->create(['is_active' => true]);
        $brand = Brand::factory()->create(['is_active' => true]);
        $set = ProductAttributeSet::factory()->create();
        $category->update(['product_attribute_set_id' => $set->id]);

        $color = ProductAttribute::factory()->variant()->create(['name' => 'Color', 'code' => 'color']);
        $storage = ProductAttribute::factory()->variant()->create(['name' => 'Storage', 'code' => 'storage']);
        $set->attributes()->attach($color->id, ['is_required' => true, 'sort_order' => 10]);
        $set->attributes()->attach($storage->id, ['is_required' => true, 'sort_order' => 20]);

        $black = ProductAttributeValue::factory()->create(['product_attribute_id' => $color->id, 'value' => 'Black', 'code' => 'black']);
        $white = ProductAttributeValue::factory()->create(['product_attribute_id' => $color->id, 'value' => 'White', 'code' => 'white']);
        $storage256 = ProductAttributeValue::factory()->create(['product_attribute_id' => $storage->id, 'value' => '256GB', 'code' => '256gb']);

        return [$vendor, $category, $brand, ['color' => $color, 'storage' => $storage], ['black' => $black, 'white' => $white, '256gb' => $storage256]];
    }

    private function specificationCatalog(bool $includeVariantAttributes = false): array
    {
        $vendor = Vendor::factory()->approved()->create();
        $category = Category::factory()->create(['is_active' => true]);
        $brand = Brand::factory()->create(['is_active' => true]);
        $set = ProductAttributeSet::factory()->create();
        $category->update(['product_attribute_set_id' => $set->id]);

        $processor = ProductAttribute::factory()->create(['code' => 'processor', 'type' => ProductAttributeType::Specification, 'input_type' => ProductAttributeInputType::Text]);
        $screen = ProductAttribute::factory()->create(['code' => 'screen_size', 'type' => ProductAttributeType::Specification, 'input_type' => ProductAttributeInputType::Number]);
        $water = ProductAttribute::factory()->create(['code' => 'water_resistant', 'type' => ProductAttributeType::Specification, 'input_type' => ProductAttributeInputType::Boolean]);
        $material = ProductAttribute::factory()->create(['code' => 'material', 'type' => ProductAttributeType::Specification, 'input_type' => ProductAttributeInputType::Select]);
        $connectivity = ProductAttribute::factory()->create(['code' => 'connectivity', 'type' => ProductAttributeType::Specification, 'input_type' => ProductAttributeInputType::Multiselect]);

        foreach ([$processor, $screen, $water, $material, $connectivity] as $index => $attribute) {
            $set->attributes()->attach($attribute->id, ['is_required' => $attribute->is($processor), 'sort_order' => ($index + 1) * 10]);
        }

        $values = [
            'cotton' => ProductAttributeValue::factory()->create(['product_attribute_id' => $material->id, 'code' => 'cotton']),
            'wifi' => ProductAttributeValue::factory()->create(['product_attribute_id' => $connectivity->id, 'code' => 'wi-fi']),
            'bluetooth' => ProductAttributeValue::factory()->create(['product_attribute_id' => $connectivity->id, 'code' => 'bluetooth']),
        ];

        $attrs = compact('processor', 'screen', 'water', 'material', 'connectivity');

        if ($includeVariantAttributes) {
            [, , , $variantAttrs, $variantValues] = $this->variantCatalog();
            foreach ($variantAttrs as $attribute) {
                $set->attributes()->attach($attribute->id, ['is_required' => true, 'sort_order' => 100 + $attribute->id]);
            }
            $attrs = array_merge($attrs, $variantAttrs);
            $values = array_merge($values, $variantValues);
        }

        return [$vendor, $category, $brand, $attrs, $values];
    }

    private function expectValidation(callable $callback, string $key): void
    {
        try {
            $callback();
            $this->fail('Expected validation exception for '.$key);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($key, $exception->errors());
        }
    }
}
