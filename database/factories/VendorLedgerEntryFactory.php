<?php

namespace Database\Factories;

use App\Enums\VendorLedgerEntryType;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VendorLedgerEntry>
 */
class VendorLedgerEntryFactory extends Factory
{
    protected $model = VendorLedgerEntry::class;

    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory()->approved(),
            'source_key' => 'factory-ledger:'.Str::uuid(),
            'type' => VendorLedgerEntryType::Sale,
            'amount' => fake()->numberBetween(1000, 100000),
            'currency' => 'USD',
            'available_at' => now(),
            'description' => 'Factory ledger entry.',
            'metadata' => null,
        ];
    }
}
