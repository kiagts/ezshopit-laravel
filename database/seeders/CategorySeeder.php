<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $tree = [
            'Electronics' => [
                'Mobile Phones' => ['Smartphones', 'Feature Phones', 'Mobile Accessories'],
                'Computers & Laptops' => ['Laptops', 'Desktop Computers', 'Computer Accessories'],
                'Audio' => ['Headphones', 'Earbuds', 'Speakers'],
                'Smart Wearables' => [],
            ],
            'Fashion' => [
                'Men' => ['Clothing', 'Shoes', 'Accessories'],
                'Women' => ['Clothing', 'Shoes', 'Handbags'],
                'Kids' => [],
            ],
            'Home & Living' => [
                'Kitchen' => [],
                'Furniture' => [],
                'Home Decor' => [],
                'Bedding' => [],
            ],
            'Beauty & Personal Care' => [
                'Skin Care' => [],
                'Hair Care' => [],
            ],
            'Sports & Fitness' => [
                'Exercise Equipment' => [],
                'Team Sports' => [],
            ],
            'Automotive' => [
                'Car Accessories' => [],
            ],
            'Gaming' => [
                'Consoles' => [],
                'Games' => [],
            ],
            'Books & Stationery' => [
                'Books' => [],
            ],
            'Baby & Kids' => [
                'Baby Gear' => [],
            ],
            'Groceries' => [
                'Pantry Staples' => [],
            ],
            'Pet Supplies' => [],
            'Tools & Home Improvement' => [],
        ];

        $sort = 10;

        foreach ($tree as $rootName => $children) {
            $root = $this->category($rootName, null, $sort);
            $childSort = 10;

            foreach ($children as $childName => $grandChildren) {
                $child = $this->category($childName, $root, $childSort);
                $grandChildSort = 10;

                foreach ($grandChildren as $grandChildName) {
                    $this->category($grandChildName, $child, $grandChildSort);
                    $grandChildSort += 10;
                }

                $childSort += 10;
            }

            $sort += 10;
        }
    }

    private function category(string $name, ?Category $parent, int $sortOrder): Category
    {
        $slug = $this->slug($name, $parent);

        return Category::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'parent_id' => $parent?->id,
                'name' => $name,
                'description' => "Marketplace category for {$name}.",
                'is_active' => true,
                'sort_order' => $sortOrder,
                'seo_title' => $name,
                'seo_description' => "Browse {$name} in the marketplace.",
            ],
        );
    }

    private function slug(string $name, ?Category $parent): string
    {
        $base = Str::slug($name);

        if ($parent === null) {
            return $base;
        }

        return $parent->slug.'-'.$base;
    }
}
