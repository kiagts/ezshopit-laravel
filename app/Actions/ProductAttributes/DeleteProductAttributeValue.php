<?php

namespace App\Actions\ProductAttributes;

use App\Models\ProductAttributeValue;

class DeleteProductAttributeValue
{
    public function handle(ProductAttributeValue $value): void
    {
        $value->delete();
    }
}
