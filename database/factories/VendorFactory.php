<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\VendorBusinessType;
use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    public function definition(): array
    {
        $displayName = fake()->unique()->company();

        return [
            'user_id' => User::factory()->state(['role' => UserRole::Vendor]),
            'status' => VendorStatus::Draft,
            'business_name' => $displayName.' Ltd',
            'display_name' => $displayName,
            'slug' => Str::slug($displayName),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'business_type' => fake()->randomElement(VendorBusinessType::cases()),
            'description' => fake()->paragraph(),
            'country' => fake()->country(),
            'city' => fake()->city(),
            'address_line_1' => fake()->streetAddress(),
            'postal_code' => fake()->postcode(),
            'website' => fake()->url(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => VendorStatus::Approved,
            'submitted_at' => now()->subDays(3),
            'approved_at' => now()->subDay(),
        ]);
    }
}
