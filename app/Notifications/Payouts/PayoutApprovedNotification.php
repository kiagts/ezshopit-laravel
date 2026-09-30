<?php

namespace App\Notifications\Payouts;

use App\Notifications\MarketplaceNotification;

class PayoutApprovedNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'payout_approved';
    }
}
