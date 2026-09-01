<?php

namespace Database\Seeders;

use App\Enums\ProductAttributeInputType;
use App\Enums\ProductAttributeType;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AttributeSeeder extends Seeder
{
    public function run(): void
    {
        $attributes = [
            ['Color', 'color', ProductAttributeType::Variant, ProductAttributeInputType::Select, true, true, true, 10],
            ['Size', 'size', ProductAttributeType::Variant, ProductAttributeInputType::Select, true, false, true, 20],
            ['Storage', 'storage', ProductAttributeType::Variant, ProductAttributeInputType::Select, true, false, true, 30],
            ['RAM', 'ram', ProductAttributeType::Variant, ProductAttributeInputType::Select, true, false, true, 40],
            ['Shoe Size', 'shoe_size', ProductAttributeType::Variant, ProductAttributeInputType::Select, true, false, true, 50],
            ['Pack Size', 'pack_size', ProductAttributeType::Variant, ProductAttributeInputType::Select, true, false, true, 60],
            ['Processor', 'processor', ProductAttributeType::Specification, ProductAttributeInputType::Text, false, false, true, 70],
            ['Screen Size', 'screen_size', ProductAttributeType::Specification, ProductAttributeInputType::Text, true, false, true, 80],
            ['Battery Capacity', 'battery_capacity', ProductAttributeType::Specification, ProductAttributeInputType::Number, true, false, true, 90],
            ['Material', 'material', ProductAttributeType::Specification, ProductAttributeInputType::Select, true, false, true, 100],
            ['Operating System', 'operating_system', ProductAttributeType::Specification, ProductAttributeInputType::Select, true, false, true, 110],
            ['Connectivity', 'connectivity', ProductAttributeType::Specification, ProductAttributeInputType::Multiselect, true, false, true, 120],
            ['Warranty', 'warranty', ProductAttributeType::Specification, ProductAttributeInputType::Text, false, false, true, 130],
            ['Model Year', 'model_year', ProductAttributeType::Specification, ProductAttributeInputType::Number, true, false, true, 140],
            ['Water Resistant', 'water_resistant', ProductAttributeType::Specification, ProductAttributeInputType::Boolean, true, false, true, 150],
            ['Weight', 'weight', ProductAttributeType::Specification, ProductAttributeInputType::Number, false, false, true, 160],
        ];

        foreach ($attributes as [$name, $code, $type, $inputType, $filterable, $required, $active, $sortOrder]) {
            ProductAttribute::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'input_type' => $inputType,
                    'is_filterable' => $filterable,
                    'is_required' => $required,
                    'is_active' => $active,
                    'sort_order' => $sortOrder,
                ],
            );
        }

        $this->seedValues('color', [
            ['Black', '#000000'],
            ['White', '#FFFFFF'],
            ['Blue', '#2563EB'],
            ['Red', '#DC2626'],
            ['Green', '#16A34A'],
            ['Silver', '#C0C0C0'],
            ['Gold', '#D4AF37'],
            ['Gray', '#6B7280'],
            ['Pink', '#EC4899'],
        ]);
        $this->seedValues('size', ['XS', 'S', 'M', 'L', 'XL', 'XXL']);
        $this->seedValues('storage', ['64GB', '128GB', '256GB', '512GB', '1TB']);
        $this->seedValues('ram', ['4GB', '6GB', '8GB', '12GB', '16GB', '32GB']);
        $this->seedValues('shoe_size', ['EU 38', 'EU 39', 'EU 40', 'EU 41', 'EU 42', 'EU 43', 'EU 44', 'EU 45']);
        $this->seedValues('pack_size', ['Single', 'Pack of 2', 'Pack of 3', 'Pack of 5', 'Pack of 10']);
        $this->seedValues('material', ['Cotton', 'Leather', 'Plastic', 'Metal', 'Wood']);
        $this->seedValues('operating_system', ['iOS', 'Android', 'Windows', 'macOS']);
        $this->seedValues('connectivity', ['Wi-Fi', 'Bluetooth', 'NFC', 'USB-C', '5G']);
    }

    /**
     * @param array<int, string|array{0: string, 1: string}> $values
     */
    private function seedValues(string $attributeCode, array $values): void
    {
        $attribute = ProductAttribute::query()->where('code', $attributeCode)->firstOrFail();

        foreach (array_values($values) as $index => $item) {
            [$value, $hex] = is_array($item) ? $item : [$item, null];

            ProductAttributeValue::query()->updateOrCreate(
                [
                    'product_attribute_id' => $attribute->id,
                    'code' => Str::slug($value),
                ],
                [
                    'value' => $value,
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                    'metadata' => $hex ? ['hex' => $hex] : null,
                ],
            );
        }
    }
}
