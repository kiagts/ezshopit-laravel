<?php

namespace Database\Seeders;

use App\Actions\Products\CreateProduct;
use App\Enums\ProductApprovalStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductImage;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $vendor = Vendor::query()->where('slug', 'techverse')->first();

        if (! $vendor) {
            return;
        }

        $adminId = User::query()->where('email', 'admin@example.com')->value('id');

        $this->createProduct($vendor, [
            'name' => 'Apple iPhone 17 Pro',
            'slug' => 'apple-iphone-17-pro',
            'type' => ProductType::Variant->value,
            'category' => 'electronics-mobile-phones-smartphones',
            'brand' => 'apple',
            'description' => 'Demo smartphone listing with variant colors, storage sizes, and structured specifications.',
            'variants' => [
                ['sku' => 'IPH17-BLK-8-256', 'base_price' => 129900, 'sale_price' => 124900, 'values' => ['color' => 'black', 'storage' => '256gb', 'ram' => '8gb']],
                ['sku' => 'IPH17-BLK-8-512', 'base_price' => 149900, 'sale_price' => null, 'values' => ['color' => 'black', 'storage' => '512gb', 'ram' => '8gb']],
                ['sku' => 'IPH17-WHT-8-256', 'base_price' => 129900, 'sale_price' => null, 'values' => ['color' => 'white', 'storage' => '256gb', 'ram' => '8gb']],
                ['sku' => 'IPH17-WHT-8-512', 'base_price' => 149900, 'sale_price' => 144900, 'values' => ['color' => 'white', 'storage' => '512gb', 'ram' => '8gb']],
            ],
            'specifications' => [
                'processor' => ['text_value' => 'A19 Pro'],
                'screen_size' => ['text_value' => '6.3 inches'],
                'battery_capacity' => ['number_value' => 4200],
                'operating_system' => ['product_attribute_value_id' => 'ios'],
                'connectivity' => ['product_attribute_value_ids' => ['wi-fi', 'bluetooth', '5g']],
                'warranty' => ['text_value' => '1 year limited warranty'],
                'weight' => ['number_value' => 0.19],
            ],
        ], ProductApprovalStatus::Approved, $adminId);

        $this->createProduct($vendor, [
            'name' => 'Samsung Galaxy S26 Ultra',
            'slug' => 'samsung-galaxy-s26-ultra',
            'type' => ProductType::Variant->value,
            'category' => 'electronics-mobile-phones-smartphones',
            'brand' => 'samsung',
            'description' => 'Demo Android flagship product with color and storage variants.',
            'variants' => [
                ['sku' => 'SGS26-BLK-12-256', 'base_price' => 119900, 'sale_price' => null, 'values' => ['color' => 'black', 'storage' => '256gb', 'ram' => '12gb']],
                ['sku' => 'SGS26-GRY-12-512', 'base_price' => 139900, 'sale_price' => 134900, 'values' => ['color' => 'gray', 'storage' => '512gb', 'ram' => '12gb']],
            ],
            'specifications' => [
                'processor' => ['text_value' => 'Snapdragon Elite'],
                'screen_size' => ['text_value' => '6.8 inches'],
                'battery_capacity' => ['number_value' => 5000],
                'operating_system' => ['product_attribute_value_id' => 'android'],
                'connectivity' => ['product_attribute_value_ids' => ['wi-fi', 'bluetooth', '5g']],
            ],
        ], ProductApprovalStatus::Approved, $adminId);

        $this->createProduct($vendor, [
            'name' => 'Xiaomi Redmi Note 16',
            'slug' => 'xiaomi-redmi-note-16',
            'type' => ProductType::Variant->value,
            'category' => 'electronics-mobile-phones-smartphones',
            'brand' => 'xiaomi',
            'description' => 'Demo mid-range smartphone with accessible storage and color options.',
            'variants' => [
                ['sku' => 'XRN16-BLK-6-128', 'base_price' => 34900, 'sale_price' => 31900, 'values' => ['color' => 'black', 'storage' => '128gb', 'ram' => '6gb']],
                ['sku' => 'XRN16-BLU-8-256', 'base_price' => 42900, 'sale_price' => null, 'values' => ['color' => 'blue', 'storage' => '256gb', 'ram' => '8gb']],
            ],
            'specifications' => [
                'processor' => ['text_value' => 'Snapdragon midrange platform'],
                'screen_size' => ['text_value' => '6.6 inches'],
                'battery_capacity' => ['number_value' => 5200],
                'operating_system' => ['product_attribute_value_id' => 'android'],
                'connectivity' => ['product_attribute_value_ids' => ['wi-fi', 'bluetooth', '5g']],
            ],
        ], ProductApprovalStatus::Approved, $adminId);

        $this->createProduct($vendor, [
            'name' => 'Dell Inspiron 14 Plus',
            'slug' => 'dell-inspiron-14-plus',
            'type' => ProductType::Variant->value,
            'category' => 'electronics-computers-laptops-laptops',
            'brand' => 'dell',
            'description' => 'Demo laptop listing with memory and storage choices for discovery filters.',
            'variants' => [
                ['sku' => 'DIN14-SLV-16-512', 'base_price' => 109900, 'sale_price' => 99900, 'values' => ['color' => 'silver', 'ram' => '16gb', 'storage' => '512gb']],
                ['sku' => 'DIN14-SLV-32-1TB', 'base_price' => 149900, 'sale_price' => null, 'values' => ['color' => 'silver', 'ram' => '32gb', 'storage' => '1tb']],
            ],
            'specifications' => [
                'processor' => ['text_value' => 'Intel Core Ultra 5'],
                'screen_size' => ['text_value' => '14 inches'],
                'operating_system' => ['product_attribute_value_id' => 'windows'],
                'connectivity' => ['product_attribute_value_ids' => ['wi-fi', 'bluetooth', 'usb-c']],
            ],
        ], ProductApprovalStatus::Approved, $adminId);

        $this->createProduct($vendor, [
            'name' => 'Lenovo ThinkPad X1',
            'slug' => 'lenovo-thinkpad-x1',
            'type' => ProductType::Variant->value,
            'category' => 'electronics-computers-laptops-laptops',
            'brand' => 'lenovo',
            'description' => 'Demo laptop product with memory and storage sellable variants.',
            'variants' => [
                ['sku' => 'TPX1-BLK-16-512', 'base_price' => 169900, 'sale_price' => 159900, 'values' => ['color' => 'black', 'ram' => '16gb', 'storage' => '512gb']],
                ['sku' => 'TPX1-BLK-32-1TB', 'base_price' => 219900, 'sale_price' => null, 'values' => ['color' => 'black', 'ram' => '32gb', 'storage' => '1tb']],
            ],
            'specifications' => [
                'processor' => ['text_value' => 'Intel Core Ultra 7'],
                'screen_size' => ['text_value' => '14 inches'],
                'operating_system' => ['product_attribute_value_id' => 'windows'],
                'connectivity' => ['product_attribute_value_ids' => ['wi-fi', 'bluetooth', 'usb-c']],
            ],
        ], ProductApprovalStatus::Pending, $adminId);

        $this->createProduct($vendor, [
            'name' => 'Nike Everyday Hoodie',
            'slug' => 'nike-everyday-hoodie',
            'type' => ProductType::Variant->value,
            'category' => 'fashion-men-clothing',
            'brand' => 'nike',
            'description' => 'Demo apparel product for size, color, and material discovery.',
            'variants' => [
                ['sku' => 'NEH-BLK-M', 'base_price' => 6999, 'sale_price' => 5999, 'values' => ['color' => 'black', 'size' => 'm']],
                ['sku' => 'NEH-GRY-L', 'base_price' => 6999, 'sale_price' => null, 'values' => ['color' => 'gray', 'size' => 'l']],
            ],
            'specifications' => [
                'material' => ['product_attribute_value_id' => 'cotton'],
            ],
        ], ProductApprovalStatus::Approved, $adminId);

        $this->createProduct($vendor, [
            'name' => 'Nike Running Shirt',
            'slug' => 'nike-running-shirt',
            'type' => ProductType::Variant->value,
            'category' => 'fashion-men-clothing',
            'brand' => 'nike',
            'description' => 'Demo fashion product using clothing attribute set variants.',
            'variants' => [
                ['sku' => 'NRS-BLU-M', 'base_price' => 3499, 'sale_price' => 2999, 'values' => ['color' => 'blue', 'size' => 'm']],
                ['sku' => 'NRS-BLU-L', 'base_price' => 3499, 'sale_price' => null, 'values' => ['color' => 'blue', 'size' => 'l']],
            ],
            'specifications' => [
                'material' => ['product_attribute_value_id' => 'cotton'],
            ],
        ], ProductApprovalStatus::Pending, $adminId);

        $this->createProduct($vendor, [
            'name' => 'Skechers Go Walk Sneaker',
            'slug' => 'skechers-go-walk-sneaker',
            'type' => ProductType::Variant->value,
            'category' => 'fashion-men-shoes',
            'brand' => 'skechers',
            'description' => 'Demo footwear product with shoe size and material filters.',
            'variants' => [
                ['sku' => 'SGW-BLK-42', 'base_price' => 12999, 'sale_price' => 11999, 'values' => ['color' => 'black', 'shoe_size' => 'eu-42']],
                ['sku' => 'SGW-GRY-43', 'base_price' => 12999, 'sale_price' => null, 'values' => ['color' => 'gray', 'shoe_size' => 'eu-43']],
            ],
            'specifications' => [
                'material' => ['product_attribute_value_id' => 'leather'],
            ],
        ], ProductApprovalStatus::Approved, $adminId);

        $this->createProduct($vendor, [
            'name' => 'Adidas Ultraboost',
            'slug' => 'adidas-ultraboost',
            'type' => ProductType::Variant->value,
            'category' => 'fashion-men-shoes',
            'brand' => 'adidas',
            'description' => 'Demo footwear product with color and shoe size variants.',
            'variants' => [
                ['sku' => 'AUB-BLK-42', 'base_price' => 18999, 'sale_price' => null, 'values' => ['color' => 'black', 'shoe_size' => 'eu-42']],
                ['sku' => 'AUB-WHT-43', 'base_price' => 18999, 'sale_price' => 17499, 'values' => ['color' => 'white', 'shoe_size' => 'eu-43']],
            ],
            'specifications' => [
                'material' => ['product_attribute_value_id' => 'leather'],
            ],
        ], ProductApprovalStatus::Rejected, $adminId);

        $this->createProduct($vendor, [
            'name' => 'Bosch Compact Mixer',
            'slug' => 'bosch-compact-mixer',
            'type' => ProductType::Simple->value,
            'category' => 'home-living-kitchen',
            'brand' => 'bosch',
            'description' => 'Demo kitchen appliance represented by one default sellable variant.',
            'variants' => [
                ['sku' => 'BOSCH-MIXER-COMPACT', 'base_price' => 24900, 'sale_price' => 22900],
            ],
            'specifications' => [
                'material' => ['product_attribute_value_id' => 'metal'],
                'warranty' => ['text_value' => '2 year limited warranty'],
            ],
        ], ProductApprovalStatus::Approved, $adminId);

        $this->createProduct($vendor, [
            'name' => 'Logitech MX Master 4',
            'slug' => 'logitech-mx-master-4',
            'type' => ProductType::Simple->value,
            'category' => 'electronics-computers-laptops-computer-accessories',
            'brand' => 'logitech',
            'description' => 'Demo computer accessory product for public search and brand discovery.',
            'variants' => [
                ['sku' => 'LOGI-MXM4', 'base_price' => 12900, 'sale_price' => 11900],
            ],
            'specifications' => [
                'connectivity' => ['product_attribute_value_ids' => ['bluetooth', 'usb-c']],
                'warranty' => ['text_value' => '1 year limited warranty'],
            ],
        ], ProductApprovalStatus::Approved, $adminId);

        $this->createProduct($vendor, [
            'name' => 'Sony WH-1000XM6 Headphones',
            'slug' => 'sony-wh-1000xm6-headphones',
            'type' => ProductType::Simple->value,
            'category' => 'electronics-audio-headphones',
            'brand' => 'sony',
            'description' => 'Demo simple product represented by one default sellable variant.',
            'variants' => [
                ['sku' => 'SONY-WH1000XM6', 'base_price' => 39900, 'sale_price' => 36900],
            ],
            'specifications' => [],
        ], ProductApprovalStatus::Draft, $adminId);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createProduct(Vendor $vendor, array $data, ProductApprovalStatus $approvalStatus, ?int $adminId): void
    {
        $product = Product::query()->where('slug', $data['slug'])->first();

        if (! $product) {
            $product = app(CreateProduct::class)->handle($vendor, [
                'name' => $data['name'],
                'slug' => $data['slug'],
                'type' => $data['type'],
                'status' => ProductStatus::Draft->value,
                'category_id' => Category::query()->where('slug', $data['category'])->value('id'),
                'brand_id' => Brand::query()->where('slug', $data['brand'])->value('id'),
                'short_description' => 'Seed demo product.',
                'description' => $data['description'],
                'variants' => collect($data['variants'])->map(fn (array $variant, int $index): array => [
                    'sku' => $variant['sku'],
                    'base_price' => $variant['base_price'],
                    'sale_price' => $variant['sale_price'] ?? null,
                    'is_default' => $index === 0,
                    'sort_order' => ($index + 1) * 10,
                    'attribute_value_ids' => collect($variant['values'] ?? [])
                        ->mapWithKeys(fn (string $valueCode, string $attributeCode): array => [
                            ProductAttribute::query()->where('code', $attributeCode)->value('id') => ProductAttributeValue::query()
                                ->where('code', $valueCode)
                                ->where('product_attribute_id', ProductAttribute::query()->where('code', $attributeCode)->value('id'))
                                ->value('id'),
                        ])
                        ->all(),
                ])->all(),
                'specifications' => collect($data['specifications'])->mapWithKeys(fn (array $payload, string $attributeCode): array => [
                    ProductAttribute::query()->where('code', $attributeCode)->value('id') => $this->resolveSpecificationPayload($attributeCode, $payload),
                ])->all(),
            ]);
        }

        $this->syncDemoMedia($product);
        $this->applyLifecycle($product, $approvalStatus, $adminId);
    }

    private function syncDemoMedia(Product $product): void
    {
        $palette = match (true) {
            str_contains($product->slug, 'iphone') => ['#111827', '#E5E7EB', 'Phone'],
            str_contains($product->slug, 'galaxy') => ['#1F2937', '#C7D2FE', 'Phone'],
            str_contains($product->slug, 'thinkpad') => ['#18181B', '#F87171', 'Laptop'],
            str_contains($product->slug, 'shirt') => ['#2563EB', '#DBEAFE', 'Apparel'],
            str_contains($product->slug, 'ultraboost') => ['#111827', '#F3F4F6', 'Shoe'],
            default => ['#0F172A', '#BAE6FD', 'Audio'],
        };

        $galleryPaths = [
            "demo/products/{$product->slug}-gallery-1.svg",
            "demo/products/{$product->slug}-gallery-2.svg",
        ];

        foreach ($galleryPaths as $index => $path) {
            $this->putDemoSvg($path, $product->name, $palette[2], $palette[0], $palette[1], $index + 1);

            ProductImage::query()->updateOrCreate(
                ['product_id' => $product->id, 'path' => $path],
                [
                    'product_variant_id' => null,
                    'alt_text' => $product->name.' demo image',
                    'is_primary' => $index === 0,
                    'sort_order' => $index,
                ],
            );
        }

        $product->galleryImages()
            ->whereNotIn('path', $galleryPaths)
            ->where('path', 'like', 'demo/products/%')
            ->delete();

        $variant = $product->variants()->where('is_active', true)->ordered()->first();
        if (! $variant) {
            return;
        }

        $variantPath = "demo/products/{$product->slug}-variant-{$variant->id}.svg";
        $this->putDemoSvg($variantPath, $product->name, 'Variant', $palette[1], $palette[0], 3);

        ProductImage::query()->updateOrCreate(
            ['product_id' => $product->id, 'path' => $variantPath],
            [
                'product_variant_id' => $variant->id,
                'alt_text' => $product->name.' variant image',
                'is_primary' => false,
                'sort_order' => 0,
            ],
        );
    }

    private function putDemoSvg(string $path, string $title, string $label, string $primary, string $secondary, int $index): void
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="1200" viewBox="0 0 1200 1200" role="img" aria-label="{$safeTitle}">
  <rect width="1200" height="1200" fill="{$secondary}"/>
  <rect x="160" y="160" width="880" height="880" rx="64" fill="{$primary}" opacity="0.94"/>
  <circle cx="900" cy="300" r="120" fill="white" opacity="0.16"/>
  <rect x="270" y="700" width="660" height="28" rx="14" fill="white" opacity="0.32"/>
  <rect x="340" y="760" width="520" height="24" rx="12" fill="white" opacity="0.22"/>
  <text x="600" y="560" text-anchor="middle" font-family="Arial, sans-serif" font-size="74" font-weight="700" fill="white">{$safeLabel}</text>
  <text x="600" y="640" text-anchor="middle" font-family="Arial, sans-serif" font-size="38" fill="white" opacity="0.82">Demo image {$index}</text>
