<?php

namespace App\Notifications\Vendors;

use App\Notifications\MarketplaceNotification;

class VendorApprovedNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'vendor_approved';
    }
}
