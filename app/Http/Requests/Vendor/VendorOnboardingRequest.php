<?php

namespace App\Http\Requests\Vendor;

use App\Enums\VendorBusinessType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class VendorOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isVendor() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $slugSource = $this->input('slug') ?: $this->input('display_name') ?: $this->input('business_name');

        if (filled($slugSource)) {
            $this->merge([
                'slug' => Str::slug($slugSource),
            ]);
        }
    }

    public function rules(): array
    {
        $required = $this->routeIs('vendor.onboarding.submit') ? 'required' : 'nullable';
        $vendorId = $this->user()?->vendor?->id;

        return [
            'business_name' => [$required, 'string', 'max:255'],
            'display_name' => [$required, 'string', 'max:255'],
            'slug' => [$required, 'string', 'max:255', 'alpha_dash:ascii', Rule::unique('vendors', 'slug')->ignore($vendorId)],
            'business_type' => [$required, Rule::enum(VendorBusinessType::class)],
            'description' => [$required, 'string', 'max:2000'],
            'email' => [$required, 'email', 'max:255'],
            'phone' => [$required, 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:255'],
            'country' => [$required, 'string', 'max:120'],
            'city' => [$required, 'string', 'max:120'],
            'address_line_1' => [$required, 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:40'],
            'tax_number' => ['nullable', 'string', 'max:120'],
            'registration_number' => ['nullable', 'string', 'max:120'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }
}
