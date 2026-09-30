<?php

namespace App\Notifications\Reviews;

use App\Notifications\MarketplaceNotification;

class ReviewRejectedNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'review_rejected';
    }
}
