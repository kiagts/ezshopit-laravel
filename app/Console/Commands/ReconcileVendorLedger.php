<?php

namespace App\Console\Commands;

use App\Actions\VendorLedger\PostVendorCommissionRefundCredit;
use App\Actions\VendorLedger\PostVendorOrderEarnings;
use App\Actions\VendorLedger\PostVendorRefundDebit;
use App\Enums\CommissionAdjustmentType;
use App\Enums\RefundAllocationType;
use App\Enums\RefundStatus;
use App\Models\CommissionAdjustment;
use App\Models\RefundAllocation;
use App\Models\VendorOrderCommission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileVendorLedger extends Command
{
    protected $signature = 'vendor-ledger:reconcile {--chunk=100}';

    protected $description = 'Post missing vendor ledger entries from commission, refund, and commission adjustment sources.';

    public function handle(
        PostVendorOrderEarnings $postVendorOrderEarnings,
        PostVendorRefundDebit $postVendorRefundDebit,
        PostVendorCommissionRefundCredit $postVendorCommissionRefundCredit,
    ): int {
        $chunk = max(1, (int) $this->option('chunk'));
        $earnings = 0;
        $refunds = 0;
        $commissionRefunds = 0;
        $failed = 0;

        VendorOrderCommission::query()
            ->with('vendorOrder')
            ->orderBy('id')
            ->chunkById($chunk, function ($commissions) use ($postVendorOrderEarnings, &$earnings, &$failed): void {
                foreach ($commissions as $commission) {
                    try {
                        $earnings += $postVendorOrderEarnings->handle($commission);
                    } catch (\Throwable $exception) {
                        $failed++;
                        Log::error('Vendor ledger earnings reconciliation failed.', [
                            'vendor_order_commission_id' => $commission->id,
                            'exception' => $exception::class,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        RefundAllocation::query()
            ->whereNotNull('vendor_order_id')
            ->whereIn('type', [RefundAllocationType::Item->value, RefundAllocationType::Shipping->value])
            ->whereHas('refund', fn ($query) => $query->where('status', RefundStatus::Succeeded->value))
            ->whereDoesntHave('ledgerEntry')
            ->orderBy('id')
            ->chunkById($chunk, function ($allocations) use ($postVendorRefundDebit, &$refunds, &$failed): void {
                foreach ($allocations as $allocation) {
                    try {
                        if ($postVendorRefundDebit->handle($allocation)) {
                            $refunds++;
                        }
                    } catch (\Throwable $exception) {
                        $failed++;
                        Log::error('Vendor ledger refund reconciliation failed.', [
                            'refund_allocation_id' => $allocation->id,
                            'exception' => $exception::class,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        CommissionAdjustment::query()
            ->where('type', CommissionAdjustmentType::Refund)
            ->where('commission_amount_delta', '<', 0)
            ->whereDoesntHave('ledgerEntry')
            ->orderBy('id')
            ->chunkById($chunk, function ($adjustments) use ($postVendorCommissionRefundCredit, &$commissionRefunds, &$failed): void {
                foreach ($adjustments as $adjustment) {
                    try {
                        if ($postVendorCommissionRefundCredit->handle($adjustment)) {
                            $commissionRefunds++;
                        }
                    } catch (\Throwable $exception) {
                        $failed++;
                        Log::error('Vendor ledger commission refund reconciliation failed.', [
                            'commission_adjustment_id' => $adjustment->id,
                            'exception' => $exception::class,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        $this->info("Posted sale/commission entries: {$earnings}");
        $this->info("Posted refund debit entries: {$refunds}");
        $this->info("Posted commission refund credit entries: {$commissionRefunds}");
        $this->info("Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
