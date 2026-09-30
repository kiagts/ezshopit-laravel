<?php
namespace App\Enums;
enum RefundFundingSource: string {
    case GiftCard = 'gift_card';
    case Wallet = 'wallet';
    case ExternalPayment = 'external_payment';
    public function label(): string { return match ($this) { self::GiftCard => 'Gift Card', self::Wallet => 'Wallet', self::ExternalPayment => 'Original payment method' }; }
}

