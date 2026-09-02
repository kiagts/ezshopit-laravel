<?php

namespace App\Enums;

enum VendorPayoutMethodType: string
{
    case BankTransfer = 'bank_transfer';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'Bank transfer',
        };
    }
}
