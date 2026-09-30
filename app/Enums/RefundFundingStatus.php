<?php
namespace App\Enums;
enum RefundFundingStatus: string {
    case Pending = 'pending';
    case Completed = 'completed';
    case PendingProvider = 'pending_provider';
}

