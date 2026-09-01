<?php

namespace App\Services\Commissions;

use App\Enums\CommissionRuleScope;
use App\Models\CommissionRule;
use App\Models\VendorOrder;
use Illuminate\Validation\ValidationException;

class CommissionRuleResolver
{
    public function resolveFor(VendorOrder $vendorOrder): CommissionRule
    {
        $at = $vendorOrder->delivered_at ?? $vendorOrder->order?->placed_at ?? now();

        $vendorRule = CommissionRule::query()
            ->where('scope', CommissionRuleScope::Vendor)
            ->where('vendor_id', $vendorOrder->vendor_id)
            ->where('is_active', true)
            ->where(function ($query) use ($at): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function ($query) use ($at): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $at);
            })
            ->latest()
            ->first();

        if ($vendorRule) {
            return $vendorRule;
        }

        $globalRule = CommissionRule::query()
            ->where('scope', CommissionRuleScope::Global)
            ->whereNull('vendor_id')
            ->where('is_active', true)
            ->where(function ($query) use ($at): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function ($query) use ($at): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $at);
            })
            ->latest()
            ->first();

        if (! $globalRule) {
            throw ValidationException::withMessages(['commission' => 'No active global commission rule is configured.']);
        }

        return $globalRule;
    }
}
