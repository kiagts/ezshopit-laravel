<?php

namespace App\Notifications\Vendors;

use App\Notifications\MarketplaceNotification;

class VendorApplicationSubmittedNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'vendor_application_submitted';
    }
}
