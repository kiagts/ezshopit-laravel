<?php

namespace App\Actions\Commissions;

use App\Enums\RefundAllocationType;
use App\Enums\RefundStatus;
use App\Models\RefundAllocation;
use App\Models\VendorOrder;
use App\Models\VendorOrderCommission;
use App\Services\Commissions\CommissionCalculator;
use App\Services\Commissions\CommissionRuleResolver;
use App\Actions\VendorLedger\PostVendorOrderEarnings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecognizeVendorOrderCommission
{
    public function __construct(
        private readonly CanRecognizeCommission $canRecognizeCommission,
        private readonly CommissionRuleResolver $ruleResolver,
        private readonly CommissionCalculator $calculator,
        private readonly PostVendorOrderEarnings $postVendorOrderEarnings,
    ) {
    }

    public function handle(VendorOrder $vendorOrder): ?VendorOrderCommission
    {
        $commission = DB::transaction(function () use ($vendorOrder): ?VendorOrderCommission {
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
            $sellerItemsAmount = max(0, (int) $vendorOrder->items_subtotal - (int) $vendorOrder->vendor_discount_total);
            $originalCommissionable = $this->calculator->commissionableAmount(
                $sellerItemsAmount,
                (int) $vendorOrder->shipping_total,
                (bool) $rule->include_shipping,
            );
            $preRecognitionRefundedBasis = $this->preRecognitionRefundedBasis($vendorOrder, (bool) $rule->include_shipping);
            $netCommissionable = max(0, $originalCommissionable - $preRecognitionRefundedBasis);

            $commission = $vendorOrder->commission()->create([
                'commission_rule_id' => $rule->id,
                'rate_basis_points' => $rule->rate_basis_points,
                'include_shipping' => $rule->include_shipping,
                'original_items_amount' => $sellerItemsAmount,
                'original_shipping_amount' => $vendorOrder->shipping_total,
                'original_commissionable_amount' => $netCommissionable,
                'original_commission_amount' => $this->calculator->calculate($netCommissionable, $rule->rate_basis_points),
                'currency' => $vendorOrder->order->currency,
                'recognized_at' => now(),
            ]);

            return $commission->load('adjustments');
        }, 3);

        if ($commission) {
            try {
                $this->postVendorOrderEarnings->handle($commission);
            } catch (\Throwable $exception) {
                Log::error('Vendor ledger earnings posting failed after commission recognition.', [
                    'vendor_order_commission_id' => $commission->id,
                    'vendor_order_id' => $commission->vendor_order_id,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $commission;
    }

    private function preRecognitionRefundedBasis(VendorOrder $vendorOrder, bool $includeShipping): int
    {
        return (int) RefundAllocation::query()
            ->where('vendor_order_id', $vendorOrder->id)
            ->whereHas('refund', fn ($query) => $query->where(fn ($q) => $q->where('status', RefundStatus::Succeeded->value)->orWhereNotNull('economics_posted_at')))
            ->where(function ($query) use ($includeShipping): void {
                $query->where('type', RefundAllocationType::Item->value);

                if ($includeShipping) {
                    $query->orWhere('type', RefundAllocationType::Shipping->value);
                }
            })
            ->sum(DB::raw('COALESCE(seller_amount, amount)'));
    }
}
