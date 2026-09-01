<?php

namespace Database\Factories;

use App\Models\CommissionRule;
use App\Models\VendorOrder;
use App\Models\VendorOrderCommission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorOrderCommission>
 */
class VendorOrderCommissionFactory extends Factory
{
    protected $model = VendorOrderCommission::class;

    public function definition(): array
    {
        $vendorOrder = VendorOrder::factory()->create();

        return [
            'vendor_order_id' => $vendorOrder->id,
            'commission_rule_id' => CommissionRule::factory(),
            'rate_basis_points' => 1000,
            'include_shipping' => false,
            'original_items_amount' => $vendorOrder->items_subtotal,
            'original_shipping_amount' => $vendorOrder->shipping_total,
            'original_commissionable_amount' => $vendorOrder->items_subtotal,
            'original_commission_amount' => intdiv(($vendorOrder->items_subtotal * 1000) + 5000, 10000),
            'currency' => $vendorOrder->order->currency,
            'recognized_at' => now(),
        ];
    }
}
