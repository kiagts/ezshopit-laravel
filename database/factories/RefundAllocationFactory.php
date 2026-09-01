<?php

namespace Database\Factories;

use App\Enums\RefundAllocationType;
use App\Models\Refund;
use App\Models\RefundAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RefundAllocation>
 */
class RefundAllocationFactory extends Factory
{
    protected $model = RefundAllocation::class;

    public function definition(): array
    {
        return [
            'refund_id' => Refund::factory(),
            'vendor_order_id' => null,
            'order_item_id' => null,
            'type' => RefundAllocationType::Adjustment,
            'amount' => fake()->numberBetween(100, 1000),
            'quantity' => null,
        ];
    }
}
