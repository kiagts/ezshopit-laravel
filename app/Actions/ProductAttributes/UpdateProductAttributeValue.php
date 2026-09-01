<?php

namespace App\Actions\ProductAttributes;

use App\Models\ProductAttributeValue;
use Illuminate\Http\Request;

class UpdateProductAttributeValue
{
    public function handle(ProductAttributeValue $value, Request $request): ProductAttributeValue
    {
        $data = $request->safe()->except(['hex_color', 'is_active']);
        $data['code'] = filled($data['code'] ?? null) ? $data['code'] : $value->code;
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['metadata'] = filled($request->input('hex_color')) ? ['hex' => $request->input('hex_color')] : null;

        $value->update($data);

        return $value;
    }
}
