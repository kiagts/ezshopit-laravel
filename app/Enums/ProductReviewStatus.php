<?php

namespace App\Enums;

enum ProductReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Published',
            self::Rejected => 'Rejected',
        };
    }

    public function adminLabel(): string
    {
        return str($this->value)->title()->toString();
    }

    public function badgeTone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }
}
