<?php

namespace App\Notifications\Payouts;

use App\Notifications\MarketplaceNotification;

class PayoutRejectedNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'payout_rejected';
    }
}
