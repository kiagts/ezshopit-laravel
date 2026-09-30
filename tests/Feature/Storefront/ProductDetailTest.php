<?php

namespace Tests\Feature\Storefront;

use App\Enums\ProductApprovalStatus;
use App\Enums\ProductAttributeInputType;
use App\Enums\ProductAttributeType;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\VendorStatus;
use App\Enums\HomepageSectionType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HomepageSection;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeSet;
use App\Models\ProductAttributeValue;
use App\Models\ProductImage;
use App\Models\ProductSpecification;
use App\Models\ProductVariant;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_product_detail_page_renders(): void
    {
        $category = Category::factory()->create(['name' => 'Smartphones', 'slug' => 'smartphones']);
        $brand = Brand::factory()->create(['name' => 'Apple', 'slug' => 'apple']);
        $vendor = Vendor::factory()->approved()->create(['display_name' => 'TechVerse', 'tax_number' => 'TAX-SECRET', 'registration_number' => 'REG-SECRET']);
        $product = $this->createPublicProduct('Apple iPhone Detail', category: $category, brand: $brand, vendor: $vendor, variants: [
            ['sku' => 'IPH-DETAIL', 'base_price' => 129900, 'sale_price' => 119900, 'stock' => 10],
        ]);

        ProductImage::factory()->primary()->create(['product_id' => $product->id, 'path' => 'demo/detail-primary.svg', 'sort_order' => 0]);

        $this->get(route('storefront.products.show', $product))
            ->assertOk()
            ->assertSeeText('Apple iPhone Detail')
            ->assertSeeText('Apple')
            ->assertSeeText('Sold by TechVerse')
            ->assertSeeText('USD 1,199.00')
            ->assertSeeText('SKU: IPH-DETAIL')
            ->assertSeeText('In Stock')
            ->assertSeeText('Add to Cart')
            ->assertSee(route('cart.items.store'), false)
            ->assertSee('name="product_variant_id"', false)
            ->assertDontSeeText('TAX-SECRET')
            ->assertDontSeeText('REG-SECRET')
            ->assertSeeText('Verified Product Reviews')
            ->assertDontSeeText('shipping');
    }

    public function test_non_public_products_return_not_found(): void
    {
        $category = Category::factory()->create();
        $inactiveParent = Category::factory()->inactive()->create();
        $hiddenChild = Category::factory()->childOf($inactiveParent)->create();

        $draft = $this->createPublicProduct('Draft Detail', category: $category, productState: ['status' => ProductStatus::Draft]);
        $pending = $this->createPublicProduct('Pending Detail', category: $category, productState: ['approval_status' => ProductApprovalStatus::Pending]);
        $archived = $this->createPublicProduct('Archived Detail', category: $category, productState: ['status' => ProductStatus::Archived]);
        $suspendedVendor = $this->createPublicProduct('Suspended Vendor Detail', category: $category, vendorState: ['status' => VendorStatus::Suspended]);
        $inactiveCategory = $this->createPublicProduct('Inactive Category Detail', category: Category::factory()->inactive()->create());
        $inactiveAncestor = $this->createPublicProduct('Inactive Ancestor Detail', category: $hiddenChild);

        foreach ([$draft, $pending, $archived, $suspendedVendor, $inactiveCategory, $inactiveAncestor] as $product) {
            $this->get(route('storefront.products.show', $product))->assertNotFound();
        }
    }

    public function test_out_of_stock_public_product_is_viewable(): void
    {
        $product = $this->createPublicProduct('Sold Out Detail Product', variants: [
            ['sku' => 'SOLD-OUT-DETAIL', 'base_price' => 10000, 'stock' => 0],
        ]);

        $this->get(route('storefront.products.show', $product))
            ->assertOk()
            ->assertSeeText('Sold Out Detail Product')
            ->assertSeeText('Out of Stock');
    }

    public function test_simple_product_has_no_variant_selector(): void
    {
        $product = $this->createPublicProduct('Simple Detail Product', productState: ['type' => ProductType::Simple], variants: [
            ['sku' => 'SIMPLE-DETAIL', 'base_price' => 20000, 'sale_price' => 15000, 'stock' => 4],
        ]);

        $this->get(route('storefront.products.show', $product))
            ->assertOk()
            ->assertSeeText('Simple Detail Product')
            ->assertSeeText('USD 150.00')
            ->assertSeeText('SKU: SIMPLE-DETAIL')
            ->assertDontSee('data-variant-selector', false);
    }

    public function test_variant_product_displays_offered_values_and_public_variant_data(): void
    {
        [$category, $color, $storage, $black, $white, $storage128, $storage256] = $this->variantCategory();
        $product = $this->createPublicProduct('Variant Detail Product', category: $category, productState: ['type' => ProductType::Variant], variants: [
            ['sku' => 'VAR-BLK-128', 'base_price' => 10000, 'stock' => 0, 'values' => [$color->id => $black->id, $storage->id => $storage128->id]],
            ['sku' => 'VAR-WHT-256', 'base_price' => 14000, 'sale_price' => 12000, 'stock' => 6, 'values' => [$color->id => $white->id, $storage->id => $storage256->id]],
        ]);

        $this->get(route('storefront.products.show', $product))
            ->assertOk()
            ->assertSee('data-variant-selector', false)
            ->assertSeeText('Color')
            ->assertSeeText('Black')
            ->assertSeeText('White')
            ->assertSeeText('Storage')
            ->assertSeeText('128GB')
            ->assertSeeText('256GB')
            ->assertSeeText('SKU: VAR-WHT-256')
            ->assertSeeText('USD 120.00')
            ->assertSee('"stock_state":"out_of_stock"', false)
            ->assertSee('"stock_state":"in_stock"', false)
            ->assertDontSee('on_hand', false)
            ->assertDontSee('reserved', false);
    }

    public function test_gallery_orders_primary_first_and_variant_media_falls_back(): void
    {
        [$category, $color, $storage, $black, $white, $storage128, $storage256] = $this->variantCategory();
        $product = $this->createPublicProduct('Media Detail Product', category: $category, productState: ['type' => ProductType::Variant], variants: [
            ['sku' => 'MEDIA-BLK-128', 'base_price' => 10000, 'stock' => 6, 'values' => [$color->id => $black->id, $storage->id => $storage128->id]],
            ['sku' => 'MEDIA-WHT-256', 'base_price' => 12000, 'stock' => 6, 'values' => [$color->id => $white->id, $storage->id => $storage256->id]],
        ]);
        $blackVariant = $product->variants()->where('sku', 'MEDIA-BLK-128')->firstOrFail();

        ProductImage::factory()->create(['product_id' => $product->id, 'path' => 'demo/second.svg', 'sort_order' => 20]);
        ProductImage::factory()->primary()->create(['product_id' => $product->id, 'path' => 'demo/primary.svg', 'sort_order' => 0]);
        ProductImage::factory()->create(['product_id' => $product->id, 'product_variant_id' => $blackVariant->id, 'path' => 'demo/black-variant.svg', 'sort_order' => 0]);

        $this->get(route('storefront.products.show', $product))
            ->assertOk()
            ->assertSeeInOrder(['demo/black-variant.svg', 'demo/primary.svg', 'demo/second.svg'])
            ->assertSee('demo/primary.svg', false);
    }

    public function test_specifications_render_normalized_values(): void
    {
        [$category, $material, $cotton, $leather, $connectivity, $wifi, $bluetooth, $weight, $waterResistant, $processor] = $this->specificationCategory();
        $product = $this->createPublicProduct('Specification Detail Product', category: $category);

        $this->specification($product, $material, ['value_ids' => [$cotton->id]]);
        $this->specification($product, $connectivity, ['value_ids' => [$wifi->id, $bluetooth->id]]);
        $this->specification($product, $weight, ['number_value' => 1.25]);
        $this->specification($product, $waterResistant, ['boolean_value' => true]);
        $this->specification($product, $processor, ['text_value' => 'A19 Pro']);

        $this->get(route('storefront.products.show', $product))
            ->assertOk()
            ->assertSeeText('Material')
            ->assertSeeText('Cotton')
            ->assertSeeText('Connectivity')
            ->assertSeeText('Wi-Fi, Bluetooth')
            ->assertSeeText('1.2500')
            ->assertSeeText('Yes')
            ->assertSeeText('A19 Pro');
    }

    public function test_breadcrumbs_and_related_products_use_public_category_context(): void
    {
        $root = Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);
        $child = Category::factory()->childOf($root)->create(['name' => 'Smartphones', 'slug' => 'smartphones']);
        $product = $this->createPublicProduct('Main Phone Detail', category: $child);
        $related = $this->createPublicProduct('Related Phone Detail', category: $child);
        $hidden = $this->createPublicProduct('Hidden Related Detail', category: $child, productState: ['approval_status' => ProductApprovalStatus::Pending]);

        $this->get(route('storefront.products.show', $product))
            ->assertOk()
            ->assertSeeTextInOrder(['Home', 'Electronics', 'Smartphones', 'Main Phone Detail'])
            ->assertSeeText('Related Phone Detail')
            ->assertDontSeeText('Hidden Related Detail');
    }

    public function test_product_cards_link_to_public_product_detail_pages(): void
    {
        $category = Category::factory()->create(['slug' => 'phones']);
        $product = $this->createPublicProduct('Linked Product Card', category: $category);
        HomepageSection::query()->create([
            'key' => 'products',
            'type' => HomepageSectionType::Products,
            'name' => 'Products',
            'title' => 'Products',
            'is_enabled' => true,
            'sort_order' => 10,
            'settings' => ['source' => 'latest', 'limit' => 8],
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('storefront.products.show', $product), false);

        $this->get(route('storefront.categories.show', $category))
            ->assertOk()
            ->assertSee(route('storefront.products.show', $product), false);

        $this->get(route('storefront.search', ['q' => 'Linked Product']))
            ->assertOk()
            ->assertSee(route('storefront.products.show', $product), false);
    }

    /**
     * @return array{0: Category, 1: ProductAttribute, 2: ProductAttribute, 3: ProductAttributeValue, 4: ProductAttributeValue, 5: ProductAttributeValue, 6: ProductAttributeValue}
     */
    private function variantCategory(): array
    {
        $set = ProductAttributeSet::factory()->create(['code' => 'detail_phone_set']);
        $category = Category::factory()->create(['product_attribute_set_id' => $set->id]);
        $color = ProductAttribute::factory()->variant()->create(['name' => 'Color', 'code' => 'color', 'is_filterable' => true, 'sort_order' => 10]);
        $storage = ProductAttribute::factory()->variant()->create(['name' => 'Storage', 'code' => 'storage', 'is_filterable' => true, 'sort_order' => 20]);
        $set->attributes()->attach($color->id, ['is_required' => true, 'sort_order' => 10]);
        $set->attributes()->attach($storage->id, ['is_required' => true, 'sort_order' => 20]);

        return [
            $category,
            $color,
            $storage,
            ProductAttributeValue::factory()->color('#000000')->create(['product_attribute_id' => $color->id, 'value' => 'Black', 'code' => 'black']),
            ProductAttributeValue::factory()->create(['product_attribute_id' => $color->id, 'value' => 'White', 'code' => 'white']),
            ProductAttributeValue::factory()->create(['product_attribute_id' => $storage->id, 'value' => '128GB', 'code' => '128gb']),
            ProductAttributeValue::factory()->create(['product_attribute_id' => $storage->id, 'value' => '256GB', 'code' => '256gb']),
        ];
    }

    private function specificationCategory(): array
    {
        $set = ProductAttributeSet::factory()->create(['code' => 'detail_spec_set']);
        $category = Category::factory()->create(['product_attribute_set_id' => $set->id]);

        $material = ProductAttribute::factory()->specification()->create(['name' => 'Material', 'code' => 'material', 'input_type' => ProductAttributeInputType::Select, 'sort_order' => 10]);
        $connectivity = ProductAttribute::factory()->specification()->create(['name' => 'Connectivity', 'code' => 'connectivity', 'input_type' => ProductAttributeInputType::Multiselect, 'sort_order' => 20]);
        $weight = ProductAttribute::factory()->specification()->create(['name' => 'Weight', 'code' => 'weight', 'input_type' => ProductAttributeInputType::Number, 'sort_order' => 30]);
        $waterResistant = ProductAttribute::factory()->specification()->create(['name' => 'Water Resistant', 'code' => 'water_resistant', 'input_type' => ProductAttributeInputType::Boolean, 'sort_order' => 40]);
        $processor = ProductAttribute::factory()->specification()->create(['name' => 'Processor', 'code' => 'processor', 'input_type' => ProductAttributeInputType::Text, 'sort_order' => 50]);

        foreach ([$material, $connectivity, $weight, $waterResistant, $processor] as $index => $attribute) {
            $set->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => ($index + 1) * 10]);
        }

        return [
            $category,
            $material,
            ProductAttributeValue::factory()->create(['product_attribute_id' => $material->id, 'value' => 'Cotton', 'code' => 'cotton']),
            ProductAttributeValue::factory()->create(['product_attribute_id' => $material->id, 'value' => 'Leather', 'code' => 'leather']),
            $connectivity,
            ProductAttributeValue::factory()->create(['product_attribute_id' => $connectivity->id, 'value' => 'Wi-Fi', 'code' => 'wi-fi']),
            ProductAttributeValue::factory()->create(['product_attribute_id' => $connectivity->id, 'value' => 'Bluetooth', 'code' => 'bluetooth']),
            $weight,
            $waterResistant,
            $processor,
        ];
    }

    private function specification(Product $product, ProductAttribute $attribute, array $payload): ProductSpecification
    {
        $specification = $product->specifications()->create([
            'product_attribute_id' => $attribute->id,
            'text_value' => $payload['text_value'] ?? null,
            'number_value' => $payload['number_value'] ?? null,
            'boolean_value' => $payload['boolean_value'] ?? null,
        ]);

        if (isset($payload['value_ids'])) {
            $specification->values()->attach($payload['value_ids']);
        }

        return $specification;
    }

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
                'description' => "{$name} long description",
            ]);

        $variants = $variants ?: [['sku' => str($name)->slug()->upper().'-SKU', 'base_price' => 10000, 'stock' => 10]];

        foreach ($variants as $index => $variantData) {
            $variant = ProductVariant::factory()->create([
                'product_id' => $product->id,
                'sku' => $variantData['sku'],
                'base_price' => $variantData['base_price'],
                'sale_price' => $variantData['sale_price'] ?? null,
                'is_default' => $index === 0,
                'combination_key' => 'detail-'.$index,
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

        return $product;
    }
}
