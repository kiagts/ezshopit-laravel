@php
    $banners = collect($section['contentItems'])
        ->map(fn (array $item): array => [
            'title' => $item['title'],
            'image' => $item['image'],
            'mobile_image' => $item['mobile_image'],
            'image_alt' => $item['image_alt'],
            'url' => $item['cta_url'],
        ])
        ->filter(fn (array $item): bool => filled($item['image']))
        ->values()
        ->all();
@endphp

<x-storefront.promotional-banner-slider :banners="$banners" />
