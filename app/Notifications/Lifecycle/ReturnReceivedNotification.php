<?php

namespace App\Notifications\Lifecycle;

use App\Notifications\MarketplaceNotification;

 class ReturnReceivedNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'return_received';
    }
}
