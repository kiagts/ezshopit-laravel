<?php

namespace App\Enums;

enum VendorLedgerEntryType: string
{
    case Sale = 'sale';
    case Commission = 'commission';
    case Refund = 'refund';
    case CommissionRefund = 'commission_refund';
    case Payout = 'payout';
    case Promotion = 'promotion';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Sale Credit',
            self::Commission => 'Commission Debit',
            self::Refund => 'Refund Debit',
            self::CommissionRefund => 'Commission Refund Credit',
            self::Payout => 'Payout Debit',
            self::Promotion => 'Promotion Debit',
        };
    }
}
