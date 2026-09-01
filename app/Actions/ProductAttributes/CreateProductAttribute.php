<?php

namespace App\Actions\ProductAttributes;

use App\Actions\Concerns\GeneratesUniqueCodes;
use App\Models\ProductAttribute;
use Illuminate\Http\Request;

class CreateProductAttribute
{
    use GeneratesUniqueCodes;

    public function handle(Request $request): ProductAttribute
    {
        $data = $request->safe()->except(['is_filterable', 'is_required', 'is_active']);
        $data['code'] = filled($data['code'] ?? null)
            ? $data['code']
            : $this->uniqueCodeFor(ProductAttribute::class, $data['name']);
        $data['is_filterable'] = $request->boolean('is_filterable');
        $data['is_required'] = $request->boolean('is_required');
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return ProductAttribute::query()->create($data);
    }
}
