<?php

namespace App\Services\VendorFinance;

use App\Models\Currency;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

class FinanceMoney
{
    public function precision(string $code): int
    {
        $value = Currency::where('code', $code)->value('minor_unit');
        if ($value === null && $code === Money::currencyCode()) {
            $value = config('marketplace.currency.minor_unit', 2);
        }
        if ($value === null) {
            throw ValidationException::withMessages(['currency' => 'Configure this currency before displaying financial amounts.']);
        }

        return (int) $value;
    }

    public function parse(string $value, int $precision): int
    {
        if (! preg_match('/^\d{1,12}'.($precision ? '(?:\.\d{1,'.$precision.'})?' : '').'$/D', $value)) {
            throw ValidationException::withMessages(['amount' => 'Enter a valid positive amount in the selected currency.']);
        }
        [$whole,$fraction] = array_pad(explode('.', $value, 2), 2, '');

        return (int) $whole * (10 ** $precision) + (int) str_pad($fraction, $precision, '0');
    }
}
