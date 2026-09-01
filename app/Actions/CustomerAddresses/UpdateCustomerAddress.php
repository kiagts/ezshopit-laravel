<?php

namespace App\Actions\CustomerAddresses;

use App\Models\CustomerAddress;
use Illuminate\Support\Facades\DB;

class UpdateCustomerAddress
{
    /**
     * @param array<string, mixed> $data
     */
    public function handle(CustomerAddress $address, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($address, $data): CustomerAddress {
            if ((bool) ($data['is_default'] ?? false)) {
                $address->user->addresses()
                    ->whereKeyNot($address->id)
                    ->update(['is_default' => false]);
            }

            $address->fill($data);
            $address->country_code = strtoupper((string) $data['country_code']);
            $address->is_default = (bool) ($data['is_default'] ?? false);
            $address->save();

            return $address;
        });
    }
}
