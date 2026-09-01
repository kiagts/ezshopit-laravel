<?php

namespace App\Support;

use App\Models\CustomerAddress;

class AddressFormatter
{
    /**
     * @return array<int, string>
     */
    public function lines(CustomerAddress $address): array
    {
        return array_values(array_filter([
            $address->address_line_1,
            $address->address_line_2,
            trim(implode(', ', array_filter([$address->city, $address->state_region, $address->postal_code]))),
            Countries::name($address->country_code),
        ], fn (?string $line): bool => filled($line)));
    }
}
