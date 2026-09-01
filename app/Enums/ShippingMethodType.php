<?php

namespace App\Enums;

enum ShippingMethodType: string
{
    case FlatRate = 'flat_rate';

    public function label(): string
    {
        return match ($this) {
            self::FlatRate => 'Flat rate',
        };
    }
}
