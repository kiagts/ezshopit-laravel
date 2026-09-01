<?php

namespace App\Http\Requests\Admin\ProductAttributes;

use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateProductAttributeValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (filled($this->input('code'))) {
            $this->merge(['code' => Str::slug($this->input('code'))]);
        }

        if (filled($this->input('hex_color'))) {
            $this->merge(['hex_color' => strtoupper($this->input('hex_color'))]);
        }
    }

    public function rules(): array
    {
        /** @var ProductAttribute $attribute */
        $attribute = $this->route('attribute');
        /** @var ProductAttributeValue $value */
        $value = $this->route('value');

        return [
            'value' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_attribute_values', 'value')
                    ->where('product_attribute_id', $attribute->id)
                    ->ignore($value),
            ],
            'code' => [
                'nullable',
                'string',
                'max:255',
                'alpha_dash:ascii',
                Rule::unique('product_attribute_values', 'code')
                    ->where('product_attribute_id', $attribute->id)
                    ->ignore($value),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['nullable', 'boolean'],
            'hex_color' => ['nullable', 'regex:/^#[0-9A-F]{6}$/'],
        ];
    }
}
