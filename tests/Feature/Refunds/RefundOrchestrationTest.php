<?php

namespace Tests\Feature\Refunds;

use App\Actions\Commissions\RecognizeVendorOrderCommission;
use App\Actions\Refunds\MarkRefundFailed;
use App\Actions\Refunds\MarkRefundSucceeded;
use App\Enums\GiftCardStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\ReturnStatus;
use App\Enums\VendorOrderStatus;
use App\Models\CommissionAdjustment;
use App\Models\CommissionRule;
use App\Models\Currency;
use App\Models\GiftCardTransaction;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\RefundAllocation;
use App\Models\RefundFundingAllocation;
use App\Models\ReturnRequest;
use App\Models\TaxClass;
use App\Models\TaxRule;
use App\Models\User;
use App\Models\VendorLedgerEntry;
use App\Models\VendorOrder;
use App\Models\VendorPayout;
use App\Models\WalletTransaction;
use App\Services\GiftCardService;
use App\Services\Refunds\ExternalRefundGateway;
use App\Services\Refunds\RefundService;
use App\Services\Returns\ReturnWorkflowService;
use App\Services\VendorLedger\VendorEarningsService;
use App\Services\WalletService;
use Database\Seeders\RefundWorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RefundOrchestrationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(int $gift = 3000, int $wallet = 2000, int $gross = 10000, int $discount = 0, int $tax = 0, int $shipping = 0): array
    {
        $customer = User::factory()->customer()->create();
        $admin = User::factory()->admin()->create();
        $total = $gross - $discount + $tax + $shipping;
        $order = Order::factory()->create(['user_id' => $customer->id, 'currency' => 'USD', 'items_subtotal' => $gross, 'discount_total' => $discount, 'tax_total' => $tax, 'shipping_total' => $shipping, 'grand_total' => $total]);
        $vo = VendorOrder::factory()->create(['order_id' => $order->id, 'items_subtotal' => $gross, 'discount_total' => $discount, 'vendor_discount_total' => $discount, 'tax_total' => $tax, 'shipping_total' => $shipping, 'grand_total' => $total, 'status' => VendorOrderStatus::Delivered, 'delivered_at' => now()->subDays(2)]);
        $item = OrderItem::factory()->create(['vendor_order_id' => $vo->id, 'quantity' => 3, 'unit_price' => intdiv($gross, 3), 'line_total' => $gross, 'discount_total' => $discount, 'vendor_discount_total' => $discount, 'net_line_total' => $gross - $discount]);
        $card = null;
        $w = null;
        if ($gift) {
            [$card,$code] = app(GiftCardService::class)->issue($gift, 'USD');
            app(GiftCardService::class)->redeem($order, $code, $gift);
            $order->update(['gift_card_applied_total' => $gift, 'gift_card_last_four' => $card->code_last_four]);
        }
        if ($wallet) {
            $w = app(WalletService::class)->wallet($customer, 'USD');
            app(WalletService::class)->credit($w, $wallet, 'Opening credit', 'opening:'.$order->id);
            DB::transaction(fn () => app(WalletService::class)->applyToOrder($order, true));
        }
        $payment = Payment::factory()->create(['order_id' => $order->id, 'status' => PaymentStatus::Paid, 'currency' => 'USD', 'amount' => $total - $gift - $wallet]);
        $order->update(['payment_status' => PaymentStatus::Paid]);

        return [$order, $item, $admin, $card, $w, $vo, $payment];
    }

    private function refund(array $fixture, int $qty = 3, ?string $key = null, array $extra = []): Refund
    {
        return app(RefundService::class)->create($fixture[0], $fixture[2], $extra + [
            'key' => $key ?? (string) Str::uuid(), 'reason' => 'Historical refund review', 'items' => [['id' => $fixture[1]->id, 'quantity' => $qty]],
        ]);
    }

    public function test_historical_price_discount_and_tax_ignore_current_rules(): void
    {
        $f = $this->fixture(0, 0, 10000, 2000, 901);
        $f[1]->variant->update(['base_price' => 990000, 'sale_price' => null]);
        $taxClass = TaxClass::create(['name' => 'Historical test', 'code' => 'historical-test', 'is_active' => true]);
        $rule = TaxRule::create(['tax_class_id' => $taxClass->id, 'country_code' => 'US', 'rate_basis_points' => 901, 'is_active' => true]);
        $rule->update(['rate_basis_points' => 9900]);
        $refund = $this->refund($f);
        $a = $refund->allocations()->first();
        $this->assertSame(8000, $a->merchandise_amount);
        $this->assertSame(2000, $a->discount_amount);
        $this->assertSame(901, $a->tax_amount);
        $this->assertSame(8901, $refund->amount);
        $this->assertSame('USD', $refund->currency);
    }

    public function test_partial_rounding_reconciles_last_unit_and_caps_quantity(): void
    {
        $f = $this->fixture(0, 0, 10000, 2001, 902);
        foreach (range(1, 3) as $i) {
            $this->refund($f, 1);
        }
        $this->assertSame(10000, (int) RefundAllocation::sum('gross_amount'));
        $this->assertSame(2001, (int) RefundAllocation::sum('discount_amount'));
        $this->assertSame(902, (int) RefundAllocation::sum('tax_amount'));
        $this->assertSame(8901, (int) Refund::sum('amount'));
        $this->expectException(ValidationException::class);
        $this->refund($f, 1);
    }

    public function test_shipping_is_explicit_and_capped(): void
    {
        $f = $this->fixture(0, 0, 10000, 0, 0, 500);
        $r = $this->refund($f, 1);
        $this->assertSame(0, $r->allocations()->where('type', 'shipping')->count());
        $r = $this->refund($f, 1, null, ['shipping' => [['vendor_order_id' => $f[5]->id, 'amount' => 500]]]);
        $this->assertSame(500, (int) $r->allocations()->where('type', 'shipping')->sum('amount'));
        $this->expectException(ValidationException::class);
        $this->refund($f, 1, null, ['shipping' => [['vendor_order_id' => $f[5]->id, 'amount' => 1]]]);
    }

    public function test_split_priority_and_internal_completion(): void
    {
        $f = $this->fixture();
        $r = app(RefundService::class)->create($f[0], $f[2], ['key' => 'partial', 'reason' => 'Partial adjustment', 'items' => [], 'adjustment' => 4000]);
        $this->assertSame(['gift_card' => 3000, 'wallet' => 1000], $r->fundingAllocations()->pluck('amount', 'source_type')->all());
        app(RefundService::class)->process($r, $f[2]);
        $this->assertSame(RefundStatus::Succeeded, $r->fresh()->status);
        $this->assertSame(3000, $f[3]->fresh()->balance());
        $this->assertSame(1000, app(WalletService::class)->balance($f[4]));
        $this->assertSame(PaymentStatus::Paid, $f[6]->fresh()->status);
    }

    public function test_full_split_leaves_external_pending_and_payment_unchanged(): void
    {
        $f = $this->fixture();
        $r = $this->refund($f);
        $stale = $r->fresh();
        $before = $f[6]->fresh()->getRawOriginal();
        app(RefundService::class)->process($r, $f[2]);
        $this->assertSame(RefundStatus::PartiallyProcessed, $r->fresh()->status);
        $this->assertNull($r->fresh()->processed_at);
        $this->assertSame(5000, (int) $r->fundingAllocations()->where('status', 'pending_provider')->sum('amount'));
        $this->assertSame($before, $f[6]->fresh()->getRawOriginal());
        app(MarkRefundFailed::class)->handle($stale, 'late-failure');
        $this->assertSame(RefundStatus::PartiallyProcessed, $r->fresh()->status);
        $this->expectException(ValidationException::class);
        app(MarkRefundSucceeded::class)->handle($r, 'fake');
    }

    public function test_duplicate_creation_and_processing_are_idempotent(): void
    {
        $f = $this->fixture(3000, 7000);
        $r = $this->refund($f, 3, 'repeat');
        $this->assertSame($r->id, $this->refund($f, 3, 'repeat')->id);
        app(RefundService::class)->process($r, $f[2]);
        $counts = [RefundFundingAllocation::count(), GiftCardTransaction::count(), WalletTransaction::count(), VendorLedgerEntry::count()];
        app(RefundService::class)->process($r, $f[2]);
        $this->assertSame($counts, [RefundFundingAllocation::count(), GiftCardTransaction::count(), WalletTransaction::count(), VendorLedgerEntry::count()]);
        $this->assertSame(PaymentStatus::Refunded, $f[6]->fresh()->status);
        $this->expectException(ValidationException::class);
        $this->refund($f, 1, 'repeat');
    }

    public function test_prior_direct_gift_credit_reduces_source_capacity_and_disabled_card_can_receive(): void
    {
        $f = $this->fixture();
        app(GiftCardService::class)->refundToGiftCard($f[0], $f[3], 2000, 'older-credit');
        $f[3]->update(['status' => GiftCardStatus::Disabled, 'expires_at' => now()->subDay()]);
        $r = app(RefundService::class)->create($f[0], $f[2], ['key' => 'remaining', 'reason' => 'Remaining refund', 'items' => [], 'adjustment' => 1000]);
        app(RefundService::class)->process($r, $f[2]);
        $this->assertSame(3000, $f[3]->fresh()->balance());
        $this->assertFalse($f[3]->fresh()->usable());
        $next = app(RefundService::class)->create($f[0], $f[2], ['key' => 'next', 'reason' => 'Next source refund', 'items' => [], 'adjustment' => 1000]);
        $this->assertSame('wallet', $next->fundingAllocations()->first()->source_type->value);
    }

    public function test_cap_and_unknown_funding_are_rejected(): void
    {
        $f = $this->fixture();
        $this->refund($f);
        try {
            app(RefundService::class)->create($f[0], $f[2], ['key' => 'overflow', 'reason' => 'Over refund attempt', 'items' => [], 'adjustment' => 1]);
            $this->fail();
        } catch (ValidationException) {
            $this->assertDatabaseCount('refunds', 1);
        }
        $g = $this->fixture(0, 0);
        $g[0]->update(['gift_card_applied_total' => 100]);
        $this->expectException(ValidationException::class);
        $this->refund($g);
    }

    public function test_commission_ledger_and_payout_history_are_preserved(): void
    {
        config(['marketplace.vendor_earnings_hold_days' => 0]);
        CommissionRule::factory()->create(['scope' => 'global', 'is_active' => true, 'rate_basis_points' => 1000]);
        $f = $this->fixture(0, 0);
        $commission = app(RecognizeVendorOrderCommission::class)->handle($f[5]);
        $original = $commission->fresh()->getRawOriginal();
        $sale = VendorLedgerEntry::where('type', 'sale')->where('vendor_order_id', $f[5]->id)->firstOrFail();
        $saleOriginal = $sale->getRawOriginal();
        $payout = VendorPayout::factory()->forVendor($f[5]->vendor)->paid()->create(['amount' => 9000]);
        $payoutOriginal = $payout->fresh()->getRawOriginal();
        VendorLedgerEntry::forceCreate(['vendor_id' => $f[5]->vendor_id, 'source_key' => 'test-paid-payout', 'type' => 'payout', 'amount' => -9000, 'currency' => 'USD', 'available_at' => now(), 'description' => 'Paid payout']);
        for ($i = 0; $i < 3; $i++) {
            $r = $this->refund($f, 1);
            app(RefundService::class)->process($r, $f[2]);
        }
        $this->assertSame($original, $commission->fresh()->getRawOriginal());
        $this->assertSame($saleOriginal, $sale->fresh()->getRawOriginal());
        $this->assertSame($payoutOriginal, $payout->fresh()->getRawOriginal());
        $this->assertSame(-$commission->original_commission_amount, (int) $commission->adjustments()->sum('commission_amount_delta'));
        $this->assertSame(-10000, (int) VendorLedgerEntry::where('type', 'refund')->sum('amount'));
        $balance = app(VendorEarningsService::class)->summaryFor($f[5]->vendor, 'USD');
        $this->assertLessThan(0, $balance->availableBalance);
    }

    public function test_refund_failure_rolls_back_internal_credits_and_economics(): void
    {
        $f = $this->fixture();
        $r = $this->refund($f);
        $this->mock(ExternalRefundGateway::class)->shouldReceive('request')->once()->andThrow(new \RuntimeException('unavailable'));
        try {
            app(RefundService::class)->process($r, $f[2]);
            $this->fail();
        } catch (\RuntimeException) {
            $this->assertSame(0, $f[3]->fresh()->balance());
            $this->assertSame(0, app(WalletService::class)->balance($f[4]));
            $this->assertSame(RefundStatus::Pending, $r->fresh()->status);
            $this->assertSame(0, VendorLedgerEntry::count());
        }
    }

    public function test_other_vendor_and_currency_are_untouched_and_pre_payout_balance_is_reduced(): void
    {
        config(['marketplace.vendor_earnings_hold_days' => 0]);
        CommissionRule::factory()->create(['scope' => 'global', 'is_active' => true, 'rate_basis_points' => 1000]);
        $f = $this->fixture(3000, 7000);
        $other = VendorOrder::factory()->create(['order_id' => $f[0]->id, 'items_subtotal' => 1000, 'grand_total' => 1000, 'shipping_total' => 0, 'status' => VendorOrderStatus::Delivered, 'delivered_at' => now()->subDays(2)]);
        OrderItem::factory()->create(['vendor_order_id' => $other->id, 'line_total' => 1000, 'net_line_total' => 1000]);
        $f[0]->update(['items_subtotal' => 11000, 'grand_total' => 11000]);
        $f[6]->update(['amount' => 1000]);
        app(RecognizeVendorOrderCommission::class)->handle($f[5]);
        app(RecognizeVendorOrderCommission::class)->handle($other);
        $before = VendorLedgerEntry::where('vendor_id', $other->vendor_id)->get()->map->getRawOriginal()->all();
        Currency::create(['code' => 'EUR', 'name' => 'Euro', 'symbol' => 'EUR', 'minor_unit' => 2, 'is_active' => true]);
        $eur = app(WalletService::class)->wallet($f[0]->user, 'EUR');
        app(WalletService::class)->credit($eur, 500, 'Other currency', 'eur-credit');
        session(['currency_code' => 'EUR']);
        $r = $this->refund($f, 1);
        app(RefundService::class)->process($r, $f[2]);
        $this->assertSame('USD', $r->fresh()->currency);
        $this->assertSame(500, app(WalletService::class)->balance($eur));
        $this->assertSame($before, VendorLedgerEntry::where('vendor_id', $other->vendor_id)->get()->map->getRawOriginal()->all());
        $summary = app(VendorEarningsService::class)->summaryFor($f[5]->vendor, 'USD');
        $this->assertSame(6000, $summary->availableBalance);
        $counts = [VendorLedgerEntry::count(), CommissionAdjustment::count()];
        app(RefundService::class)->process($r, $f[2]);
        $this->assertSame($counts, [VendorLedgerEntry::count(), CommissionAdjustment::count()]);
    }

    public function test_demo_seeder_uses_workflows_and_is_repeatable(): void
    {
        $f = $this->fixture();
        $return = $this->returnFor($f);
        $return->update(['notes' => 'Operational return demo: refund_pending']);
        $this->seed(RefundWorkflowSeeder::class);
        $counts = [Refund::count(), RefundFundingAllocation::count(), VendorLedgerEntry::count(), InventoryMovement::count()];
        $this->seed(RefundWorkflowSeeder::class);
        $this->assertSame($counts, [Refund::count(), RefundFundingAllocation::count(), VendorLedgerEntry::count(), InventoryMovement::count()]);
        $refund = Refund::sole();
        $this->assertSame($refund->amount, (int) $refund->fundingAllocations()->sum('amount'));
        $this->assertSame(ReturnStatus::Completed, $return->fresh()->status);
    }

    public function test_named_financial_demo_examples_reconcile_and_do_not_duplicate(): void
    {
        $f = $this->fixture();
        $f[0]->update(['order_number' => 'DEMO-LIFE-REFUND-TEST']);
        $this->seed(RefundWorkflowSeeder::class);
        $counts = [Order::count(), Refund::count(), RefundFundingAllocation::count(), WalletTransaction::count(), GiftCardTransaction::count()];
        $this->seed(RefundWorkflowSeeder::class);
        $this->assertSame($counts, [Order::count(), Refund::count(), RefundFundingAllocation::count(), WalletTransaction::count(), GiftCardTransaction::count()]);
        $this->assertSame(4, Refund::where('idempotency_key', 'like', 'demo-financial:%')->count());
        $this->assertSame(RefundStatus::Pending, Refund::where('idempotency_key', 'demo-financial:pending')->sole()->status);
        $this->assertSame(RefundStatus::Succeeded, Refund::where('idempotency_key', 'demo-financial:internal')->sole()->status);
        $this->assertSame(RefundStatus::PartiallyProcessed, Refund::where('idempotency_key', 'demo-financial:split')->sole()->status);
    }

    public function test_refund_before_commission_recognition_is_not_reversed_again_by_reconciliation(): void
    {
        \App\Models\CommissionRule::factory()->create(['scope'=>'global','is_active'=>true,'rate_basis_points'=>1000]);
        $f=$this->fixture(3000,7000);
        $refund=$this->refund($f,1);
        app(RefundService::class)->process($refund,$f[2]);
        $commission=app(RecognizeVendorOrderCommission::class)->handle($f[5]);
        $this->assertSame(667,$commission->original_commission_amount);
        $this->artisan('commissions:reconcile')->assertSuccessful();
        $this->assertSame(0,$commission->adjustments()->count());
        $this->assertSame(667,$commission->fresh()->original_commission_amount);
    }

    private function returnFor(array $f): ReturnRequest
    {
        $workflow = app(ReturnWorkflowService::class);
        $return = $workflow->request($f[0], $f[0]->user, ['reason' => 'damaged', 'items' => [['order_item_id' => $f[1]->id, 'quantity' => 1]]]);
        foreach ([ReturnStatus::Approved, ReturnStatus::AwaitingReturn, ReturnStatus::Received] as $status) {
            $workflow->transition($return, $f[2], $status);
        }
        $workflow->inspect($return, $f[2], ['items' => [['id' => $return->items()->first()->id, 'condition' => 'restockable', 'inspected_quantity' => 1, 'resolution' => 'refund']]]);
        $workflow->transition($return, $f[2], ReturnStatus::RefundPending);

        return $return->fresh();
    }

    public function test_return_internal_completion_and_stock_independence(): void
    {
        $f = $this->fixture(3000, 7000);
        $return = $this->returnFor($f);
        $before = InventoryMovement::count();
        $r = app(RefundService::class)->create($f[0], $f[2], ['key' => 'return-one', 'reason' => 'Inspected refund', 'items' => [['id' => $f[1]->id, 'quantity' => 1]]], $return);
        app(RefundService::class)->process($r, $f[2]);
        $this->assertSame(ReturnStatus::Completed, $return->fresh()->status);
        $this->assertSame($before, InventoryMovement::count());
        $this->assertDatabaseHas('return_histories', ['return_id' => $return->id, 'from_status' => 'refund_pending', 'to_status' => 'completed']);
    }

    public function test_return_external_remains_pending_and_over_quantity_fails(): void
    {
        $f = $this->fixture(0, 0);
        $return = $this->returnFor($f);
        try {
            app(RefundService::class)->create($f[0], $f[2], ['key' => 'too-many', 'reason' => 'Inspected refund', 'items' => [['id' => $f[1]->id, 'quantity' => 2]]], $return);
            $this->fail();
        } catch (ValidationException) {
            $this->assertDatabaseCount('refunds', 0);
        }
        $r = app(RefundService::class)->create($f[0], $f[2], ['key' => 'one', 'reason' => 'Inspected refund', 'items' => [['id' => $f[1]->id, 'quantity' => 1]]], $return);
        app(RefundService::class)->process($r, $f[2]);
        $this->assertSame(ReturnStatus::RefundPending, $return->fresh()->status);
    }

    public function test_admin_customer_vendor_authorization_and_safe_views(): void
    {
        $f = $this->fixture();
        $return = $this->returnFor($f);
        $url = route('admin.returns.refund.store', $return);
        $data = ['key' => 'ui-refund', 'reason' => 'Inspected return refund', 'items' => [['id' => $f[1]->id, 'quantity' => 1]]];
        $this->actingAs($f[0]->user)->post($url, $data)->assertForbidden();
        $this->actingAs($f[5]->vendor->user)->post($url,$data)->assertForbidden();
        $this->actingAs($f[2])->get(route('admin.returns.refund.create',$return))->assertOk();
        $this->post($url,$data)->assertRedirect();
        $r = Refund::firstOrFail();
        $this->get(route('admin.refunds.show',$r))->assertOk()->assertSee('Gift Card')->assertSee('Wallet');
        $this->actingAs($f[0]->user)->get(route('account.returns.show',$return))->assertOk()->assertSee('Gift Card');
        $this->actingAs($f[5]->vendor->user)->get(route('vendor.returns.show',$return))->assertOk()->assertSee('Seller reversal');
    }
}
