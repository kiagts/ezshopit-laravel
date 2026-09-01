<?php

namespace App\Actions\Commissions;

use App\Enums\PaymentStatus;
use App\Enums\VendorOrderStatus;
use App\Models\VendorOrder;

class CanRecognizeCommission
{
    public function check(VendorOrder $vendorOrder): bool
    {
        $vendorOrder->loadMissing('order.payment');

        if ($vendorOrder->status !== VendorOrderStatus::Delivered) {
            return false;
        }

        $payment = $vendorOrder->order?->payment;

        if (! $payment) {
            return false;
        }

        return in_array($payment->status, [
            PaymentStatus::Paid,
            PaymentStatus::PartiallyRefunded,
            PaymentStatus::Refunded,
        ], true);
    }
}
