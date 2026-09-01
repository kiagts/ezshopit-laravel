<?php

namespace App\Http\Requests\Admin\Categories;

use App\Models\Category;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (filled($this->input('slug'))) {
            $this->merge(['slug' => Str::slug($this->input('slug'))]);
        }
    }

    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'product_attribute_set_id' => [
                'nullable',
                'integer',
                Rule::exists('product_attribute_sets', 'id')->where(fn (Builder $query): Builder => $query
                    ->whereNull('deleted_at')
                    ->where(function (Builder $query) use ($category): void {
                        $query->where('is_active', true)
                            ->when($category instanceof Category && $category->product_attribute_set_id, fn (Builder $query): Builder => $query->orWhere('id', $category->product_attribute_set_id));
                    })),
            ],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique('categories', 'slug')->ignore($category)],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'icon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:1024'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $category = $this->route('category');
                $parentId = $this->integer('parent_id') ?: null;

                if (! $category instanceof Category || $parentId === null) {
                    return;
                }

                if ($parentId === $category->id) {
                    $validator->errors()->add('parent_id', 'A category cannot be its own parent.');

                    return;
                }

                if ($category->descendantIds()->contains($parentId)) {
                    $validator->errors()->add('parent_id', 'A category cannot be moved under one of its descendants.');
                }
            },
        ];
    }
}
