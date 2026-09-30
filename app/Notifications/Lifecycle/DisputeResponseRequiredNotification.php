<?php

namespace App\Notifications\Lifecycle;

use App\Notifications\MarketplaceNotification;

 class DisputeResponseRequiredNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'dispute_response_required';
    }
}
