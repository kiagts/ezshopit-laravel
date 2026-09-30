<?php

namespace App\Notifications\Products;

use App\Notifications\MarketplaceNotification;

class ProductApprovedNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'product_approved';
    }
}
