<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case Initial = 'initial';
    case Increase = 'increase';
    case Decrease = 'decrease';
    case Adjustment = 'adjustment';
    case OrderFulfillment = 'order_fulfillment';

    public function label(): string
    {
        return str($this->value)->title()->toString();
    }

    public function badgeTone(): string
    {
        return match ($this) {
            self::Initial, self::Increase => 'success',
            self::Decrease, self::OrderFulfillment => 'warning',
            self::Adjustment => 'brand',
        };
    }
}
