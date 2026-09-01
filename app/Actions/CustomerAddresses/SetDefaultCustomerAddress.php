<?php

namespace App\Actions\CustomerAddresses;

use App\Models\CustomerAddress;
use Illuminate\Support\Facades\DB;

class SetDefaultCustomerAddress
{
    public function handle(CustomerAddress $address): CustomerAddress
    {
        return DB::transaction(function () use ($address): CustomerAddress {
            $address->user->addresses()->update(['is_default' => false]);
            $address->forceFill(['is_default' => true])->save();

            return $address;
        });
    }
}
