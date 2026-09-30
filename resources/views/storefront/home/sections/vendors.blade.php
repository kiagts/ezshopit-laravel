<x-storefront.featured-stores
    :vendors="$section['vendors']"
    :title="$section['title'] ?: 'Featured Stores'"
    :description="$section['subtitle'] ?: 'Shop from our trusted and verified sellers'"
    :view-all-url="$section['settings']['view_all_url'] ?? null"
/>
