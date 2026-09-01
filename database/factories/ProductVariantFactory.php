<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => 'SKU-'.fake()->unique()->bothify('????-####'),
            'name' => null,
            'base_price' => fake()->numberBetween(1000, 200000),
            'sale_price' => null,
            'is_active' => true,
            'is_default' => false,
            'combination_key' => 'default-'.fake()->unique()->numberBetween(1000, 9999),
            'sort_order' => 0,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (): array => [
            'is_default' => true,
            'combination_key' => 'default',
        ]);
    }
}