</svg>
SVG;

        Storage::disk(config('marketplace.product_media.disk', 'public'))->put($path, $svg);
    }

    private function applyLifecycle(Product $product, ProductApprovalStatus $approvalStatus, ?int $adminId): void
    {
        $submittedAt = now()->subDays(2);

        $product->forceFill(match ($approvalStatus) {
            ProductApprovalStatus::Approved => [
                'status' => ProductStatus::Active,
                'approval_status' => ProductApprovalStatus::Approved,
                'submitted_at' => $submittedAt,
                'approved_at' => now()->subDay(),
                'approved_by' => $adminId,
                'rejection_reason' => null,
                'rejected_at' => null,
                'rejected_by' => null,
                'published_at' => now()->subDay(),
            ],
            ProductApprovalStatus::Pending => [
                'status' => ProductStatus::Active,
                'approval_status' => ProductApprovalStatus::Pending,
                'submitted_at' => $submittedAt,
                'approved_at' => null,
                'approved_by' => null,
                'rejection_reason' => null,
                'rejected_at' => null,
                'rejected_by' => null,
            ],
            ProductApprovalStatus::Rejected => [
                'status' => ProductStatus::Draft,
                'approval_status' => ProductApprovalStatus::Rejected,
                'submitted_at' => $submittedAt,
                'approved_at' => null,
                'approved_by' => null,
                'rejection_reason' => 'Please provide a clearer product description and complete the required specifications.',
                'rejected_at' => now()->subDay(),
                'rejected_by' => $adminId,
            ],
            ProductApprovalStatus::Draft => [
                'status' => ProductStatus::Draft,
                'approval_status' => ProductApprovalStatus::Draft,
                'submitted_at' => null,
                'approved_at' => null,
                'approved_by' => null,
                'rejection_reason' => null,
                'rejected_at' => null,
                'rejected_by' => null,
            ],
        })->save();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function resolveSpecificationPayload(string $attributeCode, array $payload): array
    {
        $attributeId = ProductAttribute::query()->where('code', $attributeCode)->value('id');

        if (isset($payload['product_attribute_value_id'])) {
            $payload['product_attribute_value_id'] = ProductAttributeValue::query()
                ->where('product_attribute_id', $attributeId)
                ->where('code', $payload['product_attribute_value_id'])
                ->value('id');
        }

        if (isset($payload['product_attribute_value_ids'])) {
            $payload['product_attribute_value_ids'] = ProductAttributeValue::query()
                ->where('product_attribute_id', $attributeId)
                ->whereIn('code', $payload['product_attribute_value_ids'])
                ->pluck('id')
                ->all();
        }

        return $payload;
    }
}
