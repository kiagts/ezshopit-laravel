<?php

namespace Tests\Feature\Storefront;

use App\Enums\ProductApprovalStatus;
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
use App\Models\ProductVariant;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorStorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_vendor_store_renders_public_identity_without_private_fields(): void
    {
        $vendor = Vendor::factory()->approved()->create([
            'display_name' => 'TechVerse',
            'slug' => 'techverse',
            'description' => 'Technology for everyday life.',
            'email' => 'private@techverse.test',
            'phone' => '+1-555-0000',
            'tax_number' => 'TAX-SECRET',
            'registration_number' => 'REG-SECRET',
            'address_line_1' => '123 Private Street',
        ]);

        $this->get(route('storefront.stores.show', $vendor))
            ->assertOk()
            ->assertSeeText('TechVerse')
            ->assertSeeText('Technology for everyday life.')
            ->assertSeeText('Marketplace seller')
            ->assertDontSeeText('private@techverse.test')
            ->assertDontSeeText('+1-555-0000')
            ->assertDontSeeText('TAX-SECRET')
            ->assertDontSeeText('REG-SECRET')
            ->assertDontSeeText('123 Private Street');
    }

    public function test_unapproved_vendor_stores_return_not_found(): void
    {
        foreach ([VendorStatus::Draft, VendorStatus::Pending, VendorStatus::Rejected, VendorStatus::Suspended] as $status) {
            $vendor = Vendor::factory()->create(['status' => $status, 'slug' => 'seller-'.$status->value]);

            $this->get(route('storefront.stores.show', $vendor))->assertNotFound();
        }
    }

    public function test_store_catalog_only_shows_current_vendor_public_products(): void
    {
        $vendor = Vendor::factory()->approved()->create(['display_name' => 'TechVerse', 'slug' => 'techverse']);
        $otherVendor = Vendor::factory()->approved()->create(['display_name' => 'HomeCraft', 'slug' => 'homecraft']);
        $category = Category::factory()->create(['name' => 'Smartphones', 'slug' => 'smartphones']);

        $this->createPublicProduct('Visible TechVerse Phone', vendor: $vendor, category: $category);
        $this->createPublicProduct('Other Vendor Phone', vendor: $otherVendor, category: $category);
        $this->createPublicProduct('Draft TechVerse Phone', vendor: $vendor, category: $category, productState: ['status' => ProductStatus::Draft]);
        $this->createPublicProduct('Pending TechVerse Phone', vendor: $vendor, category: $category, productState: ['approval_status' => ProductApprovalStatus::Pending]);
        $this->createPublicProduct('Rejected TechVerse Phone', vendor: $vendor, category: $category, productState: ['approval_status' => ProductApprovalStatus::Rejected]);
        $this->createPublicProduct('Inactive TechVerse Phone', vendor: $vendor, category: $category, productState: ['status' => ProductStatus::Inactive]);
        $this->createPublicProduct('Archived TechVerse Phone', vendor: $vendor, category: $category, productState: ['status' => ProductStatus::Archived]);

        $this->get(route('storefront.stores.show', $vendor))
            ->assertOk()
            ->assertSeeText('Visible TechVerse Phone')
            ->assertDontSeeText('Other Vendor Phone')
            ->assertDontSeeText('Draft TechVerse Phone')
            ->assertDontSeeText('Pending TechVerse Phone')
            ->assertDontSeeText('Rejected TechVerse Phone')
            ->assertDontSeeText('Inactive TechVerse Phone')
            ->assertDontSeeText('Archived TechVerse Phone');
    }

    public function test_store_search_category_brand_price_and_availability_filters_are_vendor_scoped(): void
    {
        $vendor = Vendor::factory()->approved()->create(['display_name' => 'TechVerse', 'slug' => 'techverse']);
        $otherVendor = Vendor::factory()->approved()->create(['display_name' => 'Other Seller']);
        $phones = Category::factory()->create(['name' => 'Smartphones', 'slug' => 'smartphones']);
        $laptops = Category::factory()->create(['name' => 'Laptops', 'slug' => 'laptops']);
        $apple = Brand::factory()->create(['name' => 'Apple', 'slug' => 'apple']);
        $samsung = Brand::factory()->create(['name' => 'Samsung', 'slug' => 'samsung']);

        $this->createPublicProduct('Alpha iPhone', vendor: $vendor, category: $phones, brand: $apple, variants: [['sku' => 'ALPHA-IPHONE', 'base_price' => 100000, 'sale_price' => 90000, 'stock' => 8]]);
        $this->createPublicProduct('Budget Galaxy', vendor: $vendor, category: $phones, brand: $samsung, variants: [['sku' => 'BUDGET-GALAXY', 'base_price' => 30000, 'stock' => 0]]);
        $this->createPublicProduct('Creator Laptop', vendor: $vendor, category: $laptops, brand: $apple, variants: [['sku' => 'CREATOR-LAPTOP', 'base_price' => 250000, 'stock' => 3]]);
        $this->createPublicProduct('Alpha Rival Phone', vendor: $otherVendor, category: $phones, brand: $apple, variants: [['sku' => 'ALPHA-RIVAL', 'base_price' => 80000, 'stock' => 5]]);

        $this->get(route('storefront.stores.show', [$vendor, 'q' => 'Alpha']))
            ->assertOk()
            ->assertSeeText('Alpha iPhone')
            ->assertDontSeeText('Alpha Rival Phone')
            ->assertDontSeeText('Creator Laptop');

        $this->get(route('storefront.stores.show', [$vendor, 'category' => 'laptops']))
            ->assertOk()
            ->assertSeeText('Creator Laptop')
            ->assertDontSeeText('Alpha iPhone');

        $this->get(route('storefront.stores.show', [$vendor, 'brand' => ['samsung']]))
            ->assertOk()
            ->assertSeeText('Budget Galaxy')
            ->assertDontSeeText('Alpha iPhone');

        $this->get(route('storefront.stores.show', [$vendor, 'price_min' => '800', 'price_max' => '1000']))
            ->assertOk()
            ->assertSeeText('Alpha iPhone')
            ->assertDontSeeText('Budget Galaxy')
            ->assertDontSeeText('Creator Laptop');

        $this->get(route('storefront.stores.show', [$vendor, 'in_stock' => 1]))
            ->assertOk()
            ->assertSeeText('Alpha iPhone')
            ->assertSeeText('Creator Laptop')
            ->assertDontSeeText('Budget Galaxy');
    }

    public function test_leaf_category_variant_attribute_filters_work_under_vendor_scope(): void
    {
        [$category, $color, $black, $white] = $this->variantCategory();
        $vendor = Vendor::factory()->approved()->create(['display_name' => 'TechVerse', 'slug' => 'techverse']);
        $otherVendor = Vendor::factory()->approved()->create();

        $this->createPublicProduct('Black Store Phone', vendor: $vendor, category: $category, productState: ['type' => ProductType::Variant], variants: [
            ['sku' => 'STORE-BLACK', 'base_price' => 10000, 'values' => [$color->id => $black->id]],
        ]);
        $this->createPublicProduct('White Store Phone', vendor: $vendor, category: $category, productState: ['type' => ProductType::Variant], variants: [
            ['sku' => 'STORE-WHITE', 'base_price' => 10000, 'values' => [$color->id => $white->id]],
        ]);
        $this->createPublicProduct('Black Rival Phone', vendor: $otherVendor, category: $category, productState: ['type' => ProductType::Variant], variants: [
            ['sku' => 'RIVAL-BLACK', 'base_price' => 10000, 'values' => [$color->id => $black->id]],
        ]);

        $this->get(route('storefront.stores.show', [
            $vendor,
            'category' => 'smartphones',
            'attributes' => ['color' => ['black']],
        ]))
            ->assertOk()
            ->assertSeeText('Color')
            ->assertSeeText('Black Store Phone')
            ->assertDontSeeText('White Store Phone')
            ->assertDontSeeText('Black Rival Phone');
    }

    public function test_store_pagination_preserves_query_parameters(): void
    {
        $vendor = Vendor::factory()->approved()->create(['slug' => 'techverse']);
        $category = Category::factory()->create(['slug' => 'phones']);

        foreach (range(1, 26) as $index) {
            $this->createPublicProduct('Paged Phone '.$index, vendor: $vendor, category: $category);
        }

        $this->get(route('storefront.stores.show', [$vendor, 'q' => 'Paged', 'category' => 'phones']))
            ->assertOk()
            ->assertSee('page=2', false)
            ->assertSee('q=Paged', false)
            ->assertSee('category=phones', false);
    }

    public function test_product_detail_and_homepage_store_cards_link_to_public_store(): void
    {
        $vendor = Vendor::factory()->approved()->create(['display_name' => 'TechVerse', 'slug' => 'techverse']);
        $product = $this->createPublicProduct('Seller Linked Product', vendor: $vendor);
        HomepageSection::query()->create([
            'key' => 'stores',
            'type' => HomepageSectionType::Vendors,
            'name' => 'Stores',
            'title' => 'Stores',
            'is_enabled' => true,
            'sort_order' => 10,
            'settings' => ['source' => 'latest', 'limit' => 8],
        ]);

        $this->get(route('storefront.products.show', $product))
            ->assertOk()
            ->assertSee(route('storefront.stores.show', $vendor), false)
            ->assertSeeText('Visit Store');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('storefront.stores.show', $vendor), false);
    }

    public function test_suspended_vendor_products_disappear_from_store_product_detail_search_and_category(): void
    {
        $vendor = Vendor::factory()->approved()->create(['slug' => 'techverse']);
        $category = Category::factory()->create(['slug' => 'smartphones']);
        $product = $this->createPublicProduct('Suspension Sensitive Phone', vendor: $vendor, category: $category);

        $vendor->forceFill(['status' => VendorStatus::Suspended])->save();

        $this->get(route('storefront.stores.show', $vendor))->assertNotFound();
        $this->get(route('storefront.products.show', $product))->assertNotFound();
        $this->get(route('storefront.search', ['q' => 'Suspension Sensitive']))
            ->assertOk()
            ->assertDontSeeText('Suspension Sensitive Phone');
        $this->get(route('storefront.categories.show', $category))
            ->assertOk()
            ->assertDontSeeText('Suspension Sensitive Phone');
    }

    /**
     * @return array{0: Category, 1: ProductAttribute, 2: ProductAttributeValue, 3: ProductAttributeValue}
     */
    private function variantCategory(): array
    {
        $set = ProductAttributeSet::factory()->create(['name' => 'Smartphone', 'code' => 'smartphone']);
        $category = Category::factory()->create([
            'name' => 'Smartphones',
            'slug' => 'smartphones',
            'product_attribute_set_id' => $set->id,
        ]);
        $color = ProductAttribute::factory()->variant()->create([
            'name' => 'Color',
            'code' => 'color',
            'type' => ProductAttributeType::Variant,
            'is_filterable' => true,
        ]);
        $set->attributes()->attach($color->id, ['is_required' => true, 'sort_order' => 10]);

        return [
            $category,
            $color,
            ProductAttributeValue::factory()->create(['product_attribute_id' => $color->id, 'value' => 'Black', 'code' => 'black']),
            ProductAttributeValue::factory()->create(['product_attribute_id' => $color->id, 'value' => 'White', 'code' => 'white']),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $variants
     * @param array<string, mixed> $productState
     */
    private function createPublicProduct(
        string $name,
        ?Vendor $vendor = null,
        ?Category $category = null,
        ?Brand $brand = null,
        array $variants = [],
        array $productState = [],
    ): Product {
        $vendor ??= Vendor::factory()->approved()->create();
        $category ??= Category::factory()->create();
        $brand ??= Brand::factory()->create();

        $product = Product::factory()
            ->approved()
            ->state($productState)
            ->create([
                'name' => $name,
                'type' => $productState['type'] ?? ProductType::Simple,
                'vendor_id' => $vendor->id,
                'category_id' => $category->id,
                'brand_id' => $brand->id,
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
                'combination_key' => 'store-'.$index,
            ]);

            foreach ($variantData['values'] ?? [] as $attributeId => $valueId) {
                $variant->attributeValues()->attach($valueId, ['product_attribute_id' => $attributeId]);
            }

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
