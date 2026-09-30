<?php

namespace App\Actions\VendorPayouts;

use App\Enums\VendorPayoutStatus;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayout;
use App\Services\Notifications\MarketplaceNotifier;
use App\Services\VendorPayouts\VendorPayoutBalanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkVendorPayoutPaid
{
    public function __construct(
        private readonly PostVendorPayoutLedgerDebit $postDebit,
        private readonly MarketplaceNotifier $notifier,
    ) {}

    public function handle(VendorPayout $payout, User $admin, ?string $externalReference = null, ?string $internalNotes = null): VendorPayout
    {
        $payout = DB::transaction(function () use ($payout, $admin, $externalReference, $internalNotes): VendorPayout {
            $vendor = Vendor::query()->whereKey($payout->vendor_id)->lockForUpdate()->firstOrFail();
            $payout = VendorPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if ($payout->status === VendorPayoutStatus::Paid) {
                $this->postDebit->handle($payout);

                return $payout;
            }

            if ($payout->status !== VendorPayoutStatus::Processing) {
                throw ValidationException::withMessages(['payout' => 'Only processing payouts can be marked paid.']);
            }

            $paidAt = now();
            // Refunds may have reduced funds since this reservation was approved.
            // The vendor lock is also used by all authoritative ledger writers.
            $balance = app(VendorPayoutBalanceService::class)->summaryFor($vendor, $payout->currency);
            $otherReservations = max(0, $balance->activePayoutReserved - $payout->amount);
            if ($payout->amount > $balance->availableLedgerBalance - $otherReservations) {
                throw ValidationException::withMessages(['payout' => 'Available funds changed. Reconcile or cancel this payout before marking it paid.']);
            }

            $payout->forceFill([
                'status' => VendorPayoutStatus::Paid,
                'paid_at' => $paidAt,
                'paid_by' => $admin->id,
                'external_reference' => $externalReference,
                'internal_notes' => $internalNotes,
            ]);

            $this->postDebit->handle($payout);
            $payout->save();

            return $payout;
        });

        $this->notifier->payoutStatusChanged($payout, VendorPayoutStatus::Paid);

        return $payout;
    }
}
