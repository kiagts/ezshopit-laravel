<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VendorOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 3);
        $unitPrice = fake()->numberBetween(1000, 20000);

        return [
            'vendor_order_id' => VendorOrder::factory(),
            'product_id' => Product::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'product_name' => fake()->words(3, true),
            'variant_description' => null,
            'sku' => 'SKU-'.fake()->unique()->bothify('????-####'),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $unitPrice * $quantity,
            'discount_total' => 0,
            'marketplace_discount_total' => 0,
            'vendor_discount_total' => 0,
            'net_line_total' => $unitPrice * $quantity,
            'attributes_snapshot' => null,
        ];
    }
}
