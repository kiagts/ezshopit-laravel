<?php

namespace Database\Factories;

use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    protected $model = Refund::class;

    public function definition(): array
    {
        $payment = Payment::factory()->create();

        return [
            'refund_number' => 'REF-'.now()->format('Ymd').'-'.fake()->unique()->bothify('????'),
            'order_id' => $payment->order_id,
            'payment_id' => $payment->id,
            'status' => RefundStatus::Pending,
            'amount' => fake()->numberBetween(500, (int) $payment->amount),
            'currency' => $payment->currency,
            'reason' => 'Customer service adjustment',
            'notes' => null,
            'provider' => $payment->provider,
            'provider_reference' => null,
            'requested_by' => User::factory(),
            'requested_at' => now(),
            'processed_at' => null,
            'failed_at' => null,
            'cancelled_at' => null,
            'failure_code' => null,
            'failure_message' => null,
        ];
    }
}
