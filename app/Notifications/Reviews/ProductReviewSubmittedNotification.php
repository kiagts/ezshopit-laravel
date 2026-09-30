<?php

namespace App\Notifications\Reviews;

use App\Notifications\MarketplaceNotification;

class ProductReviewSubmittedNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'product_review_submitted';
    }
}
