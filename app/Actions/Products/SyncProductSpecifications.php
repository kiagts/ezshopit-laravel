<?php

namespace App\Actions\Products;

use App\Enums\ProductAttributeInputType;
use App\Enums\ProductAttributeType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use Illuminate\Validation\ValidationException;

class SyncProductSpecifications
{
    /**
     * @param array<int|string, mixed> $specifications
     */
    public function handle(Product $product, Category $category, array $specifications): void
    {
        $set = $category->productAttributeSet;

        if (! $set) {
            if ($this->hasFilledSpecification($specifications)) {
                throw ValidationException::withMessages(['specifications' => 'This category does not support structured specifications.']);
            }

            $product->specifications()->delete();

            return;
        }

        $allowed = $set->attributes()
            ->where('product_attributes.type', ProductAttributeType::Specification->value)
            ->with('activeValues')
            ->get()
            ->keyBy('id');

        $seen = [];

        foreach ($specifications as $attributeId => $payload) {
            $attribute = $allowed->get((int) $attributeId);
            $payload = is_array($payload) ? $payload : ['value' => $payload];

            if (! $attribute instanceof ProductAttribute) {
                throw ValidationException::withMessages(['specifications' => 'Specifications must belong to the selected category.']);
            }

            if (! $this->payloadIsFilled($attribute, $payload)) {
                continue;
            }

            $specification = $product->specifications()->updateOrCreate(
                ['product_attribute_id' => $attribute->id],
                $this->typedValues($attribute, $payload),
            );

            $valueIds = $this->valueIds($attribute, $payload);
            $specification->values()->sync($valueIds);
            $seen[] = $attribute->id;
        }

        $product->specifications()
            ->whereNotIn('product_attribute_id', $seen)
            ->get()
            ->each(function ($specification): void {
                $specification->values()->detach();
                $specification->delete();
            });
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{text_value: ?string, number_value: mixed, boolean_value: ?bool}
     */
    private function typedValues(ProductAttribute $attribute, array $payload): array
    {
        return match ($attribute->input_type) {
            ProductAttributeInputType::Text => ['text_value' => trim((string) ($payload['text_value'] ?? $payload['value'] ?? '')), 'number_value' => null, 'boolean_value' => null],
            ProductAttributeInputType::Number => ['text_value' => null, 'number_value' => is_numeric($payload['number_value'] ?? $payload['value'] ?? null) ? ($payload['number_value'] ?? $payload['value']) : throw ValidationException::withMessages(['specifications' => "{$attribute->name} must be a number."]), 'boolean_value' => null],
            ProductAttributeInputType::Boolean => ['text_value' => null, 'number_value' => null, 'boolean_value' => filter_var($payload['boolean_value'] ?? $payload['value'] ?? false, FILTER_VALIDATE_BOOLEAN)],
            ProductAttributeInputType::Select, ProductAttributeInputType::Multiselect => ['text_value' => null, 'number_value' => null, 'boolean_value' => null],
        };
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, int>
     */
    private function valueIds(ProductAttribute $attribute, array $payload): array
    {
        if (! $attribute->usesPredefinedValues()) {
            return [];
        }

        $ids = $attribute->input_type === ProductAttributeInputType::Select
            ? collect([$payload['product_attribute_value_id'] ?? $payload['value'] ?? null])
            : collect($payload['product_attribute_value_ids'] ?? $payload['value'] ?? []);

        $ids = $ids->filter(fn ($id): bool => filled($id))->map(fn ($id): int => (int) $id)->unique()->values();

        if ($attribute->input_type === ProductAttributeInputType::Select && $ids->count() !== 1) {
            throw ValidationException::withMessages(['specifications' => "{$attribute->name} requires one value."]);
        }

        if ($attribute->input_type === ProductAttributeInputType::Multiselect && $ids->isEmpty()) {
            throw ValidationException::withMessages(['specifications' => "{$attribute->name} requires at least one value."]);
        }

        $validCount = ProductAttributeValue::query()
            ->where('product_attribute_id', $attribute->id)
            ->where('is_active', true)
            ->whereIn('id', $ids)
            ->count();

        if ($validCount !== $ids->count()) {
            throw ValidationException::withMessages(['specifications' => "{$attribute->name} contains an unavailable value."]);
        }

        return $ids->all();
    }

    /**
     * @param array<int|string, mixed> $specifications
     */
    private function hasFilledSpecification(array $specifications): bool
    {
        foreach ($specifications as $payload) {
            if (is_array($payload) && collect($payload)->flatten()->filter(fn ($value): bool => filled($value))->isNotEmpty()) {
                return true;
            }

            if (! is_array($payload) && filled($payload)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function payloadIsFilled(ProductAttribute $attribute, array $payload): bool
    {
        return match ($attribute->input_type) {
            ProductAttributeInputType::Text => filled($payload['text_value'] ?? $payload['value'] ?? null),
            ProductAttributeInputType::Number => filled($payload['number_value'] ?? $payload['value'] ?? null),
            ProductAttributeInputType::Boolean => array_key_exists('boolean_value', $payload) || array_key_exists('value', $payload),
            ProductAttributeInputType::Select => filled($payload['product_attribute_value_id'] ?? $payload['value'] ?? null),
            ProductAttributeInputType::Multiselect => collect($payload['product_attribute_value_ids'] ?? $payload['value'] ?? [])->filter(fn ($value): bool => filled($value))->isNotEmpty(),
        };
    }
}
