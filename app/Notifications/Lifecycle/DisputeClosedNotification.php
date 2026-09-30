<?php

namespace App\Notifications\Lifecycle;

use App\Notifications\MarketplaceNotification;

 class DisputeClosedNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'dispute_closed';
    }
}
