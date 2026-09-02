<?php

namespace Database\Seeders;

use App\Actions\Commissions\ApplyRefundCommissionAdjustments;
use App\Actions\Commissions\RecognizeVendorOrderCommission;
use App\Actions\VendorLedger\PostVendorRefundDebits;
use App\Enums\CommissionRuleScope;
use App\Enums\PaymentStatus;
use App\Enums\RefundAllocationType;
use App\Enums\RefundStatus;
use App\Enums\VendorOrderStatus;
use App\Models\CommissionRule;
use App\Models\Refund;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CommissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->rules();
        });

        $recognize = app(RecognizeVendorOrderCommission::class);
        $postRefundDebits = app(PostVendorRefundDebits::class);
        $applyRefundAdjustments = app(ApplyRefundCommissionAdjustments::class);

        VendorOrder::query()
            ->whereIn('vendor_order_number', [
                'DEMO-LIFE-DELIVERED-01',
                'DEMO-LIFE-MIXED-01',
                'DEMO-LIFE-REFUND-NONE-01',
                'DEMO-LIFE-REFUND-PARTIAL-01',
                'DEMO-LIFE-REFUND-FULL-01',
            ])
            ->where('status', VendorOrderStatus::Delivered)
            ->whereHas('order.payment', fn ($query) => $query->whereIn('status', [
                PaymentStatus::Paid->value,
                PaymentStatus::PartiallyRefunded->value,
                PaymentStatus::Refunded->value,
            ]))
            ->get()
            ->each(fn (VendorOrder $vendorOrder) => $recognize->handle($vendorOrder));

        $this->postRecognitionAdjustmentDemo();

        Refund::query()
            ->where('status', RefundStatus::Succeeded)
            ->whereHas('order', fn ($query) => $query->where('order_number', 'like', 'DEMO-LIFE-%'))
            ->get()
            ->each(function (Refund $refund) use ($postRefundDebits, $applyRefundAdjustments): void {
                $postRefundDebits->handle($refund);
                $applyRefundAdjustments->handle($refund);
            });
    }

    private function rules(): void
    {
        CommissionRule::query()->updateOrCreate(
            [
                'scope' => CommissionRuleScope::Global,
                'vendor_id' => null,
                'is_active' => true,
            ],
            [
                'rate_basis_points' => 1000,
                'include_shipping' => false,
            ],
        );

        $this->vendorOverride('techverse', 800);
        $this->vendorOverride('lifecycle-goods', 1200);
    }

    private function vendorOverride(string $slug, int $rateBasisPoints): void
    {
        $vendor = Vendor::query()->where('slug', $slug)->first();

        if (! $vendor) {
            return;
        }

        CommissionRule::query()->updateOrCreate(
            [
                'scope' => CommissionRuleScope::Vendor,
                'vendor_id' => $vendor->id,
                'is_active' => true,
            ],
            [
                'rate_basis_points' => $rateBasisPoints,
                'include_shipping' => false,
            ],
        );
    }

    private function postRecognitionAdjustmentDemo(): void
    {
        $vendorOrder = VendorOrder::query()
            ->with(['commission', 'items', 'order.payment'])
            ->where('vendor_order_number', 'DEMO-LIFE-REFUND-NONE-01')
            ->first();

        if (! $vendorOrder?->commission || ! $vendorOrder->order?->payment || ! $vendorOrder->items->first()) {
            return;
        }

        $payment = $vendorOrder->order->payment;
        $refund = Refund::query()->updateOrCreate(
            ['refund_number' => 'REF-DEMO-LIFE-COMMISSION-ADJUSTED'],
            [
                'order_id' => $vendorOrder->order_id,
                'payment_id' => $payment->id,
                'status' => RefundStatus::Succeeded,
                'amount' => 1500,
                'currency' => $payment->currency,
                'reason' => 'Post-recognition item refund for commission adjustment demo',
                'provider' => $payment->provider,
                'provider_reference' => 're_demo_commission_adjusted',
                'requested_by' => User::query()->where('email', 'admin@example.com')->value('id'),
                'requested_at' => $vendorOrder->commission->recognized_at->copy()->addSeconds(30),
                'processed_at' => $vendorOrder->commission->recognized_at->copy()->addMinute(),
            ],
        );

        $refund->allocations()->updateOrCreate(
            [
                'vendor_order_id' => $vendorOrder->id,
                'order_item_id' => $vendorOrder->items->first()->id,
                'type' => RefundAllocationType::Item,
            ],
            [
                'amount' => 1500,
                'quantity' => 1,
            ],
        );

        $payment->forceFill(['status' => PaymentStatus::PartiallyRefunded])->save();
        $vendorOrder->order->forceFill(['payment_status' => PaymentStatus::PartiallyRefunded])->save();
    }
}
