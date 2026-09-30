<?php

namespace App\Notifications\Lifecycle;

use App\Notifications\MarketplaceNotification;

 class DisputeOpenedNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'dispute_opened';
    }
}
