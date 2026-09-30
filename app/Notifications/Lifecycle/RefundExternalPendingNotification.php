<?php

namespace App\Notifications\Lifecycle;

use App\Notifications\MarketplaceNotification;

 class RefundExternalPendingNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'refund_external_pending';
    }
}
