<?php

namespace App\Services\Homepage;

use App\Enums\HomepageSectionItemType;
use App\Enums\HomepageSectionType;
use App\Enums\VendorStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HomepageSection;
use App\Models\Product;
use App\Models\Vendor;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class HomepageService
{
    /**
     * @return array<string, mixed>
     */
    public function build(bool $preview = false): array
    {
        $sections = HomepageSection::query()
            ->with(['items', 'contentItems'])
            ->when(! $preview, fn (Builder $query) => $query->visible())
            ->ordered()
            ->get();

        $resolved = $sections
            ->map(fn (HomepageSection $section): ?array => $this->resolve($section, $preview))
            ->filter()
            ->values();

        $productIds = $resolved
            ->flatMap(fn (array $section): Collection => $section['products'] ?? collect())
            ->pluck('id')
            ->unique()
            ->values();

        return [
            'homepageSections' => $resolved,
            'wishlistedProductIds' => $this->wishlistedProductIds($productIds),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolve(HomepageSection $section, bool $preview): ?array
    {
        $type = $section->type;
        $settings = $section->settings ?? [];
        $base = [
            'model' => $section,
            'key' => $section->key,
            'type' => $type,
            'view' => 'storefront.home.sections.'.$type->value,
            'title' => $section->title,
            'subtitle' => $section->subtitle,
            'settings' => $settings,
        ];

        return match ($type) {
            HomepageSectionType::Hero => $base + [
                'slides' => $this->contentItems($section),
            ],
            HomepageSectionType::PromotionGrid => $this->withContent($base, $section, 3),
            HomepageSectionType::BannerSlider => $this->withContent($base, $section, 1),
            HomepageSectionType::Benefits => $this->withContent($base, $section, 1),
            HomepageSectionType::Categories => $this->withEntities($base, 'categories', $this->categories($section)),
            HomepageSectionType::Products => $this->withEntities($base, 'products', $this->products($section)),
            HomepageSectionType::ProductShowcase => $this->resolveProductShowcase($base, $section),
            HomepageSectionType::Brands => $this->withEntities($base, 'brands', $this->brands($section)),
            HomepageSectionType::Vendors => $this->withEntities($base, 'vendors', $this->vendors($section)),
            HomepageSectionType::ReadyToGrow => $base + [
                'products' => $this->products($section),
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>|null
     */
    private function withContent(array $base, HomepageSection $section, int $minimumItems): ?array
    {
        $items = $this->contentItems($section);

        if ($section->type === HomepageSectionType::BannerSlider) {
            $items = $items->filter(fn (array $item): bool => filled($item['image']))->values();
        }

        if ($items->count() < $minimumItems) {
            return null;
        }

        return $base + ['contentItems' => $items];
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>|null
     */
    private function withEntities(array $base, string $key, Collection $entities): ?array
    {
        if ($entities->isEmpty()) {
            return null;
        }

        return $base + [$key => $entities];
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>|null
     */
    private function resolveProductShowcase(array $base, HomepageSection $section): ?array
    {
        $products = $this->products($section);

        if ($products->isEmpty()) {
            return null;
        }

        $featured = $products->first();

        return $base + [
            'featuredProduct' => $this->featuredProductPayload($featured),
            'products' => $products->skip(1)->values(),
        ];
    }

    private function products(HomepageSection $section): Collection
    {
        $settings = $section->settings ?? [];
        $limit = min(max((int) ($settings['limit'] ?? 8), 1), 24);
        $source = $settings['source'] ?? 'latest';

        if ($source === 'manual') {
            $ids = $this->itemIds($section, HomepageSectionItemType::Product);

            return $this->productQuery()
                ->whereIn('id', $ids)
                ->get()
                ->sortBy(fn (Product $product): int => array_search($product->id, $ids, true))
                ->take($limit)
                ->values();
        }

        $query = $this->productQuery();

        if ($source === 'featured') {
            $query->orderByDesc('is_featured')->latest('published_at')->latest('id');
        } elseif ($source === 'on_sale') {
            $query
                ->whereHas('variants', function (Builder $query): void {
                    $query->active()
                        ->whereNotNull('sale_price')
                        ->whereColumn('sale_price', '<', 'base_price');
                })
                ->latest('published_at')
                ->latest('id');
        } elseif ($source === 'category') {
            $category = Category::query()->find($settings['category_id'] ?? null);
            $ids = $category ? $category->descendantIds()->prepend($category->id)->all() : [];
            $query->whereIn('category_id', $ids)->latest('published_at')->latest('id');
        } else {
            $query->latest('published_at')->latest('id');
        }

        return $query->limit($limit)->get();
    }

    private function categories(HomepageSection $section): Collection
    {
        $settings = $section->settings ?? [];
        $limit = min(max((int) ($settings['limit'] ?? 8), 1), 24);

        if (($settings['source'] ?? 'latest') === 'manual') {
            $ids = $this->itemIds($section, HomepageSectionItemType::Category);

            return Category::query()
                ->active()
                ->with(['activeChildren' => fn ($query) => $query->limit(6)])
                ->whereDoesntHave('parent', fn (Builder $query) => $query->where('is_active', false))
                ->whereIn('id', $ids)
                ->get()
                ->filter(fn (Category $category): bool => $this->categoryIsVisible($category))
                ->sortBy(fn (Category $category): int => array_search($category->id, $ids, true))
                ->take($limit)
                ->values();
        }

        return Category::query()
            ->roots()
            ->active()
            ->ordered()
            ->with(['activeChildren' => fn ($query) => $query->limit(6)])
            ->limit($limit)
            ->get();
    }

    private function brands(HomepageSection $section): Collection
    {
        $settings = $section->settings ?? [];
        $limit = min(max((int) ($settings['limit'] ?? 5), 1), 24);

        if (($settings['source'] ?? 'latest') === 'manual') {
            $ids = $this->itemIds($section, HomepageSectionItemType::Brand);

            return Brand::query()
                ->active()
                ->withCount(['products' => fn (Builder $query) => $query->publiclyVisible()])
                ->whereIn('id', $ids)
                ->get()
                ->sortBy(fn (Brand $brand): int => array_search($brand->id, $ids, true))
                ->take($limit)
                ->values();
        }

        return Brand::query()
            ->active()
            ->withCount(['products' => fn (Builder $query) => $query->publiclyVisible()])
            ->ordered()
            ->limit($limit)
            ->get();
    }

    private function vendors(HomepageSection $section): Collection
    {
        $settings = $section->settings ?? [];
        $limit = min(max((int) ($settings['limit'] ?? 4), 1), 24);

        if (($settings['source'] ?? 'latest') === 'manual') {
            $ids = $this->itemIds($section, HomepageSectionItemType::Vendor);

            return Vendor::query()
                ->approved()
                ->withCount(['products' => fn (Builder $query) => $query->publiclyVisible()])
                ->whereIn('id', $ids)
                ->get()
                ->sortBy(fn (Vendor $vendor): int => array_search($vendor->id, $ids, true))
                ->take($limit)
                ->values();
        }

        return Vendor::query()
            ->where('status', VendorStatus::Approved)
            ->withCount(['products' => fn (Builder $query) => $query->publiclyVisible()])
            ->orderBy('display_name')
            ->limit($limit)
            ->get();
    }

    private function productQuery(): Builder
    {
        return Product::query()
            ->publiclyVisible()
            ->with([
                'vendor:id,display_name,slug',
                'brand:id,name',
                'primaryImage',
                'firstGalleryImage',
                'variants' => fn ($query) => $query
                    ->active()
                    ->with('inventory')
                    ->ordered(),
            ]);
    }

    private function categoryIsVisible(Category $category): bool
    {
        $visited = [];
        while (true) {
            if (! $category->is_active || isset($visited[$category->id])) {
                return false;
            }
            $visited[$category->id] = true;
            if ($category->parent_id === null) {
                return true;
            }
            $category = $category->parent;
            if (! $category) {
                return false;
            }
        }
    }

    private function contentItems(HomepageSection $section): Collection
    {
        return $section->contentItems
            ->map(fn ($item): array => [
                'title' => $item->title,
                'body' => $item->body,
                'image' => $this->imageUrl($item->image_path),
                'mobile_image' => $this->imageUrl($item->mobile_image_path) ?: $this->imageUrl($item->image_path),
                'image_alt' => $item->image_alt ?: $item->title,
                'icon_key' => $item->icon_key,
                'cta_label' => $item->cta_label,
                'cta_url' => $item->cta_url,
                'settings' => $item->settings ?? [],
            ])
            ->values();
    }

    /**
     * @return array<int, int>
     */
    private function itemIds(HomepageSection $section, HomepageSectionItemType $type): array
    {
        return $section->items
            ->where('item_type', $type)
            ->pluck('item_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, int>  $productIds
     * @return array<int, int>
     */
    private function wishlistedProductIds(Collection $productIds): array
    {
        $user = auth()->user();

        if (! $user || $productIds->isEmpty()) {
            return [];
        }

        return $user->wishlistItems()
            ->whereIn('product_id', $productIds)
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function featuredProductPayload(Product $product): array
    {
        $price = $product->storefrontPrice();
        $image = $product->displayImage();

        return [
            'name' => $product->name,
            'brand' => $product->brand?->name,
            'seller' => $product->vendor?->display_name,
            'description' => $product->short_description ?: $product->description,
            'image' => $image ? Storage::url($image->path) : null,
            'starting_at' => (bool) ($price['from'] ?? false),
            'price' => $price['effective'] ? Money::format($price['effective']) : null,
            'old_price' => ($price['sale'] ?? null) && ($price['base'] ?? null) ? Money::format($price['base']) : null,
            'discount' => ($price['discount'] ?? null) ? $price['discount'].'% off' : null,
            'badge' => 'Featured',
            'stock_label' => $product->publicStockState() === 'low_stock' ? 'Limited stock' : null,
            'url' => route('storefront.products.show', $product),
        ];
    }

    private function imageUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $path = (string) $path;

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, 'demo/') || str_starts_with($path, 'homepage/')) {
            return Storage::url($path);
        }

        return $path;
    }
}
