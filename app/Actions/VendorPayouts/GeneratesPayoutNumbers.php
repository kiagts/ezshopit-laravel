<?php

namespace App\Actions\VendorPayouts;

use App\Models\VendorPayout;
use Illuminate\Support\Str;

trait GeneratesPayoutNumbers
{
    private function payoutNumber(): string
    {
        do {
            $number = 'PAY-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (VendorPayout::query()->where('payout_number', $number)->exists());

        return $number;
    }
}
