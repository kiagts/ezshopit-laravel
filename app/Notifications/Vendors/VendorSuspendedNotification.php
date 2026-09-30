<?php

namespace App\Notifications\Vendors;

use App\Notifications\MarketplaceNotification;

class VendorSuspendedNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'vendor_suspended';
    }
}
