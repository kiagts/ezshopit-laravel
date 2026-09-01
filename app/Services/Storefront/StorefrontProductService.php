<?php

namespace App\Services\Storefront;

use App\Enums\ProductAttributeInputType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductSpecification;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Support\Collection;

class StorefrontProductService
{
    public function __construct(
        private readonly ProductDiscoveryService $discovery,
        private readonly WishlistStateService $wishlistState,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function detail(Product $product): array
    {
        abort_unless(Product::query()->publiclyVisible()->whereKey($product)->exists(), 404);

        $product->load([
            'vendor:id,display_name,slug,description,logo_path,created_at',
            'brand:id,name,slug',
            'category.parent.parent.parent',
            'category.productAttributeSet.attributes',
            'galleryImages',
            'variants' => fn ($query) => $query
                ->active()
                ->with([
                    'attributeValues.productAttribute',
                    'images',
                    'inventory',
                ])
                ->ordered(),
            'specifications.attribute',
            'specifications.values',
        ]);

        abort_unless($this->discovery->categoryIsPublic($product->category), 404);

        $variants = $product->variants;
        $initialVariant = $this->initialVariant($variants);
        $gallery = $this->galleryForVariant($product, $initialVariant);
        $relatedProducts = $this->relatedProducts($product);

        return [
            'product' => $product,
            'breadcrumbs' => $this->discovery->breadcrumbs($product->category),
            'gallery' => $gallery,
            'initialVariant' => $initialVariant,
            'variantOptions' => $this->variantOptions($variants),
            'variantPayload' => $this->variantPayload($product, $variants),
            'specifications' => $this->specifications($product),
            'relatedProducts' => $relatedProducts,
            'isWishlisted' => $this->wishlistState->isWishlisted(auth()->user(), $product),
            'wishlistedProductIds' => $this->wishlistState->idsForProducts(auth()->user(), $relatedProducts),
            'navigationCategories' => $this->navigationCategories(),
        ];
    }

    private function initialVariant(Collection $variants): ProductVariant
    {
        $default = $variants->first(fn (ProductVariant $variant): bool => $variant->is_default);
        $inStockDefault = $default && $default->isInStock() ? $default : null;
        $firstInStock = $variants->first(fn (ProductVariant $variant): bool => $variant->isInStock());

        return $inStockDefault
            ?? $firstInStock
            ?? $default
            ?? $variants->first();
    }

    private function galleryForVariant(Product $product, ?ProductVariant $variant): Collection
    {
        $variantImages = $variant?->images ?? collect();
        $productImages = $product->galleryImages
            ->sortBy(fn (ProductImage $image): array => [$image->is_primary ? 0 : 1, $image->sort_order, $image->id])
            ->values();

        return $variantImages
            ->concat($productImages)
            ->unique('id')
            ->values();
    }

    private function variantOptions(Collection $variants): Collection
    {
        return $variants
            ->flatMap(fn (ProductVariant $variant): Collection => $variant->attributeValues)
            ->groupBy(fn ($value): string => $value->productAttribute->code)
            ->map(function (Collection $values): array {
                $attribute = $values->first()->productAttribute;

                return [
                    'code' => $attribute->code,
                    'name' => $attribute->name,
                    'values' => $values
                        ->unique('code')
                        ->sortBy('sort_order')
                        ->map(fn ($value): array => [
                            'code' => $value->code,
                            'label' => $value->value,
                            'hex' => $value->colorHex(),
                        ])
                        ->values(),
                ];
            })
            ->sortBy(fn (array $group): int => $variants
                ->flatMap(fn (ProductVariant $variant): Collection => $variant->attributeValues)
                ->firstWhere('productAttribute.code', $group['code'])
                ?->productAttribute
                ?->sort_order ?? 0)
            ->values();
    }

    private function variantPayload(Product $product, Collection $variants): Collection
    {
        return $variants->map(fn (ProductVariant $variant): array => [
            'id' => $variant->id,
            'sku' => $variant->sku,
            'label' => $variant->displayName(),
            'is_default' => $variant->is_default,
            'is_in_stock' => $variant->isInStock(),
            'stock_state' => $variant->publicStockState(),
            'stock_label' => str($variant->publicStockState())->replace('_', ' ')->title()->toString(),
            'price' => $variant->storefrontPrice(),
            'price_html' => view('components.storefront.price', ['price' => $variant->storefrontPrice()])->render(),
            'attributes' => $variant->attributeValues
                ->mapWithKeys(fn ($value): array => [$value->productAttribute->code => $value->code])
                ->all(),
            'images' => $this->galleryForVariant($product, $variant)
                ->map(fn (ProductImage $image): array => $this->imagePayload($image, $product->name, $variant))
                ->values(),
        ]);
    }

    private function imagePayload(ProductImage $image, string $productName, ?ProductVariant $variant = null): array
    {
        return [
            'id' => $image->id,
            'url' => $image->url(),
            'alt' => $image->alt_text ?: ($variant ? $productName.' - '.$variant->displayName() : $productName),
            'is_variant' => $image->product_variant_id !== null,
        ];
    }

    private function specifications(Product $product): Collection
    {
        return $product->specifications
            ->filter(fn (ProductSpecification $specification): bool => $specification->attribute !== null)
            ->sortBy(fn (ProductSpecification $specification): int => $this->specificationSort($product, $specification))
            ->map(fn (ProductSpecification $specification): array => [
                'label' => $specification->attribute->name,
                'value' => $this->specificationValue($specification),
            ])
            ->filter(fn (array $row): bool => filled($row['value']))
            ->values();
    }

    private function specificationSort(Product $product, ProductSpecification $specification): int
    {
        $setAttribute = $product->category->productAttributeSet?->attributes
            ->firstWhere('id', $specification->product_attribute_id);

        return $setAttribute?->pivot?->sort_order ?? $specification->attribute->sort_order;
    }

    private function specificationValue(ProductSpecification $specification): ?string
    {
        return match ($specification->attribute->input_type) {
            ProductAttributeInputType::Select, ProductAttributeInputType::Multiselect => $specification->values->pluck('value')->filter()->implode(', '),
            ProductAttributeInputType::Number => $specification->number_value !== null ? (string) $specification->number_value : null,
            ProductAttributeInputType::Boolean => $specification->boolean_value === null ? null : ($specification->boolean_value ? 'Yes' : 'No'),
            default => $specification->text_value,
        };
    }

    private function relatedProducts(Product $product): Collection
    {
        return Product::query()
            ->publiclyVisible()
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->id)
            ->with([
                'vendor:id,display_name,slug',
                'brand:id,name,slug',
                'primaryImage',
                'firstGalleryImage',
                'variants' => fn ($query) => $query->active()->with('inventory')->ordered(),
            ])
            ->orderByDesc('is_featured')
            ->latest('published_at')
            ->limit(4)
            ->get();
    }

    private function navigationCategories(): Collection
    {
        return Category::query()
            ->roots()
            ->active()
            ->with(['activeChildren' => fn ($query) => $query->ordered()->limit(6)])
            ->ordered()
            ->limit(8)
            ->get();
    }
}
