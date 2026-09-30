<?php

namespace App\Notifications\Orders;

use App\Notifications\MarketplaceNotification;

class VendorOrderCreatedNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'vendor_order_created';
    }
}
