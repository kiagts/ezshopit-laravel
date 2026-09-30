<?php

namespace App\Enums;

enum ReturnReason: string
{
    case Damaged = 'damaged';
    case Defective = 'defective';
    case WrongItem = 'wrong_item';
    case NotAsDescribed = 'not_as_described';
    case MissingParts = 'missing_parts';
    case SizeOrFit = 'size_or_fit';
    case ChangedMind = 'changed_mind';
    case Other = 'other';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
