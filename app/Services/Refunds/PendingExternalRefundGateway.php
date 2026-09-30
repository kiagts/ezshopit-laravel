<?php

namespace App\Services\Refunds;

use App\Enums\RefundFundingStatus;
use App\Models\RefundFundingAllocation;

class PendingExternalRefundGateway implements ExternalRefundGateway
{
    public function request(RefundFundingAllocation $allocation): RefundFundingStatus
    {
        return RefundFundingStatus::PendingProvider;
    }
}
