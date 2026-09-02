<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    public static function parseDecimal(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $input = trim((string) $value);
        $minorUnit = self::minorUnit();

        if (! preg_match('/^\d+(?:\.\d{1,'.$minorUnit.'})?$/', $input)) {
            throw new InvalidArgumentException('Enter a valid non-negative amount.');
        }

        [$whole, $fraction] = array_pad(explode('.', $input, 2), 2, '');
        $fraction = str_pad($fraction, $minorUnit, '0');

        return ((int) $whole * (10 ** $minorUnit)) + (int) $fraction;
    }

    public static function format(?int $amount, ?string $currency = null): string
    {
        if ($amount === null) {
            return '-';
        }

        $minorUnit = self::minorUnit();
        $divisor = 10 ** $minorUnit;
        $number = number_format($amount / $divisor, $minorUnit);

        return ($currency ?: self::currencyCode()).' '.$number;
    }

    public static function input(?int $amount): string
    {
        if ($amount === null) {
            return '';
        }

        $minorUnit = self::minorUnit();

        return number_format($amount / (10 ** $minorUnit), $minorUnit, '.', '');
    }

    public static function currencyCode(): string
    {
        return (string) config('marketplace.currency.code', 'USD');
    }

    private static function minorUnit(): int
    {
        return max(0, (int) config('marketplace.currency.minor_unit', 2));
    }
}
