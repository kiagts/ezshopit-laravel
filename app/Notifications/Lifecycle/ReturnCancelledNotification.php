<?php

namespace App\Notifications\Lifecycle;

use App\Notifications\MarketplaceNotification;

 class ReturnCancelledNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'return_cancelled';
    }
}
