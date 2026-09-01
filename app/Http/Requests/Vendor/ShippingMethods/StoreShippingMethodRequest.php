<?php

namespace App\Http\Requests\Vendor\ShippingMethods;

use App\Enums\ShippingMethodType;
use App\Support\Countries;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreShippingMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isVendor() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => str($this->input('code') ?: $this->input('name'))->slug('_')->toString(),
            'type' => ShippingMethodType::FlatRate->value,
            'flat_rate' => $this->parseMoney($this->input('flat_rate')),
            'free_shipping_threshold' => $this->parseMoney($this->input('free_shipping_threshold')),
            'ships_to_all_supported_countries' => $this->boolean('ships_to_all_supported_countries'),
            'is_active' => $this->boolean('is_active'),
            'sort_order' => $this->input('sort_order') === null || $this->input('sort_order') === '' ? 0 : $this->input('sort_order'),
            'countries' => collect($this->input('countries', []))
                ->map(fn ($code): string => strtoupper((string) $code))
                ->unique()
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:255',
                'alpha_dash:ascii',
                Rule::unique('shipping_methods', 'code')
                    ->where('vendor_id', $this->user()?->vendor?->id)
                    ->ignore($this->route('shippingMethod')),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['required', Rule::enum(ShippingMethodType::class)],
            'flat_rate' => ['required', 'integer', 'min:0'],
            'free_shipping_threshold' => ['nullable', 'integer', 'min:0'],
            'min_delivery_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'max_delivery_days' => ['nullable', 'integer', 'min:0', 'max:365', 'gte:min_delivery_days'],
            'ships_to_all_supported_countries' => ['boolean'],
            'countries' => ['array'],
            'countries.*' => ['string', 'size:2', Rule::in(Countries::codes())],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:-100000', 'max:100000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->boolean('ships_to_all_supported_countries') && count($this->input('countries', [])) === 0) {
                    $validator->errors()->add('countries', 'Choose at least one supported country or enable all supported countries.');
                }
            },
        ];
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
