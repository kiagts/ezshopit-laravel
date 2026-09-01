<?php

namespace App\Http\Requests\Vendor\Products;

use App\Http\Requests\Vendor\Products\StoreProductRequest;
use App\Enums\ProductType;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends StoreProductRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['type'] = ['required', Rule::enum(ProductType::class)];
        $rules['slug'] = ['nullable', 'string', 'max:255', 'alpha_dash:ascii'];

        return $rules;
    }
}
