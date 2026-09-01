<?php

namespace App\Actions\Refunds;

use App\Models\Refund;
use Illuminate\Support\Str;

class RefundNumberGenerator
{
    public function generate(): string
    {
        do {
            $number = 'REF-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Refund::query()->where('refund_number', $number)->exists());

        return $number;
    }
}
