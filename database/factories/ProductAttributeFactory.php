<?php

namespace Database\Factories;

use App\Enums\ProductAttributeInputType;
use App\Enums\ProductAttributeType;
use App\Models\ProductAttribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductAttribute>
 */
class ProductAttributeFactory extends Factory
{
    protected $model = ProductAttribute::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Color',
            'Size',
            'Storage',
            'Material',
            'Processor',
            'Screen Size',
            'Battery Capacity',
            'Warranty',
        ]).fake()->unique()->numberBetween(100, 999);

        return [
            'name' => $name,
            'code' => Str::slug($name, '_'),
            'type' => ProductAttributeType::Specification,
            'input_type' => ProductAttributeInputType::Text,
            'is_filterable' => false,
            'is_required' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function variant(): static
    {
        return $this->state(fn (): array => [
            'type' => ProductAttributeType::Variant,
            'input_type' => ProductAttributeInputType::Select,
        ]);
    }

    public function specification(): static
    {
        return $this->state(fn (): array => [
            'type' => ProductAttributeType::Specification,
            'input_type' => ProductAttributeInputType::Text,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
