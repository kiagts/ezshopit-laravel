<?php

namespace App\Actions\ShippingMethods;

use App\Models\ShippingMethod;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

class StoreShippingMethod
{
    /**
     * @param array<string, mixed> $data
     */
    public function handle(Vendor $vendor, array $data): ShippingMethod
    {
        return DB::transaction(function () use ($vendor, $data): ShippingMethod {
            $method = new ShippingMethod($this->methodData($data));
            $method->vendor()->associate($vendor);
            $method->save();

            $this->syncCountries($method, $data);

            return $method;
        });
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function methodData(array $data): array
    {
        return collect($data)->except('countries')->all();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function syncCountries(ShippingMethod $method, array $data): void
    {
        $method->countries()->delete();

        if ($method->ships_to_all_supported_countries) {
            return;
        }

        foreach ($data['countries'] ?? [] as $countryCode) {
            $method->countries()->create(['country_code' => strtoupper((string) $countryCode)]);
        }
    }
}
