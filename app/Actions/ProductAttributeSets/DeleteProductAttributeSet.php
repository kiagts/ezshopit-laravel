<?php

namespace App\Actions\ProductAttributeSets;

use App\Models\ProductAttributeSet;
use Illuminate\Validation\ValidationException;

class DeleteProductAttributeSet
{
    public function handle(ProductAttributeSet $attributeSet): void
    {
        if ($attributeSet->categories()->exists()) {
            throw ValidationException::withMessages([
                'attribute_set' => 'This attribute set cannot be deleted because it is assigned to one or more categories.',
            ]);
        }

        $attributeSet->delete();
    }
}
