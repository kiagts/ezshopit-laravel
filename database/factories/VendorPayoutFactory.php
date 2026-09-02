<?php

namespace Database\Factories;

use App\Enums\VendorPayoutMethodType;
use App\Enums\VendorPayoutStatus;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayout;
use App\Models\VendorPayoutMethod;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VendorPayout>
 */
class VendorPayoutFactory extends Factory
{
    protected $model = VendorPayout::class;

    public function definition(): array
    {
        $method = VendorPayoutMethod::factory()->create();

        return [
            'payout_number' => 'PAY-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'vendor_id' => $method->vendor_id,
            'status' => VendorPayoutStatus::Pending,
            'amount' => fake()->numberBetween(5000, 50000),
            'currency' => 'USD',
            'payout_method_id' => $method->id,
            'payout_method_type' => VendorPayoutMethodType::BankTransfer->value,
            'destination_snapshot' => $method->destinationSnapshot(),
            'requested_at' => now(),
            'requested_by' => Vendor::query()->find($method->vendor_id)?->user_id,
        ];
    }

    public function forVendor(Vendor $vendor): static
    {
        return $this->state(function () use ($vendor): array {
            $method = VendorPayoutMethod::factory()->create(['vendor_id' => $vendor->id]);

            return [
                'vendor_id' => $vendor->id,
                'payout_method_id' => $method->id,
                'destination_snapshot' => $method->destinationSnapshot(),
                'requested_by' => $vendor->user_id,
            ];
        });
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => VendorPayoutStatus::Approved,
            'approved_at' => now(),
            'approved_by' => User::factory()->admin(),
        ]);
    }

    public function processing(): static
    {
        return $this->approved()->state(fn (): array => [
            'status' => VendorPayoutStatus::Processing,
            'processing_at' => now(),
            'processed_by' => User::factory()->admin(),
        ]);
    }

    public function paid(): static
    {
        return $this->processing()->state(fn (): array => [
            'status' => VendorPayoutStatus::Paid,
            'paid_at' => now(),
            'paid_by' => User::factory()->admin(),
        ]);
    }
}
