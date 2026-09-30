<x-storefront.featured-brands
    :brands="$section['brands']"
    :title="$section['title'] ?: 'Top Brands You Love'"
    :description="$section['subtitle'] ?: 'Discover original products from trusted brands, all in one place.'"
    :view-all-url="$section['settings']['view_all_url'] ?? null"
/>
