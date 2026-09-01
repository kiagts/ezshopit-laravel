<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAddress>
 */
class CustomerAddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->create(['role' => UserRole::Customer])->id,
            'label' => fake()->randomElement(['Home', 'Office', null]),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => '+1 555 0100',
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => null,
            'city' => fake()->city(),
            'state_region' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'country_code' => 'US',
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }
}
