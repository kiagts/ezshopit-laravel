<?php

namespace App\Http\Requests\Admin\ProductAttributes;

use App\Enums\ProductAttributeInputType;
use App\Enums\ProductAttributeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (filled($this->input('code'))) {
            $this->merge(['code' => Str::slug($this->input('code'), '_')]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255', 'alpha_dash:ascii', 'unique:product_attributes,code'],
            'type' => ['required', Rule::enum(ProductAttributeType::class)],
            'input_type' => ['required', Rule::enum(ProductAttributeInputType::class)],
            'is_filterable' => ['nullable', 'boolean'],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('type') === ProductAttributeType::Variant->value
                    && $this->input('input_type') !== ProductAttributeInputType::Select->value) {
                    $validator->errors()->add('input_type', 'Variant attributes must use the select input type.');
                }
            },
        ];
    }
}
