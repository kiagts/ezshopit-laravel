<?php

namespace App\Notifications\Lifecycle;

use App\Notifications\MarketplaceNotification;

 class SystemFinancialFailureNotification extends MarketplaceNotification
{
    public static function eventType(): string
    {
        return 'system_financial_failure';
    }
}
