<?php

namespace App\Enums;

enum NotificationCategory: string
{
    case Account = 'account';
    case Vendor = 'vendor';
    case Product = 'product';
    case Order = 'order';
    case Payment = 'payment';
    case Fulfillment = 'fulfillment';
    case ReturnCase = 'return';
    case Refund = 'refund';
    case Dispute = 'dispute';
    case Wallet = 'wallet';
    case GiftCard = 'gift_card';
    case Payout = 'payout';
    case System = 'system';

    public function required(): bool
    {
        return ! in_array($this, [self::Product, self::Fulfillment, self::System], true);
    }

    public static function forType(string $type): self
    {
        if (str_starts_with($type, 'vendor_order_shipped') || str_starts_with($type, 'vendor_order_delivered')) {
            return self::Fulfillment;
        }
        if (str_starts_with($type, 'vendor_order')) {
            return self::Order;
        }
        if ($type === 'system_financial_failure') {
            return self::Account;
        }
        if (str_starts_with($type, 'gift_card')) {
            return self::GiftCard;
        }

        return self::tryFrom(explode('_', $type)[0]) ?? self::System;
    }
}
