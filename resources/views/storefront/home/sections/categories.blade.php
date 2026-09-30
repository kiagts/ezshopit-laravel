<x-storefront.category-section
    :categories="$section['categories']"
    :title="$section['title'] ?: 'Shop by Category'"
    :view-all-url="$section['settings']['view_all_url'] ?? null"
/>
