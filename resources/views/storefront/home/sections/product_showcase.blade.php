<x-storefront.category-product-section
    :products="$section['products']"
    :product="$section['featuredProduct']"
    :wishlisted-product-ids="$wishlistedProductIds"
    :title="$section['title'] ?: 'Featured Products'"
    :description="$section['subtitle']"
    :view-all-url="$section['settings']['view_all_url'] ?? null"
/>
