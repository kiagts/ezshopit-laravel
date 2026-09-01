<?php

namespace Database\Factories;

use App\Enums\InventoryReservationStatus;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryReservation>
 */
class InventoryReservationFactory extends Factory
{
    protected $model = InventoryReservation::class;

    public function definition(): array
    {
        return [
            'inventory_id' => Inventory::factory(),
            'order_item_id' => OrderItem::factory(),
            'quantity' => fake()->numberBetween(1, 3),
            'status' => InventoryReservationStatus::Active,
        ];
    }
}
