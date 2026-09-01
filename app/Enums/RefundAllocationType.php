<?php

namespace App\Enums;

enum RefundAllocationType: string
{
    case Item = 'item';
    case Shipping = 'shipping';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
