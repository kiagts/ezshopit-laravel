<?php

namespace App\Enums;

enum VendorPayoutStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Processing = 'processing';
    case Paid = 'paid';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }

    public function badgeTone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'brand',
            self::Processing => 'neutral',
            self::Paid => 'success',
            self::Rejected, self::Cancelled => 'danger',
        };
    }

    public function isActiveReservation(): bool
    {
        return in_array($this, [self::Pending, self::Approved, self::Processing], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Paid, self::Rejected, self::Cancelled], true);
    }
}
