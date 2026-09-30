<?php

namespace Database\Factories;

use App\Enums\PromotionDiscountType;
use App\Enums\PromotionFundingSource;
use App\Models\Promotion;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'code' => Str::upper(fake()->unique()->bothify('SAVE##??')),
            'funding_source' => PromotionFundingSource::Marketplace,
            'vendor_id' => null,
            'discount_type' => PromotionDiscountType::Percentage,
            'discount_value' => 1000,
            'minimum_spend' => null,
            'maximum_discount' => null,
            'usage_limit' => null,
            'per_customer_limit' => null,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'is_active' => true,
            'created_by' => User::factory()->admin(),
            'updated_by' => null,
        ];
    }

    public function vendorFunded(?Vendor $vendor = null): static
    {
        return $this->state(fn (): array => [
            'funding_source' => PromotionFundingSource::Vendor,
            'vendor_id' => $vendor?->id ?? Vendor::factory()->approved(),
        ]);
    }

    public function fixed(int $amount = 1000): static
    {
        return $this->state(fn (): array => [
            'discount_type' => PromotionDiscountType::FixedAmount,
            'discount_value' => $amount,
        ]);
    }
}
