<?php

namespace App\Enums;

enum DisputeStatus: string
{
    case Open = 'open';
    case UnderReview = 'under_review';
    case AwaitingCustomer = 'awaiting_customer';
    case AwaitingVendor = 'awaiting_vendor';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function active(): bool
    {
        return ! in_array($this, [self::Resolved, self::Closed], true);
    }

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }

    public function targets(): array
    {
        return match ($this) {
            self::Open => [self::UnderReview],
            self::UnderReview => [self::AwaitingCustomer, self::AwaitingVendor],
            self::AwaitingCustomer,self::AwaitingVendor => [self::UnderReview],
            self::Resolved => [self::Closed], self::Closed => [],
        };
    }
}
