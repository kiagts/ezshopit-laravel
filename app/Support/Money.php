<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    /** Format a snapshotted currency without floats or storefront configuration. */
    public static function formatWithPrecision(int $amount, string $currency, int $minorUnit): string
    {
        $metadata = Currency::from($currency, $minorUnit);
        $absolute = abs($amount);
        $scale = 10 ** $metadata->minorUnit;
        $whole = (string) intdiv($absolute, $scale);
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole);
        $fraction = $minorUnit ? '.'.str_pad((string) ($absolute % $scale), $minorUnit, '0', STR_PAD_LEFT) : '';
        return $currency.' '.($amount < 0 ? '-' : '').$whole.$fraction;
    }
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

        return self::prefix($currency).$number;
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
        return Currency::from((string) config('marketplace.currency.code', 'USD'), (int) config('marketplace.currency.minor_unit', 2))->code;
    }

    public static function currencySymbol(): string
    {
        return (string) config('marketplace.currency.symbol', self::currencyCode().' ');
    }

    private static function minorUnit(): int
    {
        return max(0, (int) config('marketplace.currency.minor_unit', 2));
    }

    private static function prefix(?string $currency = null): string
    {
        $prefix = $currency ?: self::currencySymbol();

        return preg_match('/^[A-Z]{3}$/', $prefix) ? $prefix.' ' : $prefix;
    }
}
