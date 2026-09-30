<?php

namespace App\Notifications\Lifecycle;

use App\Notifications\MarketplaceNotification;

 class RefundImpactNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'refund_impact';
    }
}
