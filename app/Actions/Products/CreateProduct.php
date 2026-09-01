<?php

namespace App\Actions\Products;

use App\Actions\Concerns\GeneratesUniqueSlugs;
use App\Actions\Inventory\EnsureInventoryExists;
use App\Enums\ProductApprovalStatus;
use App\Enums\ProductAttributeInputType;
use App\Enums\ProductAttributeType;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\VendorStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductSpecification;
use App\Models\ProductVariant;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateProduct
{
    use GeneratesUniqueSlugs;

    public function __construct(
        private readonly GenerateVariantCombinationKey $keyGenerator,
        private readonly EnsureInventoryExists $ensureInventoryExists,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function handle(Vendor $vendor, array $data): Product
    {
        return DB::transaction(function () use ($vendor, $data): Product {
            $this->ensureVendorCanCreateProducts($vendor);

            $type = ProductType::tryFrom((string) ($data['type'] ?? ProductType::Simple->value))
                ?? throw ValidationException::withMessages(['type' => 'Invalid product type.']);
            $category = Category::query()->with(['parent', 'productAttributeSet.attributes'])->findOrFail((int) ($data['category_id'] ?? 0));
            $this->ensureCategoryCanReceiveProduct($category, $type);

            $brandId = $data['brand_id'] ?? null;
            if (filled($brandId)) {
                $this->ensureBrandCanBeUsed((int) $brandId);
            }

            $slug = filled($data['slug'] ?? null) ? Str::slug((string) $data['slug']) : $this->uniqueSlugFor(Product::class, (string) ($data['name'] ?? 'product'));

            if (Product::query()->where('slug', $slug)->exists()) {
                throw ValidationException::withMessages(['slug' => 'Product slug must be unique.']);
            }

            $product = new Product([
                'category_id' => $category->id,
                'brand_id' => filled($brandId) ? (int) $brandId : null,
                'name' => (string) ($data['name'] ?? ''),
                'slug' => $slug,
                'type' => $type,
                'status' => ProductStatus::tryFrom((string) ($data['status'] ?? ProductStatus::Draft->value)) ?? ProductStatus::Draft,
                'short_description' => $data['short_description'] ?? null,
                'description' => $data['description'] ?? null,
            ]);
            $product->vendor()->associate($vendor);
            $product->forceFill(['approval_status' => ProductApprovalStatus::Draft, 'is_featured' => false]);
            $product->save();

            if ($type === ProductType::Simple) {
                $variantData = $data['default_variant'] ?? collect($data['variants'] ?? [])->first() ?? [];
                $this->createVariant($product, $category, $variantData, true, []);
            } else {
                foreach ($data['variants'] ?? [] as $index => $variantData) {
                    $this->createVariant($product, $category, $variantData, (bool) ($variantData['is_default'] ?? $index === 0), $variantData['attribute_value_ids'] ?? []);
                }
            }

            $this->syncSpecifications($product, $category, $data['specifications'] ?? []);

            return $product->load(['variants.attributeValues', 'specifications.values']);
        });
    }

    private function ensureVendorCanCreateProducts(Vendor $vendor): void
    {
        if ($vendor->status !== VendorStatus::Approved) {
            throw ValidationException::withMessages(['vendor' => 'Only approved vendors can create products.']);
        }
    }

    private function ensureCategoryCanReceiveProduct(Category $category, ProductType $type): void
    {
        if (! $category->isPubliclyUsable()) {
            throw ValidationException::withMessages(['category_id' => 'Products must use an active leaf category with active ancestors.']);
        }

        if ($type === ProductType::Variant) {
            $hasVariantAttributes = $category->productAttributeSet?->attributes()
                ->where('product_attributes.type', ProductAttributeType::Variant->value)
                ->where('product_attributes.input_type', ProductAttributeInputType::Select->value)
                ->exists() ?? false;

            if (! $hasVariantAttributes) {
                throw ValidationException::withMessages(['category_id' => 'Variant products require a category attribute set with variant attributes.']);
            }
        }
    }

    private function ensureBrandCanBeUsed(int $brandId): void
    {
        if (! Brand::query()->whereKey($brandId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['brand_id' => 'The selected brand is not available.']);
        }
    }

    /**
     * @param array<string, mixed> $variantData
     * @param array<int, int> $attributeValueIdsByAttributeId
     */
    private function createVariant(Product $product, Category $category, array $variantData, bool $isDefault, array $attributeValueIdsByAttributeId): ProductVariant
    {
        $basePrice = $this->money($variantData['base_price'] ?? null, 'variants.base_price');
        $salePrice = $this->optionalMoney($variantData['sale_price'] ?? null, 'variants.sale_price');

        if ($salePrice !== null && $salePrice > $basePrice) {
            throw ValidationException::withMessages(['variants.sale_price' => 'Sale price cannot be greater than base price.']);
        }

        $normalizedSku = Str::upper(trim((string) ($variantData['sku'] ?? '')));
        if ($normalizedSku === '') {
            throw ValidationException::withMessages(['variants.sku' => 'Variant SKU is required.']);
        }

        if (ProductVariant::query()->where('sku', $normalizedSku)->exists()) {
            throw ValidationException::withMessages(['variants.sku' => 'Variant SKU must be unique.']);
        }

        if ($product->isSimple() && $attributeValueIdsByAttributeId !== []) {
            throw ValidationException::withMessages(['variants' => 'Simple products cannot have variant attribute values.']);
        }

        $pairs = $product->isVariant()
            ? $this->validatedVariantPairs($category, $attributeValueIdsByAttributeId)
            : [];

        $combinationKey = $this->keyGenerator->handle($pairs);

        if ($product->variants()->where('combination_key', $combinationKey)->exists()) {
            throw ValidationException::withMessages(['variants' => 'Duplicate variant combinations are not allowed.']);
        }

        $variant = $product->variants()->create([
            'sku' => $normalizedSku,
            'name' => $variantData['name'] ?? null,
            'base_price' => $basePrice,
            'sale_price' => $salePrice,
            'is_active' => filter_var($variantData['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'is_default' => $isDefault,
            'combination_key' => $combinationKey,
            'sort_order' => (int) ($variantData['sort_order'] ?? 0),
        ]);

        foreach ($pairs as $attributeId => $valueId) {
            $variant->attributeValues()->attach($valueId, ['product_attribute_id' => $attributeId]);
        }

        $this->ensureInventoryExists->handle($variant);

        return $variant;
    }

    /**
     * @param array<int, int|string> $attributeValueIdsByAttributeId
     * @return array<int, int>
     */
    private function validatedVariantPairs(Category $category, array $attributeValueIdsByAttributeId): array
    {
        if ($attributeValueIdsByAttributeId === []) {
            throw ValidationException::withMessages(['variants.attribute_value_ids' => 'Variant products require attribute values.']);
        }

        $set = $category->productAttributeSet;
        $allowed = $set?->attributes()
            ->where('product_attributes.type', ProductAttributeType::Variant->value)
            ->where('product_attributes.input_type', ProductAttributeInputType::Select->value)
            ->pluck('product_attribute_set_attributes.is_required', 'product_attributes.id') ?? collect();

        $pairs = [];
        foreach ($attributeValueIdsByAttributeId as $attributeId => $valueId) {
            $attributeId = (int) $attributeId;
            $valueId = (int) $valueId;

            if (! $allowed->has($attributeId) || isset($pairs[$attributeId])) {
                throw ValidationException::withMessages(['variants.attribute_value_ids' => 'Variant attributes must belong to the category attribute set and appear once per variant.']);
            }

            $value = ProductAttributeValue::query()
                ->whereKey($valueId)
                ->where('product_attribute_id', $attributeId)
                ->where('is_active', true)
                ->first();

            if (! $value) {
                throw ValidationException::withMessages(['variants.attribute_value_ids' => 'Variant values must be active and belong to the selected attribute.']);
            }

            $pairs[$attributeId] = $valueId;
        }

        return $pairs;
    }

    /**
     * @param array<int, array<string, mixed>> $specifications
     */
    private function syncSpecifications(Product $product, Category $category, array $specifications): void
    {
        if ($specifications === []) {
            return;
        }

        $set = $category->productAttributeSet;
        if (! $set) {
            throw ValidationException::withMessages(['specifications' => 'This category does not support structured specifications.']);
        }

        $allowed = $set->attributes()
            ->where('product_attributes.type', ProductAttributeType::Specification->value)
            ->get()
            ->keyBy('id');

        foreach ($specifications as $attributeId => $payload) {
            $attribute = $allowed->get((int) $attributeId);

            if (! $attribute instanceof ProductAttribute) {
                throw ValidationException::withMessages(['specifications' => 'Specifications must belong to the category attribute set.']);
            }

            $specification = $product->specifications()->create([
                'product_attribute_id' => $attribute->id,
                ...$this->typedSpecificationValues($attribute, $payload),
            ]);

            $valueIds = $this->specificationValueIds($attribute, $payload);
            if ($valueIds !== []) {
                $specification->values()->sync($valueIds);
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{text_value: ?string, number_value: mixed, boolean_value: ?bool}
     */
    private function typedSpecificationValues(ProductAttribute $attribute, array $payload): array
    {
        return match ($attribute->input_type) {
            ProductAttributeInputType::Text => ['text_value' => filled($payload['text_value'] ?? null) ? (string) $payload['text_value'] : null, 'number_value' => null, 'boolean_value' => null],
            ProductAttributeInputType::Number => ['text_value' => null, 'number_value' => is_numeric($payload['number_value'] ?? null) ? $payload['number_value'] : throw ValidationException::withMessages(['specifications' => 'Numeric specifications must provide a number value.']), 'boolean_value' => null],
            ProductAttributeInputType::Boolean => ['text_value' => null, 'number_value' => null, 'boolean_value' => filter_var($payload['boolean_value'] ?? false, FILTER_VALIDATE_BOOLEAN)],
            ProductAttributeInputType::Select, ProductAttributeInputType::Multiselect => ['text_value' => null, 'number_value' => null, 'boolean_value' => null],
        };
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, int>
     */
    private function specificationValueIds(ProductAttribute $attribute, array $payload): array
    {
        if (! $attribute->input_type->usesPredefinedValues()) {
            return [];
        }

        $ids = $attribute->input_type === ProductAttributeInputType::Select
            ? collect([$payload['product_attribute_value_id'] ?? null])
            : collect($payload['product_attribute_value_ids'] ?? []);

        $ids = $ids->filter()->map(fn ($id): int => (int) $id)->unique()->values();

        if ($attribute->input_type === ProductAttributeInputType::Select && $ids->count() !== 1) {
            throw ValidationException::withMessages(['specifications' => 'Select specifications require exactly one predefined value.']);
        }

        if ($attribute->input_type === ProductAttributeInputType::Multiselect && $ids->isEmpty()) {
            throw ValidationException::withMessages(['specifications' => 'Multiselect specifications require at least one predefined value.']);
        }

        $validCount = ProductAttributeValue::query()
            ->where('product_attribute_id', $attribute->id)
            ->where('is_active', true)
            ->whereIn('id', $ids)
            ->count();

        if ($validCount !== $ids->count()) {
            throw ValidationException::withMessages(['specifications' => 'Specification values must be active and belong to the selected attribute.']);
        }

        return $ids->all();
    }

    private function money(mixed $value, string $field): int
    {
        if (! is_numeric($value) || (int) $value < 0) {
            throw ValidationException::withMessages([$field => 'Price must be a non-negative integer minor-unit amount.']);
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
}
