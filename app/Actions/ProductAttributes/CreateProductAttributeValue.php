<?php

namespace App\Actions\ProductAttributes;

use App\Actions\Concerns\GeneratesUniqueCodes;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CreateProductAttributeValue
{
    use GeneratesUniqueCodes;

    public function handle(ProductAttribute $attribute, Request $request): ProductAttributeValue
    {
        $data = $request->safe()->except(['hex_color', 'is_active']);
        $data['product_attribute_id'] = $attribute->id;
        $data['code'] = filled($data['code'] ?? null)
            ? $data['code']
            : $this->uniqueCodeFor(
                ProductAttributeValue::class,
                $data['value'],
                separator: '-',
                scope: fn (Builder $query): Builder => $query->where('product_attribute_id', $attribute->id),
            );
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['metadata'] = filled($request->input('hex_color')) ? ['hex' => $request->input('hex_color')] : null;

        return ProductAttributeValue::query()->create($data);
    }
}
