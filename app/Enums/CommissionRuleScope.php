<?php

namespace App\Enums;

enum CommissionRuleScope: string
{
    case Global = 'global';
    case Vendor = 'vendor';

    public function label(): string
    {
        return str($this->value)->title()->toString();
    }
}
