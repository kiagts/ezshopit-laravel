<?php

namespace Database\Seeders;

use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Database\Seeder;

class CustomerAddressSeeder extends Seeder
{
    public function run(): void
    {
        $customer = User::query()->where('email', 'customer@example.com')->first();

        if (! $customer) {
            return;
        }

        $addresses = [
            [
                'label' => 'Home',
                'first_name' => 'John',
                'last_name' => 'Customer',
                'phone' => '+92 300 0000001',
                'address_line_1' => '123 Demo Street',
                'address_line_2' => 'Apartment 4B',
                'city' => 'Lahore',
                'state_region' => 'Punjab',
                'postal_code' => '54000',
                'country_code' => 'PK',
                'is_default' => true,
            ],
            [
                'label' => 'Office',
                'first_name' => 'John',
                'last_name' => 'Customer',
                'phone' => '+92 300 0000002',
                'address_line_1' => '45 Example Business Center',
                'address_line_2' => null,
                'city' => 'Karachi',
                'state_region' => 'Sindh',
                'postal_code' => '74000',
                'country_code' => 'PK',
                'is_default' => false,
            ],
        ];

        foreach ($addresses as $address) {
            CustomerAddress::query()->updateOrCreate(
                [
                    'user_id' => $customer->id,
                    'label' => $address['label'],
                ],
                $address,
            );
        }

        CustomerAddress::query()
            ->where('user_id', $customer->id)
            ->where('label', '!=', 'Home')
            ->update(['is_default' => false]);
    }
}
