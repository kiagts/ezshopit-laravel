<?php

namespace App\Enums;

enum ProductAttributeType: string
{
    case Variant = 'variant';
    case Specification = 'specification';

    public function label(): string
    {
        return match ($this) {
            self::Variant => 'Variant',
            self::Specification => 'Specification',
        };
    }
}
