<?php

namespace App\Actions\ProductAttributeSets;

use App\Actions\Concerns\GeneratesUniqueCodes;
use App\Models\ProductAttributeSet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreateProductAttributeSet
{
    use GeneratesUniqueCodes;

    public function handle(Request $request): ProductAttributeSet
    {
        return DB::transaction(function () use ($request): ProductAttributeSet {
            $data = $request->safe()->except(['attributes', 'is_active']);
            $data['code'] = filled($data['code'] ?? null)
                ? $data['code']
                : $this->uniqueCodeFor(ProductAttributeSet::class, $data['name']);
            $data['is_active'] = $request->boolean('is_active');
            $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

            $attributeSet = ProductAttributeSet::query()->create($data);
            $attributeSet->attributes()->sync($this->assignments($request));

            return $attributeSet;
        });
    }

    /**
     * @return array<int, array{is_required: bool, sort_order: int}>
     */
    private function assignments(Request $request): array
    {
        return collect($request->validated('attributes', []))
            ->filter(fn (array $row): bool => filled($row['product_attribute_id'] ?? null))
            ->mapWithKeys(fn (array $row): array => [
                (int) $row['product_attribute_id'] => [
                    'is_required' => filter_var($row['is_required'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                ],
            ])
            ->all();
    }
}
