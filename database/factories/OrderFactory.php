<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $itemsSubtotal = fake()->numberBetween(5000, 50000);
        $shippingTotal = fake()->numberBetween(0, 2500);

        return [
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'user_id' => User::factory()->customer(),
            'customer_address_id' => CustomerAddress::factory(),
            'idempotency_key' => (string) Str::uuid(),
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'currency' => Money::currencyCode(),
            'items_subtotal' => $itemsSubtotal,
            'discount_total' => 0,
            'shipping_total' => $shippingTotal,
            'grand_total' => $itemsSubtotal + $shippingTotal,
            'shipping_first_name' => fake()->firstName(),
            'shipping_last_name' => fake()->lastName(),
            'shipping_phone' => fake()->phoneNumber(),
            'shipping_address_line_1' => fake()->streetAddress(),
            'shipping_address_line_2' => null,
            'shipping_city' => fake()->city(),
            'shipping_state_region' => fake()->state(),
            'shipping_postal_code' => fake()->postcode(),
            'shipping_country_code' => 'US',
            'placed_at' => now(),
            'cancellation_source' => null,
            'cancellation_reason' => null,
        ];
    }
}
