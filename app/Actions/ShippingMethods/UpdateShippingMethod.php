<?php

namespace App\Actions\ShippingMethods;

use App\Models\ShippingMethod;
use Illuminate\Support\Facades\DB;

class UpdateShippingMethod
{
    /**
     * @param array<string, mixed> $data
     */
    public function handle(ShippingMethod $method, array $data): ShippingMethod
    {
        return DB::transaction(function () use ($method, $data): ShippingMethod {
            $method->fill(collect($data)->except('countries')->all());
            $method->save();

            $method->countries()->delete();

            if (! $method->ships_to_all_supported_countries) {
                foreach ($data['countries'] ?? [] as $countryCode) {
                    $method->countries()->create(['country_code' => strtoupper((string) $countryCode)]);
                }
            }

            return $method;
        });
    }
}
