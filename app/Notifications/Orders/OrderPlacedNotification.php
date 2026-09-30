<?php

namespace App\Notifications\Orders;

use App\Notifications\MarketplaceNotification;

class OrderPlacedNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'order_placed';
    }
}
