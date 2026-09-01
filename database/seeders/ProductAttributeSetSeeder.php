<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeSet;
use Illuminate\Database\Seeder;

class ProductAttributeSetSeeder extends Seeder
{
    public function run(): void
    {
        $sets = [
            'smartphone' => [
                'name' => 'Smartphone',
                'description' => 'Attribute configuration for modern smartphones.',
                'sort_order' => 10,
                'attributes' => [
                    ['color', true, 10],
                    ['storage', true, 20],
                    ['ram', true, 30],
                    ['processor', true, 40],
                    ['screen_size', true, 50],
                    ['battery_capacity', false, 60],
                    ['operating_system', true, 70],
                    ['connectivity', false, 80],
                    ['warranty', false, 90],
                    ['weight', false, 100],
                ],
                'categories' => ['electronics-mobile-phones-smartphones'],
            ],
            'laptop' => [
                'name' => 'Laptop',
                'description' => 'Attribute configuration for laptops and portable computers.',
                'sort_order' => 20,
                'attributes' => [
                    ['color', false, 10],
                    ['ram', true, 20],
                    ['storage', true, 30],
                    ['processor', true, 40],
                    ['screen_size', true, 50],
                    ['operating_system', true, 60],
                    ['connectivity', false, 70],
                    ['warranty', false, 80],
                    ['weight', false, 90],
                ],
                'categories' => ['electronics-computers-laptops-laptops'],
            ],
            'desktop_computer' => [
                'name' => 'Desktop Computer',
                'description' => 'Attribute configuration for desktop computers.',
                'sort_order' => 30,
                'attributes' => [
                    ['ram', true, 10],
                    ['storage', true, 20],
                    ['processor', true, 30],
                    ['operating_system', false, 40],
                    ['connectivity', false, 50],
                    ['warranty', false, 60],
                ],
                'categories' => ['electronics-computers-laptops-desktop-computers'],
            ],
            'fashion_clothing' => [
                'name' => 'Fashion Clothing',
                'description' => 'Attribute configuration for apparel categories.',
                'sort_order' => 40,
                'attributes' => [
                    ['color', true, 10],
                    ['size', true, 20],
                    ['material', false, 30],
                    ['warranty', false, 40],
                ],
                'categories' => ['fashion-men-clothing', 'fashion-women-clothing'],
            ],
            'footwear' => [
                'name' => 'Footwear',
                'description' => 'Attribute configuration for shoes and footwear.',
                'sort_order' => 50,
                'attributes' => [
                    ['color', true, 10],
                    ['shoe_size', true, 20],
                    ['material', false, 30],
                ],
                'categories' => ['fashion-men-shoes', 'fashion-women-shoes'],
            ],
            'home_appliance' => [
                'name' => 'Home Appliance',
                'description' => 'Attribute configuration for appliances and home equipment.',
                'sort_order' => 60,
                'attributes' => [
                    ['color', false, 10],
                    ['material', false, 20],
                    ['warranty', false, 30],
                    ['weight', false, 40],
                ],
                'categories' => ['home-living-kitchen'],
            ],
            'furniture' => [
                'name' => 'Furniture',
                'description' => 'Attribute configuration for furniture products.',
                'sort_order' => 70,
                'attributes' => [
                    ['color', false, 10],
                    ['material', true, 20],
                    ['warranty', false, 30],
                    ['weight', false, 40],
                ],
                'categories' => ['home-living-furniture'],
            ],
            'gaming_accessory' => [
                'name' => 'Gaming Accessory',
                'description' => 'Attribute configuration for gaming and computer accessories.',
                'sort_order' => 80,
                'attributes' => [
                    ['color', false, 10],
                    ['connectivity', false, 20],
                    ['warranty', false, 30],
                ],
                'categories' => ['electronics-computers-laptops-computer-accessories'],
            ],
            'beauty_product' => [
                'name' => 'Beauty Product',
                'description' => 'Attribute configuration for beauty and personal care products.',
                'sort_order' => 90,
                'attributes' => [
                    ['pack_size', false, 10],
                    ['material', false, 20],
                    ['warranty', false, 30],
                ],
                'categories' => ['beauty-personal-care-skin-care', 'beauty-personal-care-hair-care'],
            ],
        ];

        foreach ($sets as $code => $data) {
            $attributeSet = ProductAttributeSet::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'is_active' => true,
                    'sort_order' => $data['sort_order'],
                ],
            );

            $attributeSet->attributes()->sync($this->assignments($data['attributes']));

            Category::query()
                ->whereIn('slug', $data['categories'])
                ->update(['product_attribute_set_id' => $attributeSet->id]);
        }
    }

    /**
     * @param array<int, array{0: string, 1: bool, 2: int}> $rows
     * @return array<int, array{is_required: bool, sort_order: int}>
     */
    private function assignments(array $rows): array
    {
        $attributes = ProductAttribute::query()
            ->whereIn('code', collect($rows)->pluck(0))
            ->pluck('id', 'code');

        return collect($rows)
            ->filter(fn (array $row): bool => $attributes->has($row[0]))
            ->mapWithKeys(fn (array $row): array => [
                $attributes[$row[0]] => [
                    'is_required' => $row[1],
                    'sort_order' => $row[2],
                ],
            ])
            ->all();
    }
}
