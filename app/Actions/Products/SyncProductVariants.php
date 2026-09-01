<?php

namespace App\Actions\Products;

use App\Actions\Inventory\EnsureInventoryExists;
use App\Enums\ProductAttributeInputType;
use App\Enums\ProductAttributeType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SyncProductVariants
{
    public function __construct(
        private readonly GenerateVariantCombinationKey $keyGenerator,
        private readonly EnsureInventoryExists $ensureInventoryExists,
    ) {}

    /**
     * @param array<int, array<string, mixed>> $variants
     */
    public function handle(Product $product, Category $category, array $variants): void
    {
        DB::transaction(function () use ($product, $category, $variants): void {
            if ($product->isSimple()) {
                $payload = $variants[0] ?? [];
                $variant = $product->variants()->withTrashed()->where('combination_key', 'default')->first();
                $this->validateSku($payload['sku'] ?? null, $variant);

                $product->variants()->where('combination_key', '!=', 'default')->delete();

                $isExistingVariant = (bool) $variant;
                $variant = $variant ?: $product->variants()->make(['combination_key' => 'default']);
                $variant->fill([
                    'sku' => $this->normalizeSku($payload['sku'] ?? ''),
                    'name' => null,
                    'base_price' => $this->money($payload['base_price'] ?? null, 'variants.base_price'),
                    'sale_price' => $this->optionalMoney($payload['sale_price'] ?? null, 'variants.sale_price'),
                    'is_active' => true,
                    'is_default' => true,
                    'sort_order' => 10,
                ]);
                $this->ensureSalePrice($variant->base_price, $variant->sale_price);
                if ($isExistingVariant && $variant->trashed()) {
                    $variant->restore();
                }
                $variant->save();
                $this->ensureInventoryExists->handle($variant);
                $variant->attributeValues()->detach();

                return;
            }

            if ($variants === []) {
                throw ValidationException::withMessages(['variants' => 'Add at least one variant combination.']);
            }

            $seenKeys = [];
            $keptKeys = [];
            $defaultKey = null;
            $activeCount = 0;
            $existing = $product->variants()->withTrashed()->with('attributeValues')->get()->keyBy('combination_key');

            foreach (array_values($variants) as $index => $payload) {
                $pairs = $this->validatedPairs($category, $payload['attribute_value_ids'] ?? []);
                $key = $this->keyGenerator->handle($pairs);

                if (isset($seenKeys[$key])) {
                    throw ValidationException::withMessages(['variants' => 'Duplicate variant combinations are not allowed.']);
                }

                $seenKeys[$key] = true;
                $keptKeys[] = $key;
                $variant = $existing->get($key);
                $this->validateSku($payload['sku'] ?? null, $variant);

                $basePrice = $this->money($payload['base_price'] ?? null, 'variants.base_price');
                $salePrice = $this->optionalMoney($payload['sale_price'] ?? null, 'variants.sale_price');
                $this->ensureSalePrice($basePrice, $salePrice);
                $isActive = filter_var($payload['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);

                if ($isActive) {
                    $activeCount++;
                }

                if (filter_var($payload['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN) || $defaultKey === null) {
                    $defaultKey = $key;
                }

                $isExistingVariant = (bool) $variant;
                $variant = $variant ?: $product->variants()->make(['combination_key' => $key]);
                $variant->fill([
                    'sku' => $this->normalizeSku($payload['sku'] ?? ''),
                    'name' => $payload['name'] ?? $this->variantName($pairs),
                    'base_price' => $basePrice,
                    'sale_price' => $salePrice,
                    'is_active' => $isActive,
                    'is_default' => false,
                    'sort_order' => (int) ($payload['sort_order'] ?? (($index + 1) * 10)),
                ]);
                if ($isExistingVariant && $variant->trashed()) {
                    $variant->restore();
                }
                $variant->save();
                $this->ensureInventoryExists->handle($variant);
                $variant->attributeValues()->sync(collect($pairs)->mapWithKeys(fn (int $valueId, int $attributeId): array => [
                    $valueId => ['product_attribute_id' => $attributeId],
                ])->all());
            }

            if ($activeCount === 0) {
                throw ValidationException::withMessages(['variants' => 'At least one active variant is required.']);
            }

            $product->variants()->whereNotIn('combination_key', $keptKeys)->delete();
            $product->variants()->update(['is_default' => false]);
            $product->variants()->where('combination_key', $defaultKey)->update(['is_default' => true]);
        });
    }

    /**
     * @param array<int|string, int|string> $attributeValueIdsByAttributeId
     * @return array<int, int>
     */
    private function validatedPairs(Category $category, array $attributeValueIdsByAttributeId): array
    {
        $allowed = $category->productAttributeSet?->attributes()
            ->where('product_attributes.type', ProductAttributeType::Variant->value)
            ->where('product_attributes.input_type', ProductAttributeInputType::Select->value)
            ->pluck('product_attribute_set_attributes.is_required', 'product_attributes.id') ?? collect();

        if ($allowed->isEmpty()) {
            throw ValidationException::withMessages(['variants' => 'This category does not support variant options.']);
        }

        $pairs = [];

        foreach ($attributeValueIdsByAttributeId as $attributeId => $valueId) {
            if (! filled($valueId)) {
                continue;
            }

            $attributeId = (int) $attributeId;
            $valueId = (int) $valueId;

            if (! $allowed->has($attributeId) || isset($pairs[$attributeId])) {
                throw ValidationException::withMessages(['variants' => 'Variant options must belong to the selected category.']);
            }

            $value = ProductAttributeValue::query()
                ->whereKey($valueId)
                ->where('product_attribute_id', $attributeId)
                ->where('is_active', true)
                ->first();

            if (! $value) {
                throw ValidationException::withMessages(['variants' => 'Variant values must be active and match their option.']);
            }

            $pairs[$attributeId] = $valueId;
        }

        if ($pairs === []) {
            throw ValidationException::withMessages(['variants' => 'Variant products require selected option values.']);
        }

        return $pairs;
    }

    private function validateSku(mixed $sku, ?ProductVariant $ignoreVariant = null): void
    {
        $normalized = $this->normalizeSku($sku);

        if ($normalized === '') {
            throw ValidationException::withMessages(['variants.sku' => 'SKU is required.']);
        }

        if (ProductVariant::query()->withTrashed()->where('sku', $normalized)->when($ignoreVariant, fn ($query) => $query->whereKeyNot($ignoreVariant->id))->exists()) {
            throw ValidationException::withMessages(['variants.sku' => 'SKU must be unique.']);
        }
    }

    private function normalizeSku(mixed $sku): string
    {
        return Str::upper(trim((string) $sku));
    }

    private function money(mixed $value, string $field): int
    {
        if (! is_numeric($value) || (int) $value < 0) {
            throw ValidationException::withMessages([$field => 'Price must be a non-negative minor-unit amount.']);
        }

        return (int) $value;
    }

    private function optionalMoney(mixed $value, string $field): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->money($value, $field);
    }

    private function ensureSalePrice(int $basePrice, ?int $salePrice): void
    {
        if ($salePrice !== null && $salePrice > $basePrice) {
            throw ValidationException::withMessages(['variants.sale_price' => 'Sale price cannot be greater than regular price.']);
        }
    }

    /**
     * @param array<int, int> $pairs
     */
    private function variantName(array $pairs): string
    {
        $orderedIds = array_values($pairs);

        return ProductAttributeValue::query()
            ->whereIn('id', $orderedIds)
            ->get()
            ->sortBy(fn (ProductAttributeValue $value): int => array_search($value->id, $orderedIds, true))
            ->pluck('value')
            ->implode(' / ');
    }
}
