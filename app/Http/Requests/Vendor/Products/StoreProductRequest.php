<?php

namespace App\Http\Requests\Vendor\Products;

use App\Enums\ProductType;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isVendor() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'brand_id' => $this->input('brand_id') ?: null,
            'variants' => $this->normalizedVariants(),
        ]);
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'type' => ['required', Rule::enum(ProductType::class)],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash:ascii'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:5000'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.sku' => ['required', 'string', 'max:255'],
            'variants.*.base_price' => ['required', 'integer', 'min:0'],
            'variants.*.sale_price' => ['nullable', 'integer', 'min:0'],
            'variants.*.is_active' => ['nullable', 'boolean'],
            'variants.*.is_default' => ['nullable', 'boolean'],
            'variants.*.attribute_value_ids' => ['nullable', 'array'],
            'specifications' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizedVariants(): array
    {
        return collect($this->input('variants', []))
            ->filter(fn ($variant): bool => is_array($variant))
            ->map(function (array $variant): array {
                $variant['base_price'] = $this->parseMoney($variant['base_price'] ?? null);
                $variant['sale_price'] = $this->parseMoney($variant['sale_price'] ?? null);

                return $variant;
            })
            ->values()
            ->all();
    }

    private function parseMoney(mixed $value): mixed
    {
        try {
            return Money::parseDecimal($value);
        } catch (\InvalidArgumentException) {
            return -1;
        }
    }
}
