<?php

namespace Database\Factories;

use App\Enums\PaymentAttemptStatus;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAttempt>
 */
class PaymentAttemptFactory extends Factory
{
    protected $model = PaymentAttempt::class;

    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'attempt_number' => 1,
            'status' => PaymentAttemptStatus::Pending,
            'provider_reference' => null,
            'amount' => fake()->numberBetween(1000, 100000),
            'currency' => 'USD',
            'failure_code' => null,
            'failure_message' => null,
            'metadata' => null,
            'started_at' => now(),
            'completed_at' => null,
        ];
    }
}
