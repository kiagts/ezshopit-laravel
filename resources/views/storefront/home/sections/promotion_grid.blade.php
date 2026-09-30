@php
    $items = collect($section['contentItems'])->values();
    $banner = fn (int $index): array => [
        'eyebrow' => data_get($items, "{$index}.settings.eyebrow"),
        'title' => data_get($items, "{$index}.title"),
        'description' => data_get($items, "{$index}.body"),
        'button_text' => data_get($items, "{$index}.cta_label"),
        'url' => data_get($items, "{$index}.cta_url", '#') ?: '#',
        'image' => data_get($items, "{$index}.image"),
    ];
@endphp

<x-storefront.promotion-grid
    :main-banner="$banner(0)"
    :top-banner="$banner(1)"
    :bottom-banner="$banner(2)"
/>
