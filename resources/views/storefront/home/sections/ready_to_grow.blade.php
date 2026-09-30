<x-storefront.ready-to-grow
    :title="$section['title'] ?: 'Ready to grow your business?'"
    :description="$section['subtitle'] ?: 'Join a marketplace built for trusted sellers. Reach more customers, manage your store, and grow your brand with '.config('marketplace.name').'.'"
    :cta-label="$section['settings']['cta_label'] ?? 'Become a Seller'"
    :cta-url="$section['settings']['cta_url'] ?? route('vendor.apply')"
    :background-image="\App\Support\HomepageImage::url($section['settings']['background_image'] ?? 'demo/banner/ready-to-grow.jpg')"
/>
