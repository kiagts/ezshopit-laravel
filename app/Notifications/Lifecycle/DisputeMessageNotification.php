<?php

namespace App\Notifications\Lifecycle;

use App\Notifications\MarketplaceNotification;

 class DisputeMessageNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'dispute_message';
    }
}
