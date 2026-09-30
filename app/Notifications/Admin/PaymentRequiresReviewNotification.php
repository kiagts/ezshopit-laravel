<?php

namespace App\Notifications\Admin;

use App\Notifications\MarketplaceNotification;

class PaymentRequiresReviewNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'payment_requires_review';
    }
}
