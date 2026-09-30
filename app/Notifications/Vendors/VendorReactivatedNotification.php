<?php

namespace App\Notifications\Vendors;

use App\Notifications\MarketplaceNotification;

class VendorReactivatedNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'vendor_reactivated';
    }
}
