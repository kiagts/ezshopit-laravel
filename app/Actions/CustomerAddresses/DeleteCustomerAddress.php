<?php

namespace App\Actions\CustomerAddresses;

use App\Models\CustomerAddress;
use Illuminate\Support\Facades\DB;

class DeleteCustomerAddress
{
    public function handle(CustomerAddress $address): void
    {
        DB::transaction(function () use ($address): void {
            $user = $address->user;
            $wasDefault = $address->is_default;

            $address->delete();

            if (! $wasDefault) {
                return;
            }

            $replacement = $user->addresses()
                ->lockForUpdate()
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->first();

            if ($replacement) {
                $replacement->forceFill(['is_default' => true])->save();
            }
        });
    }
}
