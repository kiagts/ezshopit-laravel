<?php

namespace Database\Seeders;

use App\Enums\ShippingMethodType;
use App\Models\ShippingMethod;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class ShippingMethodSeeder extends Seeder
{
    public function run(): void
    {
        Vendor::query()
            ->approved()
            ->get()
            ->each(function (Vendor $vendor): void {
                $standard = ShippingMethod::query()->updateOrCreate(
                    ['vendor_id' => $vendor->id, 'code' => 'standard'],
                    [
                        'name' => 'Standard Delivery',
                        'description' => 'Tracked seller delivery.',
                        'type' => ShippingMethodType::FlatRate,
                        'flat_rate' => 500,
                        'free_shipping_threshold' => 10000,
                        'min_delivery_days' => 3,
                        'max_delivery_days' => 5,
                        'ships_to_all_supported_countries' => true,
                        'is_active' => true,
                        'sort_order' => 10,
                    ],
                );
                $standard->countries()->delete();

                $express = ShippingMethod::query()->updateOrCreate(
                    ['vendor_id' => $vendor->id, 'code' => 'express'],
                    [
                        'name' => 'Express Delivery',
                        'description' => 'Faster tracked seller delivery.',
                        'type' => ShippingMethodType::FlatRate,
                        'flat_rate' => 1500,
                        'free_shipping_threshold' => null,
                        'min_delivery_days' => 1,
                        'max_delivery_days' => 2,
                        'ships_to_all_supported_countries' => false,
                        'is_active' => true,
                        'sort_order' => 20,
                    ],
                );

                $express->countries()->delete();
                $express->countries()->create(['country_code' => 'PK']);
            });
    }
}
