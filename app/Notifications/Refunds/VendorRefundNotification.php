<?php

namespace App\Notifications\Refunds;

use App\Notifications\MarketplaceNotification;

class VendorRefundNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'vendor_refund';
    }
}
