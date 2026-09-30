<?php

namespace App\Notifications\Lifecycle;

use App\Notifications\MarketplaceNotification;

 class ReturnRejectedNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'return_rejected';
    }
}
