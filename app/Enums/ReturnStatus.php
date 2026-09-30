<?php

namespace App\Enums;

enum ReturnStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case AwaitingReturn = 'awaiting_return';
    case Received = 'received';
    case Inspected = 'inspected';
    case RefundPending = 'refund_pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }

    public function targets(): array
    {
        return match ($this) {
            self::Requested => [self::Approved, self::Rejected, self::Cancelled],
            self::Approved => [self::AwaitingReturn, self::Cancelled],
            self::AwaitingReturn => [self::Received],
            self::Received => [self::Inspected],
            self::Inspected => [self::RefundPending, self::Completed],
            default => [],
        };
    }
}
