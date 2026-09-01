<?php

namespace App\Services\Payments;

use App\Models\Refund;

interface RefundablePaymentProvider
{
    public function refund(Refund $refund): PaymentRefundResult;
}
