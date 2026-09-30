<?php

namespace App\Notifications\Lifecycle;

use App\Notifications\MarketplaceNotification;

 class DisputeResolvedNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'dispute_resolved';
    }
}
