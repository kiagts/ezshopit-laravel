<?php

namespace Database\Factories;

use App\Enums\InventoryMovementType;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    public function definition(): array
    {
        $inventory = Inventory::factory()->create();
        $before = fake()->numberBetween(0, 100);
        $quantity = fake()->numberBetween(1, 20);

        return [
            'inventory_id' => $inventory->id,
            'product_variant_id' => $inventory->product_variant_id,
            'type' => InventoryMovementType::Increase,
            'quantity' => $quantity,
            'before_quantity' => $before,
            'after_quantity' => $before + $quantity,
            'reason' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
