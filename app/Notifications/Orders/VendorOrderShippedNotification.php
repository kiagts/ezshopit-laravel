<?php

namespace App\Notifications\Orders;

use App\Notifications\MarketplaceNotification;

class VendorOrderShippedNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'vendor_order_shipped';
    }
}
