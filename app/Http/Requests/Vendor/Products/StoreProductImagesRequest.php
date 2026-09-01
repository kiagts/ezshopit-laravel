<?php

namespace App\Http\Requests\Vendor\Products;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProductImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isVendor() ?? false;
    }

    public function rules(): array
    {
        $maxSize = (int) config('marketplace.product_media.max_file_size_kb', 5120);

        return [
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', "max:{$maxSize}"],
            'product_variant_id' => ['nullable', 'integer'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $product = $this->route('product');

                if (! $product instanceof Product || blank($this->input('product_variant_id'))) {
                    return;
                }

                if (! $product->variants()->whereKey((int) $this->input('product_variant_id'))->where('is_active', true)->exists()) {
                    $validator->errors()->add('product_variant_id', 'The selected variant does not belong to this product.');
                }
            },
        ];
    }
}
