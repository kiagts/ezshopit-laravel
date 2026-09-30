<?php

namespace App\Notifications\Payouts;

use App\Notifications\MarketplaceNotification;

class PayoutRequestedNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'payout_requested';
    }
}
