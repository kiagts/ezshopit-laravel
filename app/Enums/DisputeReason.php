<?php

namespace App\Enums;

enum DisputeReason: string
{
    case ReturnRejected = 'return_rejected';
    case RefundAmount = 'refund_amount';
    case ItemCondition = 'item_condition';
    case ItemNotReceived = 'item_not_received';
    case WrongItem = 'wrong_item';
    case NotAsDescribed = 'not_as_described';
    case SellerDisagreement = 'seller_disagreement';
    case Other = 'other';

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }
}
