<?php

namespace App\Services\Refunds;

use App\Enums\RefundFundingStatus;
use App\Models\RefundFundingAllocation;

interface ExternalRefundGateway
{
    public function request(RefundFundingAllocation $allocation): RefundFundingStatus;
}
