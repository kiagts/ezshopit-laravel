<?php

namespace Database\Seeders;

use App\Enums\HomepageSectionType;
use App\Models\HomepageSection;
use Illuminate\Database\Seeder;

class HomepageSeeder extends Seeder
{
    public function run(): void
    {
        $this->section('hero', HomepageSectionType::Hero, 10, [
            'name' => 'Homepage Hero',
            'title' => 'Shop smarter. Choose trusted.',
            'subtitle' => 'Discover quality products from verified sellers, all in one cleaner marketplace.',
            'settings' => [
                'eyebrow' => 'Trusted Marketplace',
                'primary_cta_label' => 'Start Shopping',
                'primary_cta_url' => '/search',
                'secondary_cta_label' => 'Become a Seller',
                'secondary_cta_url' => '/sell',
                'benefits' => ['Verified Sellers', 'Secure Checkout', 'Curated Products'],
            ],
            'content' => [
                ['Latest Smartphones', 'Discover new phones from top brands', 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=900&q=85', 0],
                ['Smart Accessories', 'Upgrade your everyday technology', 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=900&q=85', 0],
                ['Audio Essentials', 'Headphones and speakers for every moment', 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=900&q=85', 0],
                ['Refresh Your Home', 'Beautiful essentials for modern spaces', 'https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?auto=format&fit=crop&w=900&q=85', 1],
                ['Modern Living', 'Simple pieces that transform your space', 'https://images.unsplash.com/photo-1600210492486-724fe5c67fb0?auto=format&fit=crop&w=900&q=85', 1],
                ['Kitchen Essentials', 'Everything you need for your kitchen', 'https://images.unsplash.com/photo-1556911220-bff31c812dba?auto=format&fit=crop&w=900&q=85', 1],
                ['Beauty Essentials', 'Everyday care for your daily routine', 'https://images.unsplash.com/photo-1596462502278-27bfdc403348?auto=format&fit=crop&w=900&q=85', 2],
                ['Skincare Favorites', 'Care your skin will love', 'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=900&q=85', 2],
                ['Fragrance Collection', 'Find a scent made for you', 'https://images.unsplash.com/photo-1541643600914-78b084683601?auto=format&fit=crop&w=900&q=85', 2],
            ],
        ]);

        $this->section('shop-by-category', HomepageSectionType::Categories, 20, [
            'name' => 'Shop by Category',
            'title' => 'Shop by Category',
            'settings' => ['source' => 'latest', 'limit' => 8],
        ]);

        $this->section('promotion-grid', HomepageSectionType::PromotionGrid, 30, [
            'name' => 'Homepage Promotion Grid',
            'content' => [
                ['All in One Place', 'Everything you need, from trusted marketplace sellers.', 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?auto=format&fit=crop&w=1400&q=85', null, 'Explore Now', '/search', 'Shop Smarter'],
                ['Super Deals', 'Up to 50% Off', 'https://images.unsplash.com/photo-1607083206968-13611e3d76db?auto=format&fit=crop&w=900&q=85', null, 'Shop Deals', '/search', 'Limited Time'],
                ['New Arrivals', 'Check out the latest products.', 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=900&q=85', null, 'Shop Now', '/search', 'Just In'],
            ],
        ]);

        $this->section('featured-products', HomepageSectionType::Products, 40, [
            'name' => 'Featured Products',
            'title' => 'Featured Products',
            'subtitle' => 'Handpicked products from trusted sellers',
            'settings' => ['source' => 'featured', 'limit' => 8],
        ]);

        $this->section('featured-product-showcase', HomepageSectionType::ProductShowcase, 50, [
            'name' => 'Featured Product Showcase',
            'title' => 'Featured Products',
            'subtitle' => 'Handpicked products from trusted sellers',
            'settings' => ['source' => 'featured', 'limit' => 7],
        ]);

        $this->section('featured-brands', HomepageSectionType::Brands, 60, [
            'name' => 'Featured Brands',
            'title' => 'Top Brands You Love',
            'subtitle' => 'Discover original products from the world’s leading brands, all in one place.',
            'settings' => ['source' => 'latest', 'limit' => 5],
        ]);

        $this->section('featured-stores', HomepageSectionType::Vendors, 70, [
            'name' => 'Featured Stores',
            'title' => 'Featured Stores',
            'subtitle' => 'Shop from our trusted and verified sellers',
            'settings' => ['source' => 'latest', 'limit' => 4],
        ]);

        $this->section('promotional-banner-slider', HomepageSectionType::BannerSlider, 80, [
            'name' => 'Promotional Banner Slider',
            'content' => [
                ['Marketplace Sale Promotion', null, 'demo/banners/promotional-banner-1.png'],
                ['New Arrivals Promotion', null, 'demo/banners/promotional-banner-2.png'],
                ['Marketplace Shopping Promotion', null, 'demo/banners/promotional-banner-3.png'],
            ],
        ]);

        $this->section('new-arrivals', HomepageSectionType::Products, 90, [
            'name' => 'New Arrivals',
            'title' => 'New Arrivals',
            'subtitle' => 'Recently published approved products, shown without search or category-listing behavior for now.',
            'settings' => ['source' => 'latest', 'limit' => 8],
        ]);

        $this->section('trust-benefits', HomepageSectionType::Benefits, 100, [
            'name' => 'Trust Benefits',
            'content' => [
                ['Verified Sellers', 'Every seller is reviewed and approved for quality and reliability.', null, 'shield'],
                ['Curated Products', 'We handpick quality products from trusted brands and sellers.', null, 'star'],
                ['Transparent Pricing', 'No hidden fees or surprises. What you see is what you pay.', null, 'tag'],
                ['Real-time Inventory', 'Accurate stock updates so you know what is actually available.', null, 'truck'],
            ],
        ]);

        $this->section('ready-to-grow', HomepageSectionType::ReadyToGrow, 110, [
            'name' => 'Seller CTA',
            'title' => 'Ready to grow your business?',
            'subtitle' => 'Join a marketplace built for trusted sellers. Reach more customers, manage your store, and grow your brand with '.config('marketplace.name').'.',
            'settings' => [
                'source' => 'latest',
                'limit' => 4,
                'cta_label' => 'Become a Seller',
                'cta_url' => '/sell',
                'background_image' => 'demo/banner/ready-to-grow.jpg',
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function section(string $key, HomepageSectionType $type, int $sortOrder, array $data): void
    {
        $section = HomepageSection::query()->firstOrCreate(
            ['key' => $key],
            [
                'type' => $type,
                'name' => $data['name'],
                'title' => $data['title'] ?? null,
                'subtitle' => $data['subtitle'] ?? null,
                'is_enabled' => true,
                'sort_order' => $sortOrder,
                'starts_at' => null,
                'ends_at' => null,
                'settings' => $data['settings'] ?? [],
            ]
        );

        if (! $section->wasRecentlyCreated) {
            return;
        }

        foreach (($data['content'] ?? []) as $index => $item) {
            $section->contentItems()->create([
                'title' => $item[0],
                'body' => $item[1] ?? null,
                'image_path' => $item[2] ?? null,
                'icon_key' => $type === HomepageSectionType::Benefits ? ($item[3] ?? null) : null,
                'cta_label' => $item[4] ?? null,
                'cta_url' => $item[5] ?? '/search',
                'sort_order' => ($index + 1) * 10,
                'settings' => array_filter([
                    'group' => is_int($item[3] ?? null) ? $item[3] : null,
                    'eyebrow' => $item[6] ?? null,
                ], fn ($value): bool => $value !== null),
            ]);
        }
    }
}
