<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            'Apple',
            'Samsung',
            'Sony',
            'Xiaomi',
            'Dell',
            'HP',
            'Lenovo',
            'ASUS',
            'Acer',
            'Logitech',
            'JBL',
            'Anker',
            'Canon',
            'Nikon',
            'Nike',
            'Adidas',
            'Puma',
            "Levi's",
            'Skechers',
            'New Balance',
            'Philips',
            'Bosch',
            'Panasonic',
            'Haier',
            'LG',
        ];

        $inactive = ['Acer', 'Nikon', 'Haier'];
        $sortOrder = 10;

        foreach ($brands as $name) {
            Brand::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => "{$name} is a marketplace brand prepared for future product assignment and catalog filtering.",
                    'logo_path' => null,
                    'is_active' => ! in_array($name, $inactive, true),
                    'sort_order' => $sortOrder,
                    'seo_title' => $name,
                    'seo_description' => "Browse {$name} products in the marketplace.",
                ],
            );

            $sortOrder += 10;
        }
    }
}
