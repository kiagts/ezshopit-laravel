<?php

namespace App\Http\Requests\Admin\ProductAttributeSets;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductAttributeSetRequest extends FormRequest
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
            'code' => ['nullable', 'string', 'max:255', 'alpha_dash:ascii', 'unique:product_attribute_sets,code'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'attributes' => ['nullable', 'array'],
            'attributes.*.product_attribute_id' => [
                'nullable',
                'integer',
                'distinct',
                Rule::exists('product_attributes', 'id')->where(fn (Builder $query): Builder => $query
                    ->where('is_active', true)
                    ->whereNull('deleted_at')),
            ],
            'attributes.*.is_required' => ['nullable', 'boolean'],
            'attributes.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $ids = collect($this->input('attributes', []))
                    ->pluck('product_attribute_id')
                    ->filter()
                    ->map(fn ($id): int => (int) $id);

                if ($ids->duplicates()->isNotEmpty()) {
                    $validator->errors()->add('attributes', 'Each attribute can only be added to a set once.');
                }
            },
        ];
    }
}
