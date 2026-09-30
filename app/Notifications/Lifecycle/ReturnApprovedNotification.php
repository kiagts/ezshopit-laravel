<?php

namespace App\Notifications\Lifecycle;

use App\Notifications\MarketplaceNotification;

 class ReturnApprovedNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'return_approved';
    }
}
