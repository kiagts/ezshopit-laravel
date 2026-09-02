<?php

namespace Database\Seeders;

use App\Actions\VendorPayouts\PostVendorPayoutLedgerDebit;
use App\Enums\VendorPayoutMethodType;
use App\Enums\VendorPayoutStatus;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayout;
use App\Models\VendorPayoutMethod;
use Illuminate\Database\Seeder;

class VendorPayoutSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->first();

        $paidVendor = Vendor::query()->where('slug', 'techverse')->first();
        $pendingVendor = Vendor::query()->where('slug', 'lifecycle-goods')->first();
        $rejectedVendor = Vendor::query()->where('slug', 'supplynest')->first();

        if ($paidVendor) {
            $method = $this->method($paidVendor, 'TechVerse Demo Bank', 'TV-DEMO-00001234');
            $payout = $this->payout($paidVendor, $method, 'PAY-DEMO-PAID-001', VendorPayoutStatus::Paid, 5000, [
                'approved_at' => now()->subDays(5),
                'processing_at' => now()->subDays(4),
                'paid_at' => now()->subDays(3),
                'approved_by' => $admin?->id,
                'processed_by' => $admin?->id,
                'paid_by' => $admin?->id,
                'external_reference' => 'DEMO-BANK-TRANSFER-001',
                'internal_notes' => 'Seeded paid payout for ledger reconciliation demos.',
            ]);

            app(PostVendorPayoutLedgerDebit::class)->handle($payout);
        }

        if ($pendingVendor) {
            $method = $this->method($pendingVendor, 'Lifecycle Goods Demo Bank', 'LG-DEMO-00005678');
            $this->payout($pendingVendor, $method, 'PAY-DEMO-PENDING-001', VendorPayoutStatus::Pending, 5000);
        }

        if ($rejectedVendor) {
            $method = $this->method($rejectedVendor, 'SupplyNest Demo Bank', 'SN-DEMO-00004321');
            $this->payout($rejectedVendor, $method, 'PAY-DEMO-REJECTED-001', VendorPayoutStatus::Rejected, 5000, [
                'rejected_at' => now()->subDay(),
                'rejected_by' => $admin?->id,
                'rejection_reason' => 'Seeded rejection example with no ledger debit.',
            ]);
        }
    }

    private function method(Vendor $vendor, string $label, string $accountNumber): VendorPayoutMethod
    {
        $method = VendorPayoutMethod::query()->firstOrNew([
            'vendor_id' => $vendor->id,
            'label' => $label,
        ]);

        $method->fill([
            'type' => VendorPayoutMethodType::BankTransfer,
            'account_holder_name' => $vendor->business_name,
            'bank_name' => 'Demo Bank',
            'account_number' => $accountNumber,
            'iban' => 'PK36DEMO0000000012345678',
            'swift_code' => 'DEMOUS33',
            'routing_number' => '000111222',
            'country_code' => 'PK',
            'is_default' => true,
        ])->save();

        $vendor->payoutMethods()
            ->whereKeyNot($method->id)
            ->update(['is_default' => false]);

        return $method;
    }

    private function payout(Vendor $vendor, VendorPayoutMethod $method, string $number, VendorPayoutStatus $status, int $amount, array $attributes = []): VendorPayout
    {
        $payout = VendorPayout::query()->firstOrNew(['payout_number' => $number]);
        $requestedAt = now()->subDays(match ($status) {
            VendorPayoutStatus::Paid => 6,
            VendorPayoutStatus::Rejected => 2,
            default => 1,
        });

        $payout->forceFill([
            'vendor_id' => $vendor->id,
            'status' => $status,
            'amount' => $amount,
            'currency' => 'USD',
            'payout_method_id' => $method->id,
            'payout_method_type' => $method->type->value,
            'destination_snapshot' => $method->destinationSnapshot(),
            'requested_at' => $requestedAt,
            'requested_by' => $vendor->user_id,
            ...$attributes,
        ])->save();

        return $payout;
    }
}
