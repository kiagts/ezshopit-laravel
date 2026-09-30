<?php

namespace Tests\Feature\VendorPayouts;

use App\Actions\VendorPayouts\ApproveVendorPayout;
use App\Actions\VendorPayouts\CancelVendorPayout;
use App\Actions\VendorPayouts\MarkVendorPayoutPaid;
use App\Actions\VendorPayouts\RequestVendorPayout;
use App\Actions\VendorPayouts\StartVendorPayoutProcessing;
use App\Enums\UserRole;
use App\Enums\VendorLedgerEntryType;
use App\Enums\VendorPayoutMethodType;
use App\Enums\VendorPayoutStatus;
use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Models\VendorPayout;
use App\Models\VendorPayoutMethod;
use App\Services\VendorPayouts\VendorPayoutBalanceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VendorPayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_can_create_bank_payout_method_and_sensitive_fields_are_encrypted(): void
    {
        $vendor = Vendor::factory()->approved()->create();

        $this->actingAs($vendor->user)->post(route('vendor.payout-methods.store'), [
            'label' => 'Primary bank',
            'account_holder_name' => 'Demo Seller',
            'bank_name' => 'Demo Bank',
            'account_number' => '1234567890',
            'iban' => 'PK36DEMO123456789000',
            'routing_number' => '987654321',
            'country_code' => 'PK',
            'is_default' => '1',
        ])->assertRedirect(route('vendor.payout-methods.index'));

        $method = VendorPayoutMethod::query()->firstOrFail();
        $raw = DB::table('vendor_payout_methods')->where('id', $method->id)->first();

        $this->assertSame($vendor->id, $method->vendor_id);
        $this->assertSame(VendorPayoutMethodType::BankTransfer, $method->type);
        $this->assertNotSame('1234567890', $raw->account_number);
        $this->assertNotSame('PK36DEMO123456789000', $raw->iban);
        $this->assertSame('****7890', $method->maskedAccountNumber());

        $this->actingAs($vendor->user)
            ->get(route('vendor.payout-methods.index'))
            ->assertOk()
            ->assertSeeText('PK36 **** **** 9000')
            ->assertDontSee('1234567890')
            ->assertDontSee('PK36DEMO123456789000');
    }

    public function test_vendor_cannot_manage_another_vendors_payout_method_and_only_one_default_exists(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $other = Vendor::factory()->approved()->create();
        $first = VendorPayoutMethod::factory()->create(['vendor_id' => $vendor->id, 'is_default' => true]);
        $second = VendorPayoutMethod::factory()->create(['vendor_id' => $vendor->id, 'is_default' => false]);
        $otherMethod = VendorPayoutMethod::factory()->create(['vendor_id' => $other->id]);

        $this->actingAs($other->user)
            ->get(route('vendor.payout-methods.edit', $first))
            ->assertNotFound();

        $this->actingAs($other->user)
            ->delete(route('vendor.payout-methods.destroy', $first))
            ->assertNotFound();

        $this->actingAs($vendor->user)
            ->post(route('vendor.payout-methods.default', $second))
            ->assertRedirect(route('vendor.payout-methods.index'));

        $this->assertTrue($second->refresh()->is_default);
        $this->assertFalse($first->refresh()->is_default);
        $this->assertSame(1, VendorPayoutMethod::query()->where('vendor_id', $vendor->id)->where('is_default', true)->count());
        $this->assertSame($other->id, $otherMethod->refresh()->vendor_id);
    }

    public function test_requestable_balance_subtracts_active_payouts_but_not_paid_rejected_or_cancelled(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $this->ledger($vendor, 100000);
        VendorPayout::factory()->forVendor($vendor)->create(['status' => VendorPayoutStatus::Pending, 'amount' => 30000, 'currency' => 'USD']);
        VendorPayout::factory()->forVendor($vendor)->create(['status' => VendorPayoutStatus::Rejected, 'amount' => 20000, 'currency' => 'USD']);
        VendorPayout::factory()->forVendor($vendor)->create(['status' => VendorPayoutStatus::Paid, 'amount' => 10000, 'currency' => 'USD']);
        $this->ledger($vendor, -10000, VendorLedgerEntryType::Payout, 'paid-payout-demo');

        $balance = app(VendorPayoutBalanceService::class)->summaryFor($vendor, 'USD');

        $this->assertSame(90000, $balance->availableLedgerBalance);
        $this->assertSame(30000, $balance->activePayoutReserved);
        $this->assertSame(60000, $balance->requestableBalance);
        $this->assertSame(10000, $balance->paidOutTotal);
    }

    public function test_vendor_can_request_payout_only_within_minimum_available_currency_and_one_active_rule(): void
    {
        config(['marketplace.minimum_vendor_payout' => 5000]);
        $vendor = Vendor::factory()->approved()->create();
        $method = VendorPayoutMethod::factory()->create(['vendor_id' => $vendor->id]);
        $this->ledger($vendor, 10000);

        $this->expectValidationException(fn () => app(RequestVendorPayout::class)->handle($vendor, $vendor->user, $method, 4999));
        $this->expectValidationException(fn () => app(RequestVendorPayout::class)->handle($vendor, $vendor->user, $method, 10001));
        $this->expectValidationException(fn () => app(RequestVendorPayout::class)->handle($vendor, $vendor->user, $method, 5000, 'EUR'));

        $payout = app(RequestVendorPayout::class)->handle($vendor, $vendor->user, $method, 7000);

        $this->assertSame(VendorPayoutStatus::Pending, $payout->status);
        $this->assertSame(7000, $payout->amount);
        $this->expectValidationException(fn () => app(RequestVendorPayout::class)->handle($vendor, $vendor->user, $method, 1000));
    }

    public function test_payout_snapshot_is_immutable_after_method_update(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $method = VendorPayoutMethod::factory()->create(['vendor_id' => $vendor->id, 'account_number' => '1111222233334444']);
        $this->ledger($vendor, 10000);

        $payout = app(RequestVendorPayout::class)->handle($vendor, $vendor->user, $method, 5000);
        $method->update(['account_number' => '9999888877776666']);

        $this->assertSame('1111222233334444', $payout->refresh()->destination_snapshot['account_number']);
        $this->assertSame('****4444', $payout->maskedDestinationSnapshot());
    }

    public function test_vendor_can_cancel_pending_payout_but_not_approved_payout_and_reservation_is_released(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $method = VendorPayoutMethod::factory()->create(['vendor_id' => $vendor->id]);
        $this->ledger($vendor, 10000);
        $payout = app(RequestVendorPayout::class)->handle($vendor, $vendor->user, $method, 5000);

        app(CancelVendorPayout::class)->handle($payout, $vendor->user);

        $this->assertSame(VendorPayoutStatus::Cancelled, $payout->refresh()->status);
        $this->assertSame(10000, app(VendorPayoutBalanceService::class)->summaryFor($vendor, 'USD')->requestableBalance);
        $this->assertDatabaseMissing('vendor_ledger_entries', ['vendor_payout_id' => $payout->id]);

        $approved = app(RequestVendorPayout::class)->handle($vendor, $vendor->user, $method, 5000);
        app(ApproveVendorPayout::class)->handle($approved, User::factory()->admin()->create());

        $this->actingAs($vendor->user)
            ->post(route('vendor.payouts.cancel', $approved))
            ->assertSessionHasErrors();
    }

    public function test_admin_lifecycle_creates_exactly_one_payout_ledger_debit_and_keeps_amount_immutable(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $admin = User::factory()->admin()->create();
        $method = VendorPayoutMethod::factory()->create(['vendor_id' => $vendor->id]);
        $this->ledger($vendor, 20000);
        $payout = app(RequestVendorPayout::class)->handle($vendor, $vendor->user, $method, 10000);

        $this->expectValidationException(fn () => app(MarkVendorPayoutPaid::class)->handle($payout, $admin));

        app(ApproveVendorPayout::class)->handle($payout, $admin);
        app(StartVendorPayoutProcessing::class)->handle($payout->refresh(), $admin);
        app(MarkVendorPayoutPaid::class)->handle($payout->refresh(), $admin, 'bank-ref-1');
        $paidAt = $payout->refresh()->paid_at;
        app(MarkVendorPayoutPaid::class)->handle($payout->refresh(), $admin, 'bank-ref-2');

        $this->assertSame(VendorPayoutStatus::Paid, $payout->refresh()->status);
        $this->assertTrue($paidAt->equalTo($payout->paid_at));
        $this->assertSame(1, VendorLedgerEntry::query()->where('vendor_payout_id', $payout->id)->count());
        $this->assertDatabaseHas('vendor_ledger_entries', [
            'vendor_payout_id' => $payout->id,
            'source_key' => 'vendor-payout:'.$payout->id.':payout',
            'type' => VendorLedgerEntryType::Payout->value,
            'amount' => -10000,
        ]);
    }

    public function test_admin_reject_and_cancel_release_reservations_without_ledger_entries(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $admin = User::factory()->admin()->create();
        $method = VendorPayoutMethod::factory()->create(['vendor_id' => $vendor->id]);
        $this->ledger($vendor, 20000);

        $rejected = app(RequestVendorPayout::class)->handle($vendor, $vendor->user, $method, 5000);
        app(\App\Actions\VendorPayouts\RejectVendorPayout::class)->handle($rejected, $admin, 'Invalid bank details.');
        $cancelled = app(RequestVendorPayout::class)->handle($vendor, $vendor->user, $method, 5000);
        app(ApproveVendorPayout::class)->handle($cancelled, $admin);
        app(CancelVendorPayout::class)->handle($cancelled->refresh(), $admin, 'Cancelled before transfer.');

        $this->assertSame(20000, app(VendorPayoutBalanceService::class)->summaryFor($vendor, 'USD')->requestableBalance);
        $this->assertDatabaseMissing('vendor_ledger_entries', ['vendor_payout_id' => $rejected->id]);
        $this->assertDatabaseMissing('vendor_ledger_entries', ['vendor_payout_id' => $cancelled->id]);
    }

    public function test_payout_can_result_in_negative_available_balance_after_later_refund(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $admin = User::factory()->admin()->create();
        $method = VendorPayoutMethod::factory()->create(['vendor_id' => $vendor->id]);
        $this->ledger($vendor, 10000);
        $payout = app(RequestVendorPayout::class)->handle($vendor, $vendor->user, $method, 10000);


        app(ApproveVendorPayout::class)->handle($payout, $admin);
        app(StartVendorPayoutProcessing::class)->handle($payout->refresh(), $admin);
        app(MarkVendorPayoutPaid::class)->handle($payout->refresh(), $admin);

        $this->ledger($vendor, -3000, VendorLedgerEntryType::Refund, 'later-refund');
        $this->assertSame(-3000, app(VendorPayoutBalanceService::class)->summaryFor($vendor, 'USD')->availableLedgerBalance);
        $this->assertSame(0, app(VendorPayoutBalanceService::class)->summaryFor($vendor, 'USD')->requestableBalance);
    }

    public function test_payout_routes_are_authorized_and_scoped(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        $other = Vendor::factory()->approved()->create();
        $customer = User::factory()->customer()->create();
        $admin = User::factory()->admin()->create();
        $payout = VendorPayout::factory()->forVendor($vendor)->create();

        $this->get(route('vendor.payouts.index'))->assertRedirect(route('login'));
        $this->actingAs($customer)->get(route('vendor.payouts.index'))->assertForbidden();
        $this->actingAs($other->user)->get(route('vendor.payouts.show', $payout))->assertNotFound();
        $this->actingAs($vendor->user)->get(route('vendor.payouts.show', $payout))->assertOk();
        $this->actingAs($vendor->user)->post(route('admin.payouts.approve', $payout))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.payouts.show', $payout))->assertOk();
    }

    public function test_suspended_vendor_cannot_request_new_payout(): void
    {
        $vendor = Vendor::factory()->approved()->create(['status' => VendorStatus::Suspended]);
        $method = VendorPayoutMethod::factory()->create(['vendor_id' => $vendor->id]);
        $this->ledger($vendor, 10000);

        $this->actingAs($vendor->user)
            ->post(route('vendor.payouts.store'), ['amount' => '50.00', 'payout_method_id' => $method->id])
            ->assertRedirect(route('vendor.application-status'));

        $this->assertDatabaseCount('vendor_payouts', 0);
    }

    public function test_reconciliation_repairs_paid_payout_missing_ledger_debit_once_and_seeder_is_repeatable(): void
    {
        $payout = VendorPayout::factory()->paid()->create();

        $this->artisan('vendor-payouts:reconcile')->assertSuccessful();
        $this->artisan('vendor-payouts:reconcile')->assertSuccessful();

        $this->assertSame(1, VendorLedgerEntry::query()->where('vendor_payout_id', $payout->id)->count());

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(3, VendorPayout::query()->where('payout_number', 'like', 'PAY-DEMO-%')->count());
        $this->assertSame(1, VendorLedgerEntry::query()
            ->whereHas('vendorPayout', fn ($query) => $query->where('payout_number', 'PAY-DEMO-PAID-001'))
            ->count());
    }

    private function ledger(Vendor $vendor, int $amount, VendorLedgerEntryType $type = VendorLedgerEntryType::Sale, ?string $key = null, string $currency = 'USD'): VendorLedgerEntry
    {
        return VendorLedgerEntry::query()->forceCreate([
            'vendor_id' => $vendor->id,
            'source_key' => $key ?: 'test-ledger:'.$vendor->id.':'.uniqid(),
            'type' => $type,
            'amount' => $amount,
            'currency' => $currency,
            'available_at' => now()->subMinute(),
            'description' => 'Test ledger entry.',
        ]);
    }

    private function expectValidationException(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected validation exception was not thrown.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }
}


