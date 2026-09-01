<?php

namespace App\Actions\CustomerAddresses;

use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreCustomerAddress
{
    /**
     * @param array<string, mixed> $data
     */
    public function handle(User $user, array $data): CustomerAddress
    {
        $max = (int) config('marketplace.checkout.max_customer_addresses', 10);

        if ($user->addresses()->count() >= $max) {
            throw ValidationException::withMessages([
                'label' => "You can save up to {$max} addresses.",
            ]);
        }

        return DB::transaction(function () use ($user, $data): CustomerAddress {
            $isFirst = ! $user->addresses()->lockForUpdate()->exists();
            $makeDefault = $isFirst || (bool) ($data['is_default'] ?? false);

            if ($makeDefault) {
                $user->addresses()->update(['is_default' => false]);
            }

            $address = new CustomerAddress($data);
            $address->country_code = strtoupper((string) $data['country_code']);
            $address->is_default = $makeDefault;
            $address->user()->associate($user);
            $address->save();

            return $address;
        });
    }
}
