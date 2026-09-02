<?php

namespace Database\Factories;

use App\Enums\VendorPayoutMethodType;
use App\Models\Vendor;
use App\Models\VendorPayoutMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorPayoutMethod>
 */
class VendorPayoutMethodFactory extends Factory
{
    protected $model = VendorPayoutMethod::class;

    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory()->approved(),
            'type' => VendorPayoutMethodType::BankTransfer,
            'label' => 'Demo Bank Account',
            'account_holder_name' => fake()->name(),
            'bank_name' => 'Demo Bank',
            'account_number' => 'DEMO'.fake()->numerify('########'),
            'iban' => null,
            'swift_code' => 'DEMOUS33',
            'routing_number' => fake()->numerify('#########'),
            'country_code' => 'US',
            'is_default' => true,
        ];
    }
}
