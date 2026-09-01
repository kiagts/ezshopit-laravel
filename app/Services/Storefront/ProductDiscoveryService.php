<?php

namespace App\Services\Storefront;

use App\Enums\ProductAttributeInputType;
use App\Enums\ProductAttributeType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use App\Models\Vendor;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProductDiscoveryService
{
    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function discover(array $filters, ?Category $category = null, int $perPage = 24, ?Vendor $vendor = null): array
    {
        $filters = $this->normalize($filters, $category);
        $filterCategory = $category ?? $this->selectedFilterCategory($filters, $vendor);
        $filterableAttributes = $this->filterableAttributes($filterCategory);
        $selectedAttributeFilters = $this->selectedAttributeFilters($filters['attributes'], $filterableAttributes);

        $query = $this->baseQuery($category, $filters, $vendor);
        $this->applyFilters($query, $filters, $selectedAttributeFilters);
        $this->applySort($query, $filters['sort'], filled($filters['q']));

        /** @var LengthAwarePaginator $products */
        $products = $query
            ->paginate($perPage)
            ->withQueryString();

        return [
            'products' => $products,
            'filters' => $filters,
            'sortOptions' => $this->sortOptions(),
            'brandOptions' => $this->brandOptions($category, $filters, $vendor),
            'categoryOptions' => $category ? collect() : $this->categoryOptions($filters, $vendor),
            'attributeFilters' => $this->attributeOptions($filterCategory, $filters, $filterableAttributes, $vendor),
            'activeFilters' => $this->activeFilters($filters, $filterCategory, $filterableAttributes),
            'activeFilterCount' => $this->activeFilterCount($filters),
            'filterCategory' => $filterCategory,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function normalize(array $filters, ?Category $category): array
    {
        $filters['sort'] = array_key_exists($filters['sort'] ?? '', $this->sortOptions()) ? $filters['sort'] : 'featured';
        $filters['brand'] = $this->validBrandSlugs($filters['brand'] ?? []);
        $filters['category'] = $category ? [] : $this->validCategorySlugs($filters['category'] ?? []);
        $filters['price_min_minor'] = $this->parsePrice($filters['price_min'] ?? null);
        $filters['price_max_minor'] = $this->parsePrice($filters['price_max'] ?? null);

        if ($filters['price_min_minor'] !== null && $filters['price_max_minor'] !== null && $filters['price_min_minor'] > $filters['price_max_minor']) {
            [$filters['price_min_minor'], $filters['price_max_minor']] = [$filters['price_max_minor'], $filters['price_min_minor']];
            [$filters['price_min'], $filters['price_max']] = [$filters['price_max'], $filters['price_min']];
        }

        return $filters;
    }

    /**
     * @param array<int, string> $slugs
     * @return array<int, string>
     */
    private function validBrandSlugs(array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }

        return Brand::query()
            ->active()
            ->whereIn('slug', $slugs)
            ->pluck('slug')
            ->all();
    }

    /**
     * @param array<int, string> $slugs
     * @return array<int, string>
     */
    private function validCategorySlugs(array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }

        return Category::query()
            ->active()
            ->whereIn('slug', $slugs)
            ->get()
            ->filter(fn (Category $category): bool => $this->categoryIsPublic($category))
            ->pluck('slug')
            ->values()
            ->all();
    }

    private function parsePrice(?string $value): ?int
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Money::parseDecimal($value);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function baseQuery(?Category $category, array $filters, ?Vendor $vendor = null): Builder
    {
        return Product::query()
            ->publiclyVisible()
            ->select('products.*')
            ->with([
                'vendor:id,display_name,slug',
                'brand:id,name,slug',
                'primaryImage',
                'firstGalleryImage',
                'variants' => fn ($query) => $query->active()->with('inventory')->orderBy('sort_order')->orderBy('sku'),
            ])
            ->when($vendor, fn (Builder $query): Builder => $query->forVendor($vendor))
            ->when($category, fn (Builder $query): Builder => $query->whereIn('category_id', $this->categoryAndDescendantIds($category)))
            ->when(filled($filters['q']), fn (Builder $query): Builder => $this->applySearch($query, $filters['q']))
            ->when($filters['category'] !== [], function (Builder $query) use ($filters): void {
                $ids = Category::query()
                    ->whereIn('slug', $filters['category'])
                    ->get()
                    ->flatMap(fn (Category $category): Collection => $this->categoryAndDescendantIds($category))
                    ->unique()
                    ->values()
                    ->all();

                $query->whereIn('category_id', $ids);
            });
    }

    private function applySearch(Builder $query, string $search): Builder
    {
        $term = '%'.addcslashes($search, '%_\\').'%';

        return $query->where(function (Builder $query) use ($term): void {
            $query
                ->where('products.name', 'like', $term)
                ->orWhere('products.short_description', 'like', $term)
                ->orWhereHas('brand', fn (Builder $query): Builder => $query->where('name', 'like', $term)->orWhere('slug', 'like', $term))
                ->orWhereHas('vendor', fn (Builder $query): Builder => $query->where('display_name', 'like', $term)->orWhere('business_name', 'like', $term))
                ->orWhereHas('variants', fn (Builder $query): Builder => $query->where('sku', 'like', $term));
        });
    }

    /**
     * @param array<string, array<int, string>> $attributeFilters
     */
    private function applyFilters(Builder $query, array $filters, array $attributeFilters): void
    {
        if ($filters['brand'] !== []) {
            $query->whereHas('brand', fn (Builder $query): Builder => $query->whereIn('slug', $filters['brand'])->active());
        }

        $variantFilters = collect($attributeFilters)
            ->filter(fn (array $filter): bool => $filter['attribute']->type === ProductAttributeType::Variant);

        $specificationFilters = collect($attributeFilters)
            ->filter(fn (array $filter): bool => $filter['attribute']->type === ProductAttributeType::Specification);

        if ($this->hasVariantConstrainedFilter($filters, $variantFilters->all())) {
            $query->whereHas('variants', function (Builder $query) use ($filters, $variantFilters): void {
                $query->active();
                $this->applyVariantConstraints($query, $filters, $variantFilters->all());
            });
        }

        foreach ($specificationFilters as $filter) {
            /** @var ProductAttribute $attribute */
            $attribute = $filter['attribute'];
            $values = $filter['values'];

            $query->whereHas('specifications', function (Builder $query) use ($attribute, $values): void {
                $query->where('product_attribute_id', $attribute->id);

                if ($attribute->input_type->usesPredefinedValues()) {
                    $query->whereHas('values', fn (Builder $query): Builder => $query->whereIn('code', $values)->active());
                } elseif ($attribute->input_type === ProductAttributeInputType::Boolean) {
                    $booleanValues = collect($values)
                        ->map(fn (string $value): ?bool => match ($value) {
                            '1', 'true', 'yes' => true,
                            '0', 'false', 'no' => false,
                            default => null,
                        })
                        ->filter(fn (?bool $value): bool => $value !== null)
                        ->values()
                        ->all();

                    $query->whereIn('boolean_value', $booleanValues);
                }
            });
        }

        if (! $this->hasVariantConstrainedFilter($filters, $variantFilters->all()) && $filters['in_stock']) {
            $query->whereHas('variants', function (Builder $query): void {
                $query
                    ->active()
                    ->whereHas('inventory', fn (Builder $query): Builder => $query->whereRaw('(on_hand - reserved) > 0'));
            });
        }
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<int, array{attribute: ProductAttribute, values: array<int, string>}> $variantFilters
     */
    private function hasVariantConstrainedFilter(array $filters, array $variantFilters): bool
    {
        return $filters['price_min_minor'] !== null
            || $filters['price_max_minor'] !== null
            || $filters['in_stock']
            || $variantFilters !== [];
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<int, array{attribute: ProductAttribute, values: array<int, string>}> $variantFilters
     */
    private function applyVariantConstraints(Builder $query, array $filters, array $variantFilters): void
    {
        foreach ($variantFilters as $filter) {
            $attribute = $filter['attribute'];
            $values = $filter['values'];

            $query->whereHas('attributeValues', function (Builder $query) use ($attribute, $values): void {
                $query
                    ->where('product_attribute_values.product_attribute_id', $attribute->id)
                    ->whereIn('product_attribute_values.code', $values)
                    ->active();
            });
        }

        if ($filters['price_min_minor'] !== null) {
            $query->whereRaw('COALESCE(sale_price, base_price) >= ?', [$filters['price_min_minor']]);
        }

        if ($filters['price_max_minor'] !== null) {
            $query->whereRaw('COALESCE(sale_price, base_price) <= ?', [$filters['price_max_minor']]);
        }

        if ($filters['in_stock']) {
            $query->whereHas('inventory', fn (Builder $query): Builder => $query->whereRaw('(on_hand - reserved) > 0'));
        }
    }

    private function applySort(Builder $query, string $sort, bool $hasSearch): void
    {
        $priceSubquery = ProductVariant::query()
            ->selectRaw('MIN(COALESCE(sale_price, base_price))')
            ->whereColumn('product_variants.product_id', 'products.id')
            ->where('is_active', true)
            ->whereNull('deleted_at');

        match ($sort) {
            'newest' => $query->orderByDesc('published_at')->orderByDesc('id'),
            'price_asc' => $query->orderBy($priceSubquery),
            'price_desc' => $query->orderByDesc($priceSubquery),
            'name_asc' => $query->orderBy('name')->orderBy('id'),
            default => $query
                ->orderByDesc('is_featured')
                ->orderByDesc('published_at')
                ->orderByDesc('id'),
        };
    }

    /**
     * @return array<string, string>
     */
    private function sortOptions(): array
    {
        return [
            'featured' => 'Featured',
            'newest' => 'Newest',
            'price_asc' => 'Price: Low to High',
            'price_desc' => 'Price: High to Low',
            'name_asc' => 'Name: A-Z',
        ];
    }

    private function filterableAttributes(?Category $category): Collection
    {
        if (! $category || $this->activePublicChildren($category)->isNotEmpty() || ! $category->product_attribute_set_id) {
            return collect();
        }

        return ProductAttribute::query()
            ->whereHas('attributeSets', fn (Builder $query): Builder => $query->whereKey($category->product_attribute_set_id))
            ->active()
            ->where('is_filterable', true)
            ->whereIn('input_type', [
                ProductAttributeInputType::Select,
                ProductAttributeInputType::Multiselect,
                ProductAttributeInputType::Boolean,
            ])
            ->with('activeValues')
            ->ordered()
            ->get();
    }

    /**
     * @param array<string, array<int, string>> $selected
     * @return array<string, array{attribute: ProductAttribute, values: array<int, string>}>
     */
    private function selectedAttributeFilters(array $selected, Collection $filterableAttributes): array
    {
        return $filterableAttributes
            ->filter(fn (ProductAttribute $attribute): bool => array_key_exists($attribute->code, $selected))
            ->mapWithKeys(function (ProductAttribute $attribute) use ($selected): array {
                $values = $attribute->input_type === ProductAttributeInputType::Boolean
                    ? collect($selected[$attribute->code])->intersect(['1', '0', 'true', 'false', 'yes', 'no'])->values()->all()
                    : $attribute->activeValues->whereIn('code', $selected[$attribute->code])->pluck('code')->values()->all();

                return $values === []
                    ? []
                    : [$attribute->code => ['attribute' => $attribute, 'values' => $values]];
            })
            ->all();
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function brandOptions(?Category $category, array $filters, ?Vendor $vendor = null): Collection
    {
        return Brand::query()
            ->active()
            ->whereHas('products', function (Builder $query) use ($category, $filters, $vendor): void {
                $query->publiclyVisible()
                    ->when($vendor, fn (Builder $query): Builder => $query->forVendor($vendor))
                    ->when($category, fn (Builder $query): Builder => $query->whereIn('category_id', $this->categoryAndDescendantIds($category)))
                    ->when($filters['category'] !== [], function (Builder $query) use ($filters): void {
                        $ids = Category::query()
                            ->whereIn('slug', $filters['category'])
                            ->get()
                            ->flatMap(fn (Category $category): Collection => $this->categoryAndDescendantIds($category))
                            ->unique()
                            ->values()
                            ->all();

                        $query->whereIn('category_id', $ids);
                    })
                    ->when(filled($filters['q']), fn (Builder $query): Builder => $this->applySearch($query, $filters['q']));
            })
            ->ordered()
            ->get(['id', 'name', 'slug']);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function categoryOptions(array $filters, ?Vendor $vendor = null): Collection
    {
        if (! $vendor && blank($filters['q'])) {
            return collect();
        }

        return Category::query()
            ->active()
            ->whereHas('products', function (Builder $query) use ($filters, $vendor): void {
                $query
                    ->publiclyVisible()
                    ->when($vendor, fn (Builder $query): Builder => $query->forVendor($vendor))
                    ->when(filled($filters['q']), fn (Builder $query): Builder => $this->applySearch($query, $filters['q']));
            })
            ->ordered()
            ->get(['id', 'parent_id', 'name', 'slug'])
            ->filter(fn (Category $category): bool => $this->categoryIsPublic($category))
            ->values();
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function attributeOptions(?Category $category, array $filters, Collection $attributes, ?Vendor $vendor = null): Collection
    {
        if (! $category || $attributes->isEmpty()) {
            return collect();
        }

        return $attributes
            ->map(function (ProductAttribute $attribute) use ($category, $filters, $vendor): ?array {
                if ($attribute->input_type === ProductAttributeInputType::Boolean) {
                    $hasTrue = Product::query()
                        ->publiclyVisible()
                        ->when($vendor, fn (Builder $query): Builder => $query->forVendor($vendor))
                        ->whereIn('category_id', $this->categoryAndDescendantIds($category))
                        ->when(filled($filters['q']), fn (Builder $query): Builder => $this->applySearch($query, $filters['q']))
                        ->whereHas('specifications', fn (Builder $query): Builder => $query
                            ->where('product_attribute_id', $attribute->id)
                            ->where('boolean_value', true))
                        ->exists();

                    return $hasTrue ? [
                        'attribute' => $attribute,
                        'options' => collect([['code' => '1', 'label' => 'Yes']]),
                    ] : null;
                }

                $values = ProductAttributeValue::query()
                    ->active()
                    ->where('product_attribute_id', $attribute->id)
                    ->where(function (Builder $query) use ($attribute, $category, $filters, $vendor): void {
                        if ($attribute->type === ProductAttributeType::Variant) {
                            $query->whereHas('productVariants', function (Builder $query) use ($category, $filters, $vendor): void {
                                $query
                                    ->active()
                                    ->whereHas('product', function (Builder $query) use ($category, $filters, $vendor): void {
                                        $query
                                            ->publiclyVisible()
                                            ->when($vendor, fn (Builder $query): Builder => $query->forVendor($vendor))
                                            ->whereIn('category_id', $this->categoryAndDescendantIds($category))
                                            ->when(filled($filters['q']), fn (Builder $query): Builder => $this->applySearch($query, $filters['q']));
                                    });
                            });
                        } else {
                            $query->whereHas('specifications.product', function (Builder $query) use ($category, $filters, $vendor): void {
                                $query
                                    ->publiclyVisible()
                                    ->when($vendor, fn (Builder $query): Builder => $query->forVendor($vendor))
                                    ->whereIn('category_id', $this->categoryAndDescendantIds($category))
                                    ->when(filled($filters['q']), fn (Builder $query): Builder => $this->applySearch($query, $filters['q']));
                            });
                        }
                    })
                    ->ordered()
                    ->limit(24)
                    ->get(['id', 'product_attribute_id', 'value', 'code', 'metadata']);

                if ($values->isEmpty()) {
                    return null;
                }

                return [
                    'attribute' => $attribute,
                    'options' => $values->map(fn (ProductAttributeValue $value): array => [
                        'code' => $value->code,
                        'label' => $value->value,
                        'hex' => $value->colorHex(),
                    ]),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function selectedFilterCategory(array $filters, ?Vendor $vendor = null): ?Category
    {
        if (count($filters['category']) !== 1) {
            return null;
        }

        /** @var Category|null $category */
        $category = Category::query()
            ->where('slug', $filters['category'][0])
            ->with('parent.parent.parent', 'productAttributeSet')
            ->first();

        if (! $category || ! $this->categoryIsPublic($category)) {
            return null;
        }

        if ($vendor && ! Product::query()
            ->publiclyVisible()
            ->forVendor($vendor)
            ->whereIn('category_id', $this->categoryAndDescendantIds($category))
            ->exists()) {
            return null;
        }

        return $category;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function activeFilters(array $filters, ?Category $category, Collection $filterableAttributes): Collection
    {
        $chips = collect();

        foreach (Brand::query()->whereIn('slug', $filters['brand'])->pluck('name', 'slug') as $slug => $label) {
            $chips->push(['label' => $label, 'url' => $this->removeFilterUrl(['brand' => $slug])]);
        }

        foreach (Category::query()->whereIn('slug', $filters['category'])->pluck('name', 'slug') as $slug => $label) {
            $chips->push(['label' => $label, 'url' => $this->removeFilterUrl(['category' => $slug])]);
        }

        if ($filters['price_min']) {
            $chips->push(['label' => 'Min '.Money::format($filters['price_min_minor']), 'url' => $this->removeFilterUrl(['price_min' => true])]);
        }

        if ($filters['price_max']) {
            $chips->push(['label' => 'Max '.Money::format($filters['price_max_minor']), 'url' => $this->removeFilterUrl(['price_max' => true])]);
        }

        if ($filters['in_stock']) {
            $chips->push(['label' => 'In stock only', 'url' => $this->removeFilterUrl(['in_stock' => true])]);
        }

        foreach ($filterableAttributes as $attribute) {
            $selected = $filters['attributes'][$attribute->code] ?? [];
            if ($selected === []) {
                continue;
            }

            if ($attribute->input_type === ProductAttributeInputType::Boolean) {
                foreach ($selected as $value) {
                    $chips->push(['label' => $attribute->name.': '.($value === '0' ? 'No' : 'Yes'), 'url' => $this->removeFilterUrl(['attribute' => [$attribute->code, $value]])]);
                }

                continue;
            }

            foreach ($attribute->activeValues->whereIn('code', $selected) as $value) {
                $chips->push(['label' => $value->value, 'url' => $this->removeFilterUrl(['attribute' => [$attribute->code, $value->code]])]);
            }
        }

        return $chips;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function activeFilterCount(array $filters): int
    {
        return count($filters['brand'])
            + count($filters['category'])
            + collect($filters['attributes'])->flatten()->count()
            + ($filters['price_min'] ? 1 : 0)
            + ($filters['price_max'] ? 1 : 0)
            + ($filters['in_stock'] ? 1 : 0);
    }

    /**
     * @param array<string, mixed> $remove
     */
    private function removeFilterUrl(array $remove): string
    {
        $query = request()->query();
        unset($query['page']);

        if (isset($remove['brand'])) {
            $query['brand'] = collect($query['brand'] ?? [])->reject(fn (string $value): bool => $value === $remove['brand'])->values()->all();
        }

        if (isset($remove['category'])) {
            $query['category'] = collect($query['category'] ?? [])->reject(fn (string $value): bool => $value === $remove['category'])->values()->all();
        }

        if (isset($remove['price_min'])) {
            unset($query['price_min']);
        }

        if (isset($remove['price_max'])) {
            unset($query['price_max']);
        }

        if (isset($remove['in_stock'])) {
            unset($query['in_stock']);
        }

        if (isset($remove['attribute'])) {
            [$attribute, $value] = $remove['attribute'];
            $query['attributes'][$attribute] = collect($query['attributes'][$attribute] ?? [])->reject(fn (string $item): bool => $item === $value)->values()->all();
            if ($query['attributes'][$attribute] === []) {
                unset($query['attributes'][$attribute]);
            }
            if (($query['attributes'] ?? []) === []) {
                unset($query['attributes']);
            }
        }

        $query = collect($query)->filter(fn (mixed $value): bool => $value !== [] && $value !== null && $value !== '')->all();

        return url()->current().($query ? '?'.http_build_query($query) : '');
    }

    public function categoryIsPublic(Category $category): bool
    {
        if (! $category->is_active || $category->trashed()) {
            return false;
        }

        $parent = $category->parent;

        while ($parent) {
            if (! $parent->is_active || $parent->trashed()) {
                return false;
            }

            $parent = $parent->parent;
        }

        return true;
    }

    public function breadcrumbs(Category $category): Collection
    {
        $items = collect([$category]);
        $parent = $category->parent;

        while ($parent) {
            $items->prepend($parent);
            $parent = $parent->parent;
        }

        return $items->values();
    }

    public function activePublicChildren(Category $category): Collection
    {
        return $category
            ->activeChildren()
            ->with(['activeChildren' => fn ($query) => $query->ordered()])
            ->ordered()
            ->get()
            ->filter(fn (Category $child): bool => $this->categoryIsPublic($child))
            ->values();
    }

    public function vendorCategories(Vendor $vendor): Collection
    {
        return Category::query()
            ->active()
            ->whereHas('products', fn (Builder $query): Builder => $query
                ->publiclyVisible()
                ->forVendor($vendor))
            ->ordered()
            ->get(['id', 'parent_id', 'name', 'slug'])
            ->filter(fn (Category $category): bool => $this->categoryIsPublic($category))
            ->values();
    }

    public function categoryAndDescendantIds(Category $category): Collection
    {
        return $category->descendantIds()->prepend($category->id)->values();
    }
}
