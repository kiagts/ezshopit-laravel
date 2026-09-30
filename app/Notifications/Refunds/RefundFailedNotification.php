<?php

namespace App\Notifications\Refunds;

use App\Notifications\MarketplaceNotification;

class RefundFailedNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'refund_failed';
    }
}
