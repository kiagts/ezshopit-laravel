<?php

namespace App\Notifications\Products;

use App\Notifications\MarketplaceNotification;

class ProductSubmittedNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'product_submitted';
    }
}
