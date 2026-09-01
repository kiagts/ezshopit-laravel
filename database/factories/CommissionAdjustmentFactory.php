<?php

namespace Database\Factories;

use App\Enums\CommissionAdjustmentType;
use App\Models\CommissionAdjustment;
use App\Models\VendorOrderCommission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionAdjustment>
 */
class CommissionAdjustmentFactory extends Factory
{
    protected $model = CommissionAdjustment::class;

    public function definition(): array
    {
        return [
            'vendor_order_commission_id' => VendorOrderCommission::factory(),
            'refund_id' => null,
            'refund_allocation_id' => null,
            'type' => CommissionAdjustmentType::Refund,
            'commissionable_amount_delta' => -1000,
            'commission_amount_delta' => -100,
            'reason' => 'Refund commission adjustment.',
        ];
    }
}
