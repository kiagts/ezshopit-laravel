<?php

namespace App\Enums;

enum CommissionAdjustmentType: string
{
    case Refund = 'refund';
    case Manual = 'manual';

    public function label(): string
    {
        return str($this->value)->title()->toString();
    }
}
