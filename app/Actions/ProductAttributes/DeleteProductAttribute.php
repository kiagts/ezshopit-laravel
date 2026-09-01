<?php

namespace App\Actions\ProductAttributes;

use App\Models\ProductAttribute;
use Illuminate\Validation\ValidationException;

class DeleteProductAttribute
{
    public function handle(ProductAttribute $attribute): void
    {
        if ($attribute->attributeSets()->exists()) {
            throw ValidationException::withMessages([
                'attribute' => 'This attribute cannot be deleted because it is used by one or more attribute sets.',
            ]);
        }

        if ($attribute->values()->exists()) {
            throw ValidationException::withMessages([
                'attribute' => 'This attribute cannot be deleted while it contains attribute values.',
            ]);
        }

        $attribute->delete();
    }
}
