<?php

namespace App\Enums;

enum DisputeResolution: string
{
    case CustomerRefund = 'customer_favored_refund';
    case CustomerPartialRefund = 'customer_favored_partial_refund';
    case VendorFavored = 'vendor_favored';
    case ReturnApproved = 'return_approved';
    case ReturnRejected = 'return_rejected';
    case NoFinancialAction = 'no_financial_action';

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }

    public function financial(): bool
    {
        return in_array($this, [self::CustomerRefund, self::CustomerPartialRefund], true);
    }
}
