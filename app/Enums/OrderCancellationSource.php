<?php

namespace App\Enums;

enum OrderCancellationSource: string
{
    case Customer = 'customer';
    case PaymentExpired = 'payment_expired';
    case Admin = 'admin';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::PaymentExpired => 'Payment Expired',
            self::Admin => 'Admin',
            self::System => 'System',
        };
    }

    public function publicMessage(): string
    {
        return match ($this) {
            self::Customer => 'You cancelled this unpaid order.',
            self::PaymentExpired => 'Payment was not completed within the allowed time.',
            self::Admin, self::System => 'This order was cancelled before payment was completed.',
        };
    }
}
