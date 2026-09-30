@php
    $settings = $section['settings'];
    $slides = collect($section['slides'])
        ->groupBy(fn (array $slide): int => (int) data_get($slide, 'settings.group', 0))
        ->map(fn ($group) => $group->map(fn (array $slide): array => [
            'title' => $slide['title'],
            'subtitle' => $slide['body'],
            'image' => $slide['image'],
            'url' => $slide['cta_url'] ?: '#',
        ])->values()->all())
        ->all();
@endphp

<x-storefront.hero-section
    :small-banner-sliders="$slides"
    :eyebrow="$settings['eyebrow'] ?? 'Trusted Marketplace'"
    :title="$section['title']"
    :description="$section['subtitle']"
    :primary-cta-label="$settings['primary_cta_label'] ?? 'Start Shopping'"
    :primary-cta-url="$settings['primary_cta_url'] ?? route('storefront.search')"
    :secondary-cta-label="$settings['secondary_cta_label'] ?? 'Become a Seller'"
    :secondary-cta-url="$settings['secondary_cta_url'] ?? route('vendor.apply')"
    :benefits="$settings['benefits'] ?? ['Verified Sellers', 'Secure Checkout', 'Curated Products']"
/>
