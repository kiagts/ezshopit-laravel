<?php

namespace App\Actions\ProductAttributes;

use App\Models\ProductAttribute;
use Illuminate\Http\Request;

class UpdateProductAttribute
{
    public function handle(ProductAttribute $attribute, Request $request): ProductAttribute
    {
        $data = $request->safe()->except(['is_filterable', 'is_required', 'is_active']);
        $data['code'] = filled($data['code'] ?? null) ? $data['code'] : $attribute->code;
        $data['is_filterable'] = $request->boolean('is_filterable');
        $data['is_required'] = $request->boolean('is_required');
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        $attribute->update($data);

        return $attribute;
    }
}
