<?php

namespace App\Notifications\Payouts;

use App\Notifications\MarketplaceNotification;

class PayoutProcessingNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'payout_processing';
    }
}
