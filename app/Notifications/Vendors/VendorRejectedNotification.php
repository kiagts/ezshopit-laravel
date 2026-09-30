<?php

namespace App\Notifications\Vendors;

use App\Notifications\MarketplaceNotification;

class VendorRejectedNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'vendor_rejected';
    }
}
