<?php

namespace App\Notifications\Lifecycle;

use App\Notifications\MarketplaceNotification;

 class ReturnRequestedNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'return_requested';
    }
}
