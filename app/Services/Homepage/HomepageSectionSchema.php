<?php

namespace App\Services\Homepage;

use App\Enums\HomepageSectionItemType;
use App\Enums\HomepageSectionType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HomepageSectionSchema
{
    /**
     * @return array<string, mixed>
     */
    public function validateSettings(HomepageSectionType $type, array $settings): array
    {
        if (isset($settings['benefits']) && is_array($settings['benefits'])) {
            $settings['benefits'] = array_values(array_filter($settings['benefits'], fn ($value) => $value !== null && $value !== ''));
        }
        $settings = collect($settings)
            ->reject(fn ($value): bool => $value === null || $value === '' || $value === [])
            ->all();

        $allowed = $this->allowedSettings($type);
        $unknown = array_diff(array_keys($settings), $allowed);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'settings' => 'Unknown setting: '.implode(', ', $unknown).'.',
            ]);
        }

        $rules = match ($type) {
            HomepageSectionType::Hero => [
                'eyebrow' => ['nullable', 'string', 'max:80'],
                'primary_cta_label' => ['nullable', 'string', 'max:40'],
                'primary_cta_url' => ['nullable', 'string', 'max:255', $this->safeUrlRule()],
                'secondary_cta_label' => ['nullable', 'string', 'max:40'],
                'secondary_cta_url' => ['nullable', 'string', 'max:255', $this->safeUrlRule()],
                'benefits' => ['nullable', 'array', 'max:4'],
                'benefits.*' => ['string', 'max:40'],
            ],
            HomepageSectionType::Products, HomepageSectionType::ProductShowcase, HomepageSectionType::ReadyToGrow => [
                'source' => ['required', 'string', Rule::in(['manual', 'featured', 'latest', 'on_sale', 'category'])],
                'limit' => ['nullable', 'integer', 'min:1', 'max:24'],
                'category_id' => ['required_if:source,category', 'nullable', 'integer', 'exists:categories,id'],
                'view_all_url' => ['nullable', 'string', 'max:255', $this->safeUrlRule()],
                'cta_label' => ['nullable', 'string', 'max:50'],
                'cta_url' => ['nullable', 'string', 'max:255', $this->safeUrlRule()],
                'background_image' => ['nullable', 'string', 'max:255'],
                'image_alt' => ['nullable', 'string', 'max:120'],
            ],
            HomepageSectionType::Categories => [
                'source' => ['required', 'string', Rule::in(['manual', 'latest'])],
                'limit' => ['nullable', 'integer', 'min:1', 'max:24'],
                'view_all_url' => ['nullable', 'string', 'max:255', $this->safeUrlRule()],
            ],
            HomepageSectionType::Brands, HomepageSectionType::Vendors => [
                'source' => ['required', 'string', Rule::in(['manual', 'latest'])],
                'limit' => ['nullable', 'integer', 'min:1', 'max:24'],
                'view_all_url' => ['nullable', 'string', 'max:255', $this->safeUrlRule()],
            ],
            HomepageSectionType::PromotionGrid, HomepageSectionType::BannerSlider, HomepageSectionType::Benefits => [],
        };

        validator($settings, $rules)->validate();

        return collect($settings)
            ->only($allowed)
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->all();
    }

    /**
     * @return array<int, HomepageSectionItemType>
     */
    public function allowedItemTypes(HomepageSectionType $type): array
    {
        return match ($type) {
            HomepageSectionType::Products,
            HomepageSectionType::ProductShowcase,
            HomepageSectionType::ReadyToGrow => [HomepageSectionItemType::Product],
            HomepageSectionType::Categories => [HomepageSectionItemType::Category],
            HomepageSectionType::Brands => [HomepageSectionItemType::Brand],
            HomepageSectionType::Vendors => [HomepageSectionItemType::Vendor],
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    public function allowedIconKeys(): array
    {
        return ['shield', 'star', 'tag', 'truck', 'sparkles', 'store'];
    }

    public function safeUrlRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (blank($value)) {
                return;
            }

            $url = trim((string) $value);

            if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
                return;
            }

            if (filter_var($url, FILTER_VALIDATE_URL)) {
                $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

                if (in_array($scheme, ['http', 'https'], true)) {
                    return;
                }
            }

            $fail('The '.$attribute.' must be a relative URL or a safe HTTP/HTTPS URL.');
        };
    }

    /**
     * @return array<int, string>
     */
    private function allowedSettings(HomepageSectionType $type): array
    {
        return match ($type) {
            HomepageSectionType::Hero => [
                'eyebrow',
                'primary_cta_label',
                'primary_cta_url',
                'secondary_cta_label',
                'secondary_cta_url',
                'benefits',
            ],
            HomepageSectionType::Products, HomepageSectionType::ProductShowcase, HomepageSectionType::ReadyToGrow => [
                'source',
                'limit',
                'category_id',
                'view_all_url',
                'cta_label',
                'cta_url',
                'background_image',
                'image_alt',
            ],
            HomepageSectionType::Categories, HomepageSectionType::Brands, HomepageSectionType::Vendors => [
                'source',
                'limit',
                'view_all_url',
            ],
            HomepageSectionType::PromotionGrid, HomepageSectionType::BannerSlider, HomepageSectionType::Benefits => [],
        };
    }
}
