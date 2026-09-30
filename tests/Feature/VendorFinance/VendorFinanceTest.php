<?php

namespace Tests\Feature\VendorFinance;

use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Services\VendorFinance\FinanceMoney;
use App\Services\VendorFinance\VendorFinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorFinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_running_balance_precedes_filters_and_is_currency_and_vendor_scoped(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        VendorLedgerEntry::factory()->create(['vendor_id' => $vendor->id, 'currency' => 'USD', 'amount' => 10000, 'type' => 'sale', 'created_at' => '2026-01-01 10:00:00']);
        VendorLedgerEntry::factory()->create(['vendor_id' => $vendor->id, 'currency' => 'USD', 'amount' => -2000, 'type' => 'commission', 'created_at' => '2026-01-02 10:00:00']);
        VendorLedgerEntry::factory()->create(['vendor_id' => $vendor->id, 'currency' => 'PKR', 'amount' => 900000]);
        VendorLedgerEntry::factory()->create(['currency' => 'USD', 'amount' => 999999]);
        $service = app(VendorFinanceService::class);
        $rows = $service->transactions($vendor, 'USD', ['type' => 'commission'])->get();
        $this->assertCount(1, $rows);
        $this->assertSame(8000, (int) $rows->first()->running_balance);
        $this->assertSame(['opening' => 10000, 'period_activity' => -2000, 'closing' => 8000, 'filtered_activity' => -2000], $service->statement($vendor, 'USD', ['date_from' => '2026-01-02', 'date_to' => '2026-01-02']));
    }

    public function test_finance_pages_render_for_vendor_and_block_customer(): void
    {
        $vendor = Vendor::factory()->approved()->create();
        foreach (['index', 'transactions', 'earnings', 'statements'] as $page) {
            $this->actingAs($vendor->user)->get(route('vendor.finance.'.$page))->assertOk();
        }
        $this->actingAs(User::factory()->create())->get(route('vendor.finance.index'))->assertForbidden();
    }

    public function test_csv_formula_protection_and_integer_currency_parsing(): void
    {
        $service = app(VendorFinanceService::class);
        foreach (['=SUM(A1)', '+cmd', '-cmd', '@SUM(A1)', "\t=cmd"] as $value) {
            $this->assertSame("'".$value, $service->csvText($value));
        }
        $this->assertSame('ORD-123', $service->csvText('ORD-123'));
        $this->assertSame(12345, app(FinanceMoney::class)->parse('123.45', 2));
        $this->assertSame(123, app(FinanceMoney::class)->parse('123',0));
    }
 public function test_reconciliation_is_read_only_and_detects_currency_corruption():void {
  $commission=\App\Models\VendorOrderCommission::factory()->create();
  app(\App\Actions\VendorLedger\PostVendorOrderEarnings::class)->handle($commission);
  $vendor=$commission->vendorOrder->vendor;
  $service=app(\App\Services\VendorFinance\VendorFinancialReconciliationService::class);
  $before=\Illuminate\Support\Facades\DB::table('vendor_ledger_entries')->get()->toJson();
  $this->assertSame('healthy',$service->inspect($vendor,$commission->currency)['status']);
  $this->assertSame($before,\Illuminate\Support\Facades\DB::table('vendor_ledger_entries')->get()->toJson());
  \Illuminate\Support\Facades\DB::table('vendor_ledger_entries')->where('type','sale')->update(['currency'=>'PKR']);
  $this->assertSame('error',$service->inspect($vendor,'PKR')['status']);
  $this->assertSame('error',$service->inspect($vendor,$commission->currency)['status']);
 }
 public function test_refund_before_payment_blocks_payout_and_preserves_reservation():void {
  $vendor=Vendor::factory()->approved()->create();$admin=\App\Models\User::factory()->admin()->create();
  $payout=\App\Models\VendorPayout::factory()->forVendor($vendor)->processing()->create(['amount'=>10000,'currency'=>'USD']);
  VendorLedgerEntry::factory()->create(['vendor_id'=>$vendor->id,'currency'=>'USD','amount'=>7000,'available_at'=>now()->subDay()]);
  try {app(\App\Actions\VendorPayouts\MarkVendorPayoutPaid::class)->handle($payout,$admin);$this->fail('Insufficient funds must block payment');}
  catch(\Illuminate\Validation\ValidationException $e){$this->assertArrayHasKey('payout',$e->errors());}
  $this->assertSame(\App\Enums\VendorPayoutStatus::Processing,$payout->refresh()->status);
  $this->assertFalse(VendorLedgerEntry::where('vendor_payout_id',$payout->id)->exists());
 } public function test_order_breakdown_and_exports_are_scoped_and_historical():void {
  $commission=\App\Models\VendorOrderCommission::factory()->create();$vo=$commission->vendorOrder;$vendor=$vo->vendor;
  app(\App\Actions\VendorLedger\PostVendorOrderEarnings::class)->handle($commission);
  $this->actingAs($vendor->user)->get(route('vendor.finance.order',$vo))->assertOk()->assertSeeText($vo->vendor_order_number);
  $response=$this->get(route('vendor.finance.export',['currency'=>$commission->currency]));$response->assertOk();
  $this->assertStringContainsString($vo->vendor_order_number,$response->streamedContent());
  $other=Vendor::factory()->approved()->create();
  $this->actingAs($other->user)->get(route('vendor.finance.order',$vo))->assertForbidden();
  $this->get(route('admin.finance.index',$vendor))->assertForbidden();
  $admin=User::factory()->admin()->create();
  $this->actingAs($admin)->get(route('admin.finance.reconcile',$vendor))->assertOk();
 }
 public function test_ledger_model_and_bulk_writes_cannot_edit_or_delete_history():void {
  $entry=VendorLedgerEntry::factory()->create();
  foreach([fn()=>$entry->forceFill(['amount'=>1])->save(),fn()=>$entry->delete(),fn()=>VendorLedgerEntry::whereKey($entry->id)->update(['amount'=>1]),fn()=>VendorLedgerEntry::whereKey($entry->id)->delete()] as $write){
   try{$write();$this->fail('History mutation allowed');}catch(\LogicException $e){$this->assertStringContainsString('immutable',$e->getMessage());}
  }
  $this->assertDatabaseHas('vendor_ledger_entries',['id'=>$entry->id]);
 }
 public function test_invalid_filters_do_not_execute_arbitrary_queries():void {
  $vendor=Vendor::factory()->approved()->create();
  foreach([['currency'=>['USD']],['type'=>'DROP TABLE'],['date_from'=>'bad'],['date_from'=>'2026-02-01','date_to'=>'2026-01-01'],['reference'=>str_repeat('a',101)]] as $query)$this->actingAs($vendor->user)->getJson(route('vendor.finance.transactions',$query))->assertUnprocessable();
 }}



