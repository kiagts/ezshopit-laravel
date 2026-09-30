<?php

namespace App\Notifications\Lifecycle;

use App\Notifications\MarketplaceNotification;

 class GiftCardIssuedNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'gift_card_issued';
    }
}
