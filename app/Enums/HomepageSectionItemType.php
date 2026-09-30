<?php

namespace App\Enums;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;

enum HomepageSectionItemType: string
{
    case Product = 'product';
    case Category = 'category';
    case Vendor = 'vendor';
    case Brand = 'brand';

    public function modelClass(): string
    {
        return match ($this) {
            self::Product => Product::class,
            self::Category => Category::class,
            self::Vendor => Vendor::class,
            self::Brand => Brand::class,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Product => 'Product',
            self::Category => 'Category',
            self::Vendor => 'Store',
            self::Brand => 'Brand',
        };
    }
}
