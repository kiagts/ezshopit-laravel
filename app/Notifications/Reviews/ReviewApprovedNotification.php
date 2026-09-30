<?php

namespace App\Notifications\Reviews;

use App\Notifications\MarketplaceNotification;

class ReviewApprovedNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'review_approved';
    }
}
