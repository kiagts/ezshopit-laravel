<?php

namespace App\Notifications\Orders;

use App\Notifications\MarketplaceNotification;

class OrderCancelledNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'order_cancelled';
    }
}
