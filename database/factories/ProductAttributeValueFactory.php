<?php

namespace Database\Factories;

use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductAttributeValue>
 */
class ProductAttributeValueFactory extends Factory
{
    protected $model = ProductAttributeValue::class;

    public function definition(): array
    {
        $value = fake()->unique()->randomElement(['Black', 'White', 'Blue', 'Small', 'Medium', 'Large', '128GB', '256GB']).fake()->unique()->numberBetween(100, 999);

        return [
            'product_attribute_id' => ProductAttribute::factory(),
            'value' => $value,
            'code' => Str::slug($value),
            'sort_order' => 0,
            'is_active' => true,
            'metadata' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    public function color(string $hex): static
    {
        return $this->state(fn (): array => [
            'metadata' => ['hex' => strtoupper($hex)],
        ]);
    }
}
