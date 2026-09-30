<?php

namespace App\Notifications\Orders;

use App\Notifications\MarketplaceNotification;

class VendorOrderCancelledNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'vendor_order_cancelled';
    }
}
