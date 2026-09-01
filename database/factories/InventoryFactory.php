<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition(): array
    {
        $onHand = fake()->numberBetween(0, 100);

        return [
            'product_variant_id' => fn (): int => ProductVariant::factory()->createQuietly()->id,
            'on_hand' => $onHand,
            'reserved' => fake()->numberBetween(0, $onHand),
            'low_stock_threshold' => fake()->numberBetween(0, 10),
        ];
    }
}
