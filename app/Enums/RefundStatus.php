<?php

namespace App\Enums;

enum RefundStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Succeeded => 'Refunded',
            default => str($this->value)->replace('_', ' ')->title()->toString(),
        };
    }

    public function customerLabel(): string
    {
        return match ($this) {
            self::Pending, self::Processing => 'Refund processing',
            self::Succeeded => 'Refunded',
            self::Failed, self::Cancelled => 'Refund not completed',
        };
    }

    public function badgeTone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Processing => 'brand',
            self::Succeeded => 'success',
            self::Failed, self::Cancelled => 'danger',
        };
    }

    public function protectsCapacity(): bool
    {
        return in_array($this, [self::Pending, self::Processing], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed, self::Cancelled], true);
    }
}
