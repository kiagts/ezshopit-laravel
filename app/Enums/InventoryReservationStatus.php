<?php

namespace App\Enums;

enum InventoryReservationStatus: string
{
    case Active = 'active';
    case Committed = 'committed';
    case Released = 'released';

    public function label(): string
    {
        return str($this->value)->title()->toString();
    }
}
