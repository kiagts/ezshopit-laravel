<?php

namespace App\Console\Commands;

use App\Actions\Commissions\ApplyRefundCommissionAdjustment;
use App\Actions\Commissions\RecognizeVendorOrderCommission;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\VendorOrderStatus;
use App\Models\RefundAllocation;
use App\Models\VendorOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileCommissions extends Command
{
    protected $signature = 'commissions:reconcile {--chunk=100}';

    protected $description = 'Recognize missing vendor order commissions and repair missing refund commission adjustments.';

    public function handle(RecognizeVendorOrderCommission $recognize, ApplyRefundCommissionAdjustment $applyRefundAdjustment): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $recognized = 0;
        $adjusted = 0;
        $failed = 0;

        VendorOrder::query()
            ->where('status', VendorOrderStatus::Delivered)
            ->whereDoesntHave('commission')
            ->whereHas('order.payment', fn ($query) => $query->whereIn('status', [
                PaymentStatus::Paid->value,
                PaymentStatus::PartiallyRefunded->value,
                PaymentStatus::Refunded->value,
            ]))
            ->orderBy('id')
            ->chunkById($chunk, function ($vendorOrders) use ($recognize, &$recognized, &$failed): void {
                foreach ($vendorOrders as $vendorOrder) {
                    try {
                        if ($recognize->handle($vendorOrder)) {
                            $recognized++;
                        }
                    } catch (\Throwable $exception) {
                        $failed++;
                        Log::error('Commission recognition reconciliation failed.', [
                            'vendor_order_id' => $vendorOrder->id,
                            'exception' => $exception::class,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        RefundAllocation::query()
            ->whereNotNull('vendor_order_id')
            ->whereHas('refund', fn ($query) => $query->where('status', RefundStatus::Succeeded->value))
            ->whereHas('vendorOrder.commission')
            ->whereDoesntHave('commissionAdjustment')
            ->orderBy('id')
            ->chunkById($chunk, function ($allocations) use ($applyRefundAdjustment, &$adjusted, &$failed): void {
                foreach ($allocations as $allocation) {
                    try {
                        $before = $allocation->commissionAdjustment()->exists();
                        $applyRefundAdjustment->handle($allocation);
                        $after = $allocation->commissionAdjustment()->exists();

                        if (! $before && $after) {
                            $adjusted++;
                        }
                    } catch (\Throwable $exception) {
                        $failed++;
                        Log::error('Commission refund adjustment reconciliation failed.', [
                            'refund_allocation_id' => $allocation->id,
                            'exception' => $exception::class,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        $this->info("Recognized commissions: {$recognized}");
        $this->info("Created refund adjustments: {$adjusted}");
        $this->info("Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
