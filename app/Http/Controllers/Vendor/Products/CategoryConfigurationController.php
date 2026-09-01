<?php

namespace App\Http\Controllers\Vendor\Products;

use App\Enums\ProductAttributeType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryConfigurationController extends Controller
{
    public function __invoke(Category $category): JsonResponse
    {
        abort_unless($category->isPubliclyUsable(), 404);

        $category->load('productAttributeSet.attributes.activeValues');
        $set = $category->productAttributeSet;

        return response()->json([
            'attribute_set' => $set ? [
                'id' => $set->id,
                'name' => $set->name,
                'code' => $set->code,
            ] : null,
            'variant_attributes' => $this->attributes($set?->attributes->where('type', ProductAttributeType::Variant) ?? collect()),
            'specification_attributes' => $this->attributes($set?->attributes->where('type', ProductAttributeType::Specification) ?? collect()),
        ]);
    }

    private function attributes($attributes): array
    {
        return $attributes
            ->sortBy(fn ($attribute): int => (int) $attribute->pivot->sort_order)
            ->map(fn ($attribute): array => [
                'id' => $attribute->id,
                'name' => $attribute->name,
                'code' => $attribute->code,
                'type' => $attribute->type->value,
                'input_type' => $attribute->input_type->value,
                'is_required' => (bool) $attribute->pivot->is_required,
                'sort_order' => (int) $attribute->pivot->sort_order,
                'values' => $attribute->activeValues->map(fn ($value): array => [
                    'id' => $value->id,
                    'value' => $value->value,
                    'code' => $value->code,
                    'metadata' => $value->metadata,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
