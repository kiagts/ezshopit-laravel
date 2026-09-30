<?php

namespace App\Enums;

enum HomepageSectionType: string
{
    case Hero = 'hero';
    case Categories = 'categories';
    case PromotionGrid = 'promotion_grid';
    case Products = 'products';
    case ProductShowcase = 'product_showcase';
    case Brands = 'brands';
    case Vendors = 'vendors';
    case BannerSlider = 'banner_slider';
    case Benefits = 'benefits';
    case ReadyToGrow = 'ready_to_grow';

    public function label(): string
    {
        return match ($this) {
            self::Hero => 'Hero',
            self::Categories => 'Categories',
            self::PromotionGrid => 'Promotion Grid',
            self::Products => 'Products',
            self::ProductShowcase => 'Product Showcase',
            self::Brands => 'Brands',
            self::Vendors => 'Stores',
            self::BannerSlider => 'Banner Slider',
            self::Benefits => 'Benefits',
            self::ReadyToGrow => 'Ready to Grow',
        };
    }

    public function supportsMarketplaceItems(): bool
    {
        return in_array($this, [
            self::Categories,
            self::Products,
            self::ProductShowcase,
            self::Brands,
            self::Vendors,
        ], true);
    }

    public function supportsContentItems(): bool
    {
        return in_array($this, [
            self::Hero,
            self::PromotionGrid,
            self::BannerSlider,
            self::Benefits,
        ], true);
    }
}
