<?php

namespace App\Services\Returns;

use App\Enums\VendorOrderStatus;
use App\Models\OrderItem;
use App\Models\ReturnItem;
use App\Services\SettingsService;

class ReturnEligibilityService
{
    public function windowDays(): int
    {
        return (int) app(SettingsService::class)->get('returns.window_days', 30);
    }

    public function remaining(OrderItem $item): int
    {
        $committed = ReturnItem::where('order_item_id', $item->id)
            ->whereHas('returnRequest', fn ($q) => $q->whereNotIn('status', ['rejected', 'cancelled']))
            ->sum('quantity');

        return max(0, $item->quantity - (int) $committed);
    }

    public function check(OrderItem $item): array
    {
        $vendorOrder = $item->vendorOrder;
        $reason = match (true) {
            ! $item->is_returnable => 'This item is not returnable.',
            $vendorOrder->status !== VendorOrderStatus::Delivered => 'This item has not been delivered.',
            ! $vendorOrder->delivered_at => 'Delivery confirmation is required.',
            $vendorOrder->delivered_at->isFuture() => 'Delivery confirmation is invalid.',
            now()->greaterThan($vendorOrder->delivered_at->copy()->addDays($this->windowDays())) => 'The return window has expired.',
            default => null,
        };
        $remaining = $reason ? 0 : $this->remaining($item);

        return ['quantity' => $remaining, 'reason' => $reason ?? ($remaining ? null : 'All quantities are already in a return.')];
    }
}
