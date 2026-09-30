<?php

namespace App\Notifications\Lifecycle;

use App\Notifications\MarketplaceNotification;

 class WalletDebitNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'wallet_debit';
    }
}
