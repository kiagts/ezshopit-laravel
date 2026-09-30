<?php

namespace App\Notifications\Products;

use App\Notifications\MarketplaceNotification;

class ProductRejectedNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'product_rejected';
    }
}
