<?php

namespace Database\Factories;

use App\Enums\CommissionRuleScope;
use App\Models\CommissionRule;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionRule>
 */
class CommissionRuleFactory extends Factory
{
    protected $model = CommissionRule::class;

    public function definition(): array
    {
        return [
            'scope' => CommissionRuleScope::Global,
            'vendor_id' => null,
            'rate_basis_points' => 1000,
            'include_shipping' => false,
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    public function vendor(?Vendor $vendor = null): static
    {
        return $this->state(fn (): array => [
            'scope' => CommissionRuleScope::Vendor,
            'vendor_id' => $vendor?->id ?? Vendor::factory()->approved(),
        ]);
    }
}
