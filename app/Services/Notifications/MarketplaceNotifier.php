<?php

namespace App\Services\Notifications;

use App\Events\CommerceNotificationRequested;
use Illuminate\Support\Facades\DB;

/** Compatibility entry point: commerce actions emit events, delivery stays in listeners. */
class MarketplaceNotifier
{
    public function __call(string $operation, array $arguments): void
    {
        if (! in_array($operation, ['orderPlaced', 'paymentChanged', 'paymentRequiresReview', 'newVendorOrder', 'vendorOrderStatusChanged', 'orderCancelled', 'refundChanged', 'payoutRequested', 'payoutStatusChanged', 'vendorApplicationSubmitted', 'vendorStatusChanged', 'productSubmitted', 'productStatusChanged', 'reviewSubmitted', 'reviewModerated'], true)) {
            throw new \InvalidArgumentException('Unknown commerce event.');
        }
        DB::afterCommit(fn () => event(new CommerceNotificationRequested($operation, $arguments)));
    }
}
