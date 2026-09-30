<?php

namespace App\Notifications\Lifecycle;

use App\Notifications\MarketplaceNotification;

 class RefundProcessingNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'refund_processing';
    }
}
