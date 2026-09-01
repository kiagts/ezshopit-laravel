<?php

namespace App\Actions\Commissions;

use App\Enums\RefundAllocationType;
use App\Enums\RefundStatus;
use App\Models\RefundAllocation;
use App\Models\VendorOrder;
use App\Models\VendorOrderCommission;
use App\Services\Commissions\CommissionCalculator;
use App\Services\Commissions\CommissionRuleResolver;
use Illuminate\Support\Facades\DB;

class RecognizeVendorOrderCommission
{
    public function __construct(
        private readonly CanRecognizeCommission $canRecognizeCommission,
        private readonly CommissionRuleResolver $ruleResolver,
        private readonly CommissionCalculator $calculator,
    ) {
    }

    public function handle(VendorOrder $vendorOrder): ?VendorOrderCommission
    {
        return DB::transaction(function () use ($vendorOrder): ?VendorOrderCommission {
            $vendorOrder = VendorOrder::query()
                ->with(['order.payment', 'items'])
                ->lockForUpdate()
                ->findOrFail($vendorOrder->id);

            $existing = $vendorOrder->commission()->with('adjustments')->first();

            if ($existing) {
                return $existing;
            }

            if (! $this->canRecognizeCommission->check($vendorOrder)) {
                return null;
            }

            $rule = $this->ruleResolver->resolveFor($vendorOrder);
            $originalCommissionable = $this->calculator->commissionableAmount(
                (int) $vendorOrder->items_subtotal,
                (int) $vendorOrder->shipping_total,
                (bool) $rule->include_shipping,
            );
            $preRecognitionRefundedBasis = $this->preRecognitionRefundedBasis($vendorOrder, (bool) $rule->include_shipping);
            $netCommissionable = max(0, $originalCommissionable - $preRecognitionRefundedBasis);

            $commission = $vendorOrder->commission()->create([
                'commission_rule_id' => $rule->id,
                'rate_basis_points' => $rule->rate_basis_points,
                'include_shipping' => $rule->include_shipping,
                'original_items_amount' => $vendorOrder->items_subtotal,
                'original_shipping_amount' => $vendorOrder->shipping_total,
                'original_commissionable_amount' => $netCommissionable,
                'original_commission_amount' => $this->calculator->calculate($netCommissionable, $rule->rate_basis_points),
                'currency' => $vendorOrder->order->currency,
                'recognized_at' => now(),
            ]);

            return $commission->load('adjustments');
        }, 3);
    }

    private function preRecognitionRefundedBasis(VendorOrder $vendorOrder, bool $includeShipping): int
    {
        return (int) RefundAllocation::query()
            ->where('vendor_order_id', $vendorOrder->id)
            ->whereHas('refund', fn ($query) => $query->where('status', RefundStatus::Succeeded->value))
            ->where(function ($query) use ($includeShipping): void {
                $query->where('type', RefundAllocationType::Item->value);

                if ($includeShipping) {
                    $query->orWhere('type', RefundAllocationType::Shipping->value);
                }
            })
            ->sum('amount');
    }
}
