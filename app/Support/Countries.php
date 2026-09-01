<?php

namespace App\Support;

class Countries
{
    /**
     * @return array<string, string>
     */
    public static function supported(): array
    {
        return config('marketplace.checkout.supported_countries', []);
    }

    /**
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return array_keys(self::supported());
    }

    public static function name(?string $code): string
    {
        if (! $code) {
            return '';
        }

        return self::supported()[strtoupper($code)] ?? strtoupper($code);
    }
}
