<x-storefront.featured-products
    :products="$section['products']"
    :wishlisted-product-ids="$wishlistedProductIds"
    :title="$section['title'] ?: 'Products'"
    :description="$section['subtitle']"
    :view-all-url="$section['settings']['view_all_url'] ?? null"
/>
