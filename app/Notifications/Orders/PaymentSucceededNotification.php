<?php

namespace App\Notifications\Orders;

use App\Notifications\MarketplaceNotification;

class PaymentSucceededNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'payment_succeeded';
    }
}
