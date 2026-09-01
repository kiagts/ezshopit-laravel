<?php

namespace Tests\Feature\Storefront;

use App\Enums\ProductApprovalStatus;
use App\Enums\ProductAttributeInputType;
use App\Enums\ProductAttributeType;
use App\Enums\ProductStatus;
use App\Enums\VendorStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeSet;
use App\Models\ProductAttributeValue;
use App\Models\ProductSpecification;
use App\Models\ProductVariant;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_category_visibility_rules(): void
    {
        $active = Category::factory()->create(['name' => 'Active Category', 'slug' => 'active-category']);
        $inactive = Category::factory()->inactive()->create(['slug' => 'inactive-category']);
        $inactiveParent = Category::factory()->inactive()->create();
        $hiddenChild = Category::factory()->childOf($inactiveParent)->create(['slug' => 'hidden-child']);

        $this->get(route('storefront.categories.show', $active))->assertOk();
        $this->get('/category/'.$inactive->slug)->assertNotFound();
        $this->get('/category/'.$hiddenChild->slug)->assertNotFound();

        $active->delete();

        $this->get('/category/active-category')->assertNotFound();
    }

    public function test_category_results_exclude_non_public_products(): void
    {
        $category = Category::factory()->create(['slug' => 'smartphones']);

        $this->createPublicProduct('Visible Product', category: $category);
        $this->createPublicProduct('Draft Product', category: $category, productState: ['status' => ProductStatus::Draft]);
        $this->createPublicProduct('Pending Product', category: $category, productState: ['approval_status' => ProductApprovalStatus::Pending]);
        $this->createPublicProduct('Archived Product', category: $category, productState: ['status' => ProductStatus::Archived]);
        $this->createPublicProduct('Suspended Vendor Product', category: $category, vendorState: ['status' => VendorStatus::Suspended]);

        $this->get(route('storefront.categories.show', $category))
            ->assertOk()
            ->assertSeeText('Visible Product')
            ->assertDontSeeText('Draft Product')
            ->assertDontSeeText('Pending Product')
            ->assertDontSeeText('Archived Product')
            ->assertDontSeeText('Suspended Vendor Product');
    }

    public function test_parent_category_includes_active_descendant_products_only(): void
    {
        $parent = Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);
        $activeChild = Category::factory()->childOf($parent)->create(['name' => 'Smartphones', 'slug' => 'smartphones']);
        $inactiveChild = Category::factory()->childOf($parent)->inactive()->create(['name' => 'Hidden Phones', 'slug' => 'hidden-phones']);

        $this->createPublicProduct('Descendant Public Product', category: $activeChild);
        $this->createPublicProduct('Inactive Descendant Product', category: $inactiveChild);

        $this->get(route('storefront.categories.show', $parent))
            ->assertOk()
            ->assertSeeText('Explore Electronics')
            ->assertSeeText('Smartphones')
            ->assertSeeText('Descendant Public Product')
            ->assertDontSeeText('Hidden Phones')
            ->assertDontSeeText('Inactive Descendant Product');
    }

    public function test_brand_filter_uses_or_semantics(): void
    {
        $category = Category::factory()->create(['slug' => 'phones']);
        $apple = Brand::factory()->create(['name' => 'Apple', 'slug' => 'apple']);
        $samsung = Brand::factory()->create(['name' => 'Samsung', 'slug' => 'samsung']);
        $sony = Brand::factory()->create(['name' => 'Sony', 'slug' => 'sony']);

        $this->createPublicProduct('Apple Phone', category: $category, brand: $apple);
        $this->createPublicProduct('Samsung Phone', category: $category, brand: $samsung);
        $this->createPublicProduct('Sony Phone', category: $category, brand: $sony);

        $this->get(route('storefront.categories.show', [$category, 'brand' => ['apple', 'samsung']]))
            ->assertOk()
            ->assertSeeText('Apple Phone')
            ->assertSeeText('Samsung Phone')
            ->assertDontSeeText('Sony Phone');
    }

    public function test_price_filters_use_active_variant_effective_price(): void
    {
        $category = Category::factory()->create(['slug' => 'phones']);

        $this->createPublicProduct('Budget Phone', category: $category, variants: [['sku' => 'BUDGET-1', 'base_price' => 5000]]);
        $this->createPublicProduct('Sale Phone', category: $category, variants: [['sku' => 'SALE-1', 'base_price' => 20000, 'sale_price' => 9000]]);
        $this->createPublicProduct('Premium Phone', category: $category, variants: [['sku' => 'PREMIUM-1', 'base_price' => 30000]]);

        $this->get(route('storefront.categories.show', [$category, 'price_min' => '80']))
            ->assertOk()
            ->assertDontSeeText('Budget Phone')
            ->assertSeeText('Sale Phone')
            ->assertSeeText('Premium Phone');

        $this->get(route('storefront.categories.show', [$category, 'price_max' => '100']))
            ->assertOk()
            ->assertSeeText('Budget Phone')
            ->assertSeeText('Sale Phone')
            ->assertDontSeeText('Premium Phone');

        $this->get(route('storefront.categories.show', [$category, 'price_min' => '80', 'price_max' => '100']))
            ->assertOk()
            ->assertDontSeeText('Budget Phone')
            ->assertSeeText('Sale Phone')
            ->assertDontSeeText('Premium Phone');
    }

    public function test_variant_attribute_filters_are_combination_safe(): void
    {
        [$category, $attributes, $values] = $this->categoryWithVariantFilters();

        $this->createPublicProduct('Split Combination Phone', category: $category, variants: [
            ['sku' => 'SPLIT-BLK-128', 'base_price' => 10000, 'values' => [$attributes['color']->id => $values['black']->id, $attributes['storage']->id => $values['128gb']->id]],
            ['sku' => 'SPLIT-WHT-256', 'base_price' => 12000, 'values' => [$attributes['color']->id => $values['white']->id, $attributes['storage']->id => $values['256gb']->id]],
        ]);

        $this->createPublicProduct('Matching Combination Phone', category: $category, variants: [
            ['sku' => 'MATCH-BLK-256', 'base_price' => 11000, 'values' => [$attributes['color']->id => $values['black']->id, $attributes['storage']->id => $values['256gb']->id]],
        ]);

        $this->get(route('storefront.categories.show', [
            $category,
            'attributes' => ['color' => ['black'], 'storage' => ['256gb']],
        ]))
            ->assertOk()
            ->assertDontSeeText('Split Combination Phone')
            ->assertSeeText('Matching Combination Phone');
    }

    public function test_same_attribute_uses_or_logic_and_different_attributes_use_and_logic(): void
    {
        [$category, $attributes, $values] = $this->categoryWithVariantFilters();

        $this->createPublicProduct('Black Phone', category: $category, variants: [
            ['sku' => 'BLACK-128', 'base_price' => 10000, 'values' => [$attributes['color']->id => $values['black']->id, $attributes['storage']->id => $values['128gb']->id]],
        ]);
        $this->createPublicProduct('White Phone', category: $category, variants: [
            ['sku' => 'WHITE-256', 'base_price' => 10000, 'values' => [$attributes['color']->id => $values['white']->id, $attributes['storage']->id => $values['256gb']->id]],
        ]);
        $this->createPublicProduct('Blue Phone', category: $category, variants: [
            ['sku' => 'BLUE-256', 'base_price' => 10000, 'values' => [$attributes['color']->id => $values['blue']->id, $attributes['storage']->id => $values['256gb']->id]],
        ]);

        $this->get(route('storefront.categories.show', [$category, 'attributes' => ['color' => ['black', 'white']]]))
            ->assertOk()
            ->assertSeeText('Black Phone')
            ->assertSeeText('White Phone')
            ->assertDontSeeText('Blue Phone');

        $this->get(route('storefront.categories.show', [$category, 'attributes' => ['color' => ['black', 'white'], 'storage' => ['256gb']]]))
            ->assertOk()
            ->assertDontSeeText('Black Phone')
            ->assertSeeText('White Phone')
            ->assertDontSeeText('Blue Phone');
    }

    public function test_variant_availability_filter_uses_the_matching_variant_combination(): void
    {
        [$category, $attributes, $values] = $this->categoryWithVariantFilters();

        $product = $this->createPublicProduct('Availability Combination Phone', category: $category, variants: [
            ['sku' => 'AVAIL-BLK-256', 'base_price' => 10000, 'stock' => 0, 'values' => [$attributes['color']->id => $values['black']->id, $attributes['storage']->id => $values['256gb']->id]],
            ['sku' => 'AVAIL-WHT-256', 'base_price' => 10000, 'stock' => 10, 'values' => [$attributes['color']->id => $values['white']->id, $attributes['storage']->id => $values['256gb']->id]],
        ]);

        $this->get(route('storefront.categories.show', [
            $category,
            'attributes' => ['color' => ['black'], 'storage' => ['256gb']],
            'in_stock' => 1,
        ]))
            ->assertOk()
            ->assertDontSeeText($product->name);
    }

    public function test_specification_filters_and_filterable_attribute_relevance(): void
    {
        [$category, $material, $cotton, $leather, $waterResistant, $hidden] = $this->categoryWithSpecificationFilters();
        $footwear = Category::factory()->create(['name' => 'Footwear', 'slug' => 'footwear']);

        $this->createPublicProduct('Cotton Jacket', category: $category, specifications: [
            $material->id => ['value_ids' => [$cotton->id]],
            $waterResistant->id => ['boolean_value' => true],
        ]);
        $this->createPublicProduct('Leather Jacket', category: $category, specifications: [
            $material->id => ['value_ids' => [$leather->id]],
            $waterResistant->id => ['boolean_value' => false],
            $hidden->id => ['text_value' => 'Internal only'],
        ]);

        $this->get(route('storefront.categories.show', $category))
            ->assertOk()
            ->assertSeeText('Material')
            ->assertSeeText('Water Resistant')
            ->assertDontSeeText('Private Detail');

        $this->get(route('storefront.categories.show', [$category, 'attributes' => ['material' => ['cotton'], 'water_resistant' => ['1']]]))
            ->assertOk()
            ->assertSeeText('Cotton Jacket')
            ->assertDontSeeText('Leather Jacket');

        $this->get(route('storefront.categories.show', $footwear))
            ->assertOk()
            ->assertDontSeeText('Material')
            ->assertDontSeeText('Water Resistant');
    }

    public function test_parent_category_does_not_merge_descendant_attribute_filters(): void
    {
        $parent = Category::factory()->create(['name' => 'Fashion', 'slug' => 'fashion']);
        [$clothing] = $this->categoryWithSpecificationFilters(parent: $parent);

        $this->createPublicProduct('Cotton Jacket', category: $clothing);

        $this->get(route('storefront.categories.show', $parent))
            ->assertOk()
            ->assertSeeText('Brand')
            ->assertSeeText('Price')
            ->assertSeeText('Availability')
            ->assertDontSeeText('Material');
    }

    public function test_search_matches_product_brand_vendor_and_sku_without_leaking_private_products(): void
    {
        $brand = Brand::factory()->create(['name' => 'Searchable Brand', 'slug' => 'searchable-brand']);
        $vendor = Vendor::factory()->approved()->create(['display_name' => 'Searchable Store']);

        $this->createPublicProduct('Alpha Product', brand: $brand, vendor: $vendor, variants: [['sku' => 'ALPHA-SKU', 'base_price' => 10000]]);
        $this->createPublicProduct('Hidden Alpha Product', brand: $brand, vendor: $vendor, productState: ['approval_status' => ProductApprovalStatus::Pending]);

        $this->get(route('storefront.search', ['q' => 'Alpha']))
            ->assertOk()
            ->assertSeeText('Alpha Product')
            ->assertDontSeeText('Hidden Alpha Product');

        $this->get(route('storefront.search', ['q' => 'Searchable Brand']))
            ->assertOk()
            ->assertSeeText('Alpha Product');

        $this->get(route('storefront.search', ['q' => 'Searchable Store']))
            ->assertOk()
            ->assertSeeText('Alpha Product');

        $this->get(route('storefront.search', ['q' => 'ALPHA-SKU']))
            ->assertOk()
            ->assertSeeText('Alpha Product');
    }

    public function test_search_category_filter_sorting_pagination_and_invalid_filters(): void
    {
        $phones = Category::factory()->create(['name' => 'Phones', 'slug' => 'phones']);
        $laptops = Category::factory()->create(['name' => 'Laptops', 'slug' => 'laptops']);

        $this->createPublicProduct('Alpha Phone', category: $phones, variants: [['sku' => 'PHONE-1', 'base_price' => 20000]], productState: ['published_at' => now()->subDay()]);
        $this->createPublicProduct('Beta Laptop', category: $laptops, variants: [['sku' => 'LAPTOP-1', 'base_price' => 50000]], productState: ['published_at' => now()]);

        $this->get(route('storefront.search', ['q' => 'Product Discovery Empty']))->assertOk();

        $this->get(route('storefront.search', ['q' => 'a', 'category' => ['phones'], 'sort' => 'hacked', 'brand' => ['bad slug'], 'page' => 1]))
            ->assertOk()
            ->assertSeeText('Alpha Phone')
            ->assertDontSeeText('Beta Laptop');

        $this->get(route('storefront.search', ['q' => 'a', 'sort' => 'price_desc']))
            ->assertOk()
            ->assertSeeTextInOrder(['Beta Laptop', 'Alpha Phone']);

        $this->get(route('storefront.search', ['q' => 'a', 'sort' => 'price_asc']))
            ->assertOk()
            ->assertSeeTextInOrder(['Alpha Phone', 'Beta Laptop']);

        $this->get(route('storefront.search', ['q' => 'a', 'sort' => 'name_asc']))
            ->assertOk()
            ->assertSeeTextInOrder(['Alpha Phone', 'Beta Laptop'])
            ->assertSee('q=a', false);
    }

    public function test_out_of_stock_products_are_visible_until_in_stock_filter_is_selected(): void
    {
        $category = Category::factory()->create(['slug' => 'phones']);

        $this->createPublicProduct('Available Phone', category: $category, variants: [['sku' => 'AVAILABLE-1', 'base_price' => 10000, 'stock' => 5]]);
        $this->createPublicProduct('Sold Out Phone', category: $category, variants: [['sku' => 'SOLDOUT-1', 'base_price' => 10000, 'stock' => 0]]);

        $this->get(route('storefront.categories.show', $category))
            ->assertOk()
            ->assertSeeText('Available Phone')
            ->assertSeeText('Sold Out Phone')
            ->assertSeeText('Out of stock');

        $this->get(route('storefront.categories.show', [$category, 'in_stock' => 1]))
            ->assertOk()
            ->assertSeeText('Available Phone')
            ->assertDontSeeText('Sold Out Phone');
    }

    /**
     * @return array{0: Category, 1: array<string, ProductAttribute>, 2: array<string, ProductAttributeValue>}
     */
    private function categoryWithVariantFilters(): array
    {
        $set = ProductAttributeSet::factory()->create(['name' => 'Smartphone', 'code' => 'smartphone']);
        $category = Category::factory()->create(['name' => 'Smartphones', 'slug' => 'smartphones', 'product_attribute_set_id' => $set->id]);

        $color = ProductAttribute::factory()->variant()->create(['name' => 'Color', 'code' => 'color', 'is_filterable' => true, 'sort_order' => 10]);
        $storage = ProductAttribute::factory()->variant()->create(['name' => 'Storage', 'code' => 'storage', 'is_filterable' => true, 'sort_order' => 20]);
        $set->attributes()->attach($color->id, ['is_required' => true, 'sort_order' => 10]);
        $set->attributes()->attach($storage->id, ['is_required' => true, 'sort_order' => 20]);

        $values = [
            'black' => ProductAttributeValue::factory()->create(['product_attribute_id' => $color->id, 'value' => 'Black', 'code' => 'black']),
            'white' => ProductAttributeValue::factory()->create(['product_attribute_id' => $color->id, 'value' => 'White', 'code' => 'white']),
            'blue' => ProductAttributeValue::factory()->create(['product_attribute_id' => $color->id, 'value' => 'Blue', 'code' => 'blue']),
            '128gb' => ProductAttributeValue::factory()->create(['product_attribute_id' => $storage->id, 'value' => '128GB', 'code' => '128gb']),
            '256gb' => ProductAttributeValue::factory()->create(['product_attribute_id' => $storage->id, 'value' => '256GB', 'code' => '256gb']),
        ];

        return [$category, ['color' => $color, 'storage' => $storage], $values];
    }

    /**
     * @return array{0: Category, 1: ProductAttribute, 2: ProductAttributeValue, 3: ProductAttributeValue, 4: ProductAttribute, 5: ProductAttribute}
     */
    private function categoryWithSpecificationFilters(?Category $parent = null): array
    {
        $set = ProductAttributeSet::factory()->create(['name' => 'Clothing', 'code' => 'clothing']);
        $category = Category::factory()->childOf($parent ?? Category::factory()->create(['slug' => 'fashion']))->create([
            'name' => 'Clothing',
            'slug' => 'clothing',
            'product_attribute_set_id' => $set->id,
        ]);

        $material = ProductAttribute::factory()->specification()->create([
            'name' => 'Material',
            'code' => 'material',
            'input_type' => ProductAttributeInputType::Select,
            'is_filterable' => true,
        ]);
        $waterResistant = ProductAttribute::factory()->specification()->create([
            'name' => 'Water Resistant',
            'code' => 'water_resistant',
            'input_type' => ProductAttributeInputType::Boolean,
            'is_filterable' => true,
        ]);
        $hidden = ProductAttribute::factory()->specification()->create([
            'name' => 'Private Detail',
            'code' => 'private_detail',
            'input_type' => ProductAttributeInputType::Text,
            'is_filterable' => false,
        ]);

        $set->attributes()->attach($material->id, ['is_required' => false, 'sort_order' => 10]);
        $set->attributes()->attach($waterResistant->id, ['is_required' => false, 'sort_order' => 20]);
        $set->attributes()->attach($hidden->id, ['is_required' => false, 'sort_order' => 30]);

        $cotton = ProductAttributeValue::factory()->create(['product_attribute_id' => $material->id, 'value' => 'Cotton', 'code' => 'cotton']);
        $leather = ProductAttributeValue::factory()->create(['product_attribute_id' => $material->id, 'value' => 'Leather', 'code' => 'leather']);

        return [$category, $material, $cotton, $leather, $waterResistant, $hidden];
    }

    /**
     * @param array<int, array<string, mixed>> $variants
     * @param array<int, array<string, mixed>> $specifications
     * @param array<string, mixed> $productState
     * @param array<string, mixed> $vendorState
     */
    private function createPublicProduct(
        string $name,
        ?Category $category = null,
        ?Brand $brand = null,
        ?Vendor $vendor = null,
        array $variants = [],
        array $specifications = [],
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
                'combination_key' => 'test-'.$index,
            ]);

            foreach ($variantData['values'] ?? [] as $attributeId => $valueId) {
                $variant->attributeValues()->attach($valueId, ['product_attribute_id' => $attributeId]);
            }

            Inventory::query()->where('product_variant_id', $variant->id)->update([
                'on_hand' => $variantData['stock'] ?? 10,
                'reserved' => 0,
                'low_stock_threshold' => 2,
            ]);
        }

        foreach ($specifications as $attributeId => $payload) {
            /** @var ProductSpecification $specification */
            $specification = $product->specifications()->create([
                'product_attribute_id' => $attributeId,
                'text_value' => $payload['text_value'] ?? null,
                'number_value' => $payload['number_value'] ?? null,
                'boolean_value' => $payload['boolean_value'] ?? null,
            ]);

            if (isset($payload['value_ids'])) {
                $specification->values()->attach($payload['value_ids']);
            }
        }

        return $product;
    }
}
