<?php

namespace Database\Factories;

use App\Enums\VendorOrderStatus;
use App\Models\Order;
use App\Models\ShippingMethod;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\VendorOrder>
 */
class VendorOrderFactory extends Factory
{
    public function definition(): array
    {
        $itemsSubtotal = fake()->numberBetween(5000, 30000);
        $shippingTotal = fake()->numberBetween(0, 1500);

        return [
            'order_id' => Order::factory(),
            'vendor_id' => Vendor::factory()->approved(),
            'shipping_method_id' => ShippingMethod::factory(),
            'vendor_order_number' => 'ORD-'.now()->format('Ymd').'-'.fake()->unique()->bothify('??????').'-01',
            'status' => VendorOrderStatus::Pending,
            'items_subtotal' => $itemsSubtotal,
            'shipping_total' => $shippingTotal,
            'grand_total' => $itemsSubtotal + $shippingTotal,
            'shipping_method_name' => 'Standard Delivery',
            'shipping_method_code' => 'standard',
            'shipping_method_description' => 'Factory shipping method snapshot.',
            'shipping_min_delivery_days' => 3,
            'shipping_max_delivery_days' => 5,
        ];
    }
}
