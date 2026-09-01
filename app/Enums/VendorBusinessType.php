<?php

namespace App\Enums;

enum VendorBusinessType: string
{
    case Individual = 'individual';
    case SoleProprietor = 'sole_proprietor';
    case Company = 'company';
    case Partnership = 'partnership';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
