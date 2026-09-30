<?php

namespace App\Notifications\Lifecycle;

use App\Notifications\MarketplaceNotification;

 class ReturnRefundPendingNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'return_refund_pending';
    }
}
