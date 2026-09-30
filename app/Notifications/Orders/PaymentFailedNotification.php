<?php

namespace App\Notifications\Orders;

use App\Notifications\MarketplaceNotification;

class PaymentFailedNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'payment_failed';
    }
}
