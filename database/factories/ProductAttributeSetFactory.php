<?php

namespace Database\Factories;

use App\Models\ProductAttributeSet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductAttributeSet>
 */
class ProductAttributeSetFactory extends Factory
{
    protected $model = ProductAttributeSet::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Smartphone',
            'Laptop',
            'Fashion Clothing',
            'Footwear',
            'Home Appliance',
            'Furniture',
        ]).fake()->unique()->numberBetween(100, 999);

        return [
            'name' => $name,
            'code' => Str::slug($name, '_'),
            'description' => 'Reusable product attribute configuration.',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
