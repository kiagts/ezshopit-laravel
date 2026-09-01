<?php

namespace App\Enums;

enum VendorOrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case ReadyToShip = 'ready_to_ship';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }

    public function badgeTone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Confirmed, self::Processing, self::ReadyToShip, self::Shipped => 'brand',
            self::Delivered => 'success',
            self::Cancelled => 'danger',
        };
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Processing, self::Cancelled],
            self::Processing => [self::ReadyToShip, self::Cancelled],
            self::ReadyToShip => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered],
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isCancellable(): bool
    {
        return in_array($this, [
            self::Pending,
            self::Confirmed,
            self::Processing,
            self::ReadyToShip,
        ], true);
    }

    public function nextFulfillmentStatus(): ?self
    {
        return match ($this) {
            self::Pending => self::Confirmed,
            self::Confirmed => self::Processing,
            self::Processing => self::ReadyToShip,
            self::ReadyToShip => self::Shipped,
            self::Shipped => self::Delivered,
            self::Delivered, self::Cancelled => null,
        };
    }

    public function actionLabel(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirm Order',
            self::Processing => 'Start Processing',
            self::ReadyToShip => 'Mark Ready to Ship',
            self::Shipped => 'Mark as Shipped',
            self::Delivered => 'Mark Delivered',
            self::Pending => 'Pending',
            self::Cancelled => 'Cancelled',
        };
    }
}
