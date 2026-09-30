<?php

namespace App\Notifications\Payouts;

use App\Notifications\MarketplaceNotification;

class PayoutPaidNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'payout_paid';
    }
}
