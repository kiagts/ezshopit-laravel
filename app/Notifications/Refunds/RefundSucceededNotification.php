<?php

namespace App\Notifications\Refunds;

use App\Notifications\MarketplaceNotification;

class RefundSucceededNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'refund_succeeded';
    }
}
