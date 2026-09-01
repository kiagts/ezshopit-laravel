<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'method' => PaymentMethod::CashOnDelivery,
            'provider' => PaymentMethod::CashOnDelivery->value,
            'status' => PaymentStatus::Pending,
            'currency' => 'USD',
            'amount' => fake()->numberBetween(1000, 100000),
            'provider_reference' => null,
            'paid_at' => null,
            'failed_at' => null,
            'cancelled_at' => null,
            'expires_at' => null,
            'requires_review_at' => null,
            'review_reason' => null,
            'review_resolved_at' => null,
        ];
    }
}
