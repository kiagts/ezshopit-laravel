<?php

namespace App\Enums;

enum PromotionFundingSource: string
{
    case Marketplace = 'marketplace';
    case Vendor = 'vendor';

    public function label(): string
    {
        return match ($this) {
            self::Marketplace => 'Marketplace funded',
            self::Vendor => 'Vendor funded',
        };
    }
}
