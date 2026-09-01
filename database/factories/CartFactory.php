<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    protected $model = Cart::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'token' => (string) Str::ulid(),
        ];
    }

    public function customer(): static
    {
        return $this->state(fn (): array => [
            'user_id' => User::factory()->state(['role' => UserRole::Customer]),
            'token' => null,
        ]);
    }
}
