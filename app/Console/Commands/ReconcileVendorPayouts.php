<?php

namespace App\Console\Commands;

use App\Actions\VendorPayouts\PostVendorPayoutLedgerDebit;
use App\Enums\VendorLedgerEntryType;
use App\Enums\VendorPayoutStatus;
use App\Models\VendorLedgerEntry;
use App\Models\VendorPayout;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileVendorPayouts extends Command
{
    protected $signature = 'vendor-payouts:reconcile {--chunk=100}';

    protected $description = 'Repair missing vendor ledger debit entries for already-paid vendor payouts.';

    public function handle(PostVendorPayoutLedgerDebit $postDebit): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $posted = 0;
        $duplicates = 0;
        $failed = 0;

        VendorPayout::query()
            ->where('status', VendorPayoutStatus::Paid)
            ->whereDoesntHave('ledgerEntry')
            ->orderBy('id')
            ->chunkById($chunk, function ($payouts) use ($postDebit, &$posted, &$failed): void {
                foreach ($payouts as $payout) {
                    try {
                        $postDebit->handle($payout);
                        $posted++;
                    } catch (\Throwable $exception) {
                        $failed++;
                        Log::error('Vendor payout reconciliation failed.', [
                            'vendor_payout_id' => $payout->id,
                            'payout_number' => $payout->payout_number,
                            'exception' => $exception::class,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        VendorLedgerEntry::query()
            ->selectRaw('vendor_payout_id, COUNT(*) as aggregate')
            ->whereNotNull('vendor_payout_id')
            ->where('type', VendorLedgerEntryType::Payout)
            ->groupBy('vendor_payout_id')
            ->having('aggregate', '>', 1)
            ->get()
            ->each(function ($row) use (&$duplicates): void {
                $duplicates += (int) $row->aggregate;
            });

        $this->info("Posted missing payout debit entries: {$posted}");
        $this->info("Duplicate payout debit rows detected: {$duplicates}");
        $this->info("Failed: {$failed}");

        return $failed > 0 || $duplicates > 0 ? self::FAILURE : self::SUCCESS;
    }
}
