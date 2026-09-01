<?php

namespace App\Enums;

enum ProductAttributeInputType: string
{
    case Select = 'select';
    case Multiselect = 'multiselect';
    case Text = 'text';
    case Number = 'number';
    case Boolean = 'boolean';

    public function label(): string
    {
        return match ($this) {
            self::Select => 'Select',
            self::Multiselect => 'Multiselect',
            self::Text => 'Text',
            self::Number => 'Number',
            self::Boolean => 'Boolean',
        };
    }

    public function usesPredefinedValues(): bool
    {
        return in_array($this, [self::Select, self::Multiselect], true);
    }
}
