<?php

namespace App\Services;

use App\Enums\ProductAttributeType;
use App\Enums\ProductType;
use App\Enums\VendorStatus;
use App\Models\Product;

class ProductSubmissionValidator
{
    /**
     * @return array<int, string>
     */
    public function errors(Product $product): array
    {
        $product->loadMissing([
            'vendor',
            'category.parent',
            'category.productAttributeSet.attributes',
            'variants.attributeValues',
            'specifications.values',
            'specifications.attribute',
            'primaryImage',
        ]);

        $errors = [];

        if ($product->vendor->status !== VendorStatus::Approved) {
            $errors[] = 'Vendor must be approved.';
        }

        if (! $product->category->isPubliclyUsable()) {
            $errors[] = 'Product category must be an active leaf category with active ancestors.';
        }

        if (blank($product->name) || blank($product->description)) {
            $errors[] = 'Product name and description are required.';
        }

        if (! $product->primaryImage) {
            $errors[] = 'Choose a primary product image before submitting for review.';
        }

        $activeVariantCount = 0;

        foreach ($product->variants as $variant) {
            if ($variant->is_active) {
                $activeVariantCount++;
            }

            if (blank($variant->sku) || $variant->base_price < 0 || ($variant->sale_price !== null && $variant->sale_price > $variant->base_price)) {
                $errors[] = 'Every variant must have a valid SKU and price.';
                break;
            }
        }

        if ($activeVariantCount === 0) {
            $errors[] = 'At least one active variant is required.';
        }

        if ($product->type === ProductType::Simple) {
            if ($product->variants->count() !== 1 || ! $product->variants->first()?->is_default) {
                $errors[] = 'Simple products must have exactly one default variant.';
            }

            if ($product->variants->flatMap->attributeValues->isNotEmpty()) {
                $errors[] = 'Simple product variants cannot have attribute values.';
            }
        }

        if ($product->type === ProductType::Variant) {
            if ($product->variants->isEmpty()) {
                $errors[] = 'Variant products must have at least one variant.';
            }

            $variantAttributes = $product->category->productAttributeSet?->attributes
                ->filter(fn ($attribute): bool => $attribute->type === ProductAttributeType::Variant)
                ->keyBy('id') ?? collect();
            $requiredVariantIds = $variantAttributes
                ->filter(fn ($attribute): bool => (bool) $attribute->pivot->is_required)
                ->keys()
                ->map(fn (int $id): int => $id)
                ->all();
            $combinationKeys = [];

            foreach ($product->variants as $variant) {
                $variantAttributeIds = $variant->attributeValues->pluck('product_attribute_id')->map(fn (int $id): int => $id)->all();
                $combinationKeys[] = $variant->combination_key;

                foreach ($variant->attributeValues as $value) {
                    if (! $variantAttributes->has($value->product_attribute_id) || ! $value->is_active) {
                        $errors[] = 'Variant values must belong to the category attribute set and remain active for submission.';
                        break 2;
                    }
                }

                foreach ($requiredVariantIds as $requiredAttributeId) {
                    if (! in_array($requiredAttributeId, $variantAttributeIds, true)) {
                        $attributeName = $variantAttributes->get($requiredAttributeId)?->name ?? 'required variant option';
                        $errors[] = 'Every variant must include required variant attributes.';
                        $errors[] = "Every variant must include {$attributeName}.";
                        break 2;
                    }
            }
            }

            if (count($combinationKeys) !== count(array_unique($combinationKeys))) {
                $errors[] = 'Variant combinations must be unique.';
            }
        }

        $specificationAttributes = $product->category->productAttributeSet?->attributes
            ->filter(fn ($attribute): bool => $attribute->type === ProductAttributeType::Specification)
            ->keyBy('id') ?? collect();
        $requiredSpecificationIds = $specificationAttributes
            ->filter(fn ($attribute): bool => (bool) $attribute->pivot->is_required)
            ->keys()
            ->map(fn (int $id): int => $id)
            ->all();

        $presentSpecificationIds = $product->specifications->filter(function ($specification): bool {
            return filled($specification->text_value)
                || $specification->number_value !== null
                || $specification->boolean_value !== null
                || $specification->values->isNotEmpty();
        })->pluck('product_attribute_id')->map(fn (int $id): int => $id)->all();

        foreach ($product->specifications as $specification) {
            if (! $specificationAttributes->has($specification->product_attribute_id)) {
                $errors[] = 'Specifications must belong to the category attribute set.';
                break;
            }

            foreach ($specification->values as $value) {
                if ($value->product_attribute_id !== $specification->product_attribute_id || ! $value->is_active) {
                    $errors[] = 'Specification values must belong to the specification attribute and remain active for submission.';
                    break 2;
                }
            }
        }

        foreach ($requiredSpecificationIds as $requiredSpecificationId) {
            if (! in_array($requiredSpecificationId, $presentSpecificationIds, true)) {
                $attributeName = $specificationAttributes->get($requiredSpecificationId)?->name ?? 'required specification';
                $errors[] = 'Required specifications must be populated.';
                $errors[] = "{$attributeName} is required.";
                break;
            }
        }

        return array_values(array_unique($errors));
    }

    public function ready(Product $product): bool
    {
        return $this->errors($product) === [];
    }
}
