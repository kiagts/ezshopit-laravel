<?php

namespace App\Enums;

enum ProductType: string
{
    case Simple = 'simple';
    case Variant = 'variant';

    public function label(): string
    {
        return match ($this) {
            self::Simple => 'Simple Product',
            self::Variant => 'Product With Variants',
        };
    }
}
