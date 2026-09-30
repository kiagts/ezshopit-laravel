<?php

namespace App\Enums;

enum ReturnCondition: string
{
    case Uninspected = 'uninspected';
    case Restockable = 'restockable';
    case Damaged = 'damaged';
    case Defective = 'defective';
    case MissingParts = 'missing_parts';
    case WrongItem = 'wrong_item';
    case NotReceived = 'not_received';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
