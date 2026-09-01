<?php

namespace Database\Factories;

use App\Enums\ShippingMethodType;
use App\Models\ShippingMethod;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingMethod>
 */
class ShippingMethodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory()->approved(),
            'name' => 'Standard Delivery',
            'code' => fake()->unique()->slug(2),
            'description' => 'Tracked flat-rate delivery.',
            'type' => ShippingMethodType::FlatRate,
            'flat_rate' => 500,
            'free_shipping_threshold' => null,
            'min_delivery_days' => 3,
            'max_delivery_days' => 5,
            'ships_to_all_supported_countries' => true,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function restricted(array $countries = ['PK']): static
    {
        return $this->state(fn (): array => ['ships_to_all_supported_countries' => false])
            ->afterCreating(function (ShippingMethod $method) use ($countries): void {
                foreach ($countries as $country) {
                    $method->countries()->create(['country_code' => strtoupper($country)]);
                }
            });
    }
}
