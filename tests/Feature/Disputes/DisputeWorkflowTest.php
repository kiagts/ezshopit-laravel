<?php

namespace Tests\Feature\Disputes;

use App\Actions\Commissions\RecognizeVendorOrderCommission;
use App\Enums\DisputeStatus;
use App\Enums\RefundStatus;
use App\Enums\ReturnStatus;
use App\Events\DisputeActivity;
use App\Models\CommissionAdjustment;
use App\Models\CommissionRule;
use App\Models\Dispute;
use App\Models\DisputeEvidence;
use App\Models\DisputeHistory;
use App\Models\DisputeMessage;
use App\Models\GiftCardTransaction;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Models\VendorLedgerEntry;
use App\Models\VendorOrder;
use App\Models\VendorPayout;
use App\Models\WalletTransaction;
use App\Services\Disputes\DisputeWorkflowService;
use App\Services\GiftCardService;
use App\Services\Refunds\ExternalRefundGateway;
use App\Services\Refunds\RefundService;
use App\Services\Returns\ReturnWorkflowService;
use App\Services\VendorLedger\VendorEarningsService;
use App\Services\WalletService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DisputeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(bool $external = false): array
    {
        $customer = User::factory()->customer()->create();
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create(['user_id' => $customer->id, 'currency' => 'USD', 'items_subtotal' => 10000, 'discount_total' => 1000, 'tax_total' => 900, 'shipping_total' => 0, 'grand_total' => 9900, 'payment_status' => 'paid']);
        $vo = VendorOrder::factory()->create(['order_id' => $order->id, 'items_subtotal' => 10000, 'discount_total' => 1000, 'vendor_discount_total' => 1000, 'tax_total' => 900, 'shipping_total' => 0, 'grand_total' => 9900, 'status' => 'delivered', 'delivered_at' => now()->subDays(2)]);
        $item = OrderItem::factory()->create(['vendor_order_id' => $vo->id, 'quantity' => 2, 'unit_price' => 5000, 'line_total' => 10000, 'discount_total' => 1000, 'vendor_discount_total' => 1000, 'net_line_total' => 9000, 'tax_total' => 900]);
        [$card,$code] = app(GiftCardService::class)->issue(3000, 'USD');
        app(GiftCardService::class)->redeem($order, $code, 3000);
        $order->update(['gift_card_applied_total' => 3000]);
        $wallet = app(WalletService::class)->wallet($customer, 'USD');
        app(WalletService::class)->credit($wallet, $external ? 2000 : 6900, 'Demo funding', 'fund:'.$order->id);
        DB::transaction(fn () => app(WalletService::class)->applyToOrder($order, true));
        $payment = Payment::factory()->create(['order_id' => $order->id, 'status' => 'paid', 'currency' => 'USD', 'amount' => $external ? 4900 : 0]);

        return compact('customer', 'admin', 'order', 'vo', 'item', 'card', 'wallet', 'payment');
    }

    private function open(array $f, array $extra = []): Dispute
    {
        return app(DisputeWorkflowService::class)->open($f['order'], $f['customer'], $extra + ['vendor_order_id' => $f['vo']->id, 'reason' => 'item_condition', 'description' => 'The item condition needs review.']);
    }

    private function review(Dispute $d, array $f): void
    {
        app(DisputeWorkflowService::class)->transition($d, $f['admin'], DisputeStatus::UnderReview);
    }

    private function decision(array $f, int $qty = 2): array
    {
        return ['resolution' => 'customer_favored_partial_refund', 'note' => 'Refund inspected historical quantity.', 'items' => [['id' => $f['item']->id, 'quantity' => $qty]]];
    }

    private function rejection(callable $fn): void
    {
        try {
            $fn();
            $this->fail('Expected validation failure.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    private function counters(): array
    {
        return [Refund::count(), GiftCardTransaction::count(), WalletTransaction::count(), CommissionAdjustment::count(), VendorLedgerEntry::count(), InventoryMovement::count()];
    }

    private function makeReturn(array $f, int $qty = 2)
    {
        return app(ReturnWorkflowService::class)->request($f['order'], $f['customer'], ['reason' => 'damaged', 'items' => [['order_item_id' => $f['item']->id, 'quantity' => $qty]]]);
    }

    private function inspect($return, array $f, int $qty = 2): void
    {
        $w = app(ReturnWorkflowService::class);
        if ($return->fresh()->status === ReturnStatus::Requested) {
            $w->transition($return, $f['vo']->vendor->user, ReturnStatus::Approved);
        }
        $w->transition($return, $f['vo']->vendor->user, ReturnStatus::AwaitingReturn);
        $w->transition($return, $f['vo']->vendor->user, ReturnStatus::Received);
        $w->inspect($return, $f['vo']->vendor->user, ['items' => [['id' => $return->items()->first()->id, 'condition' => 'restockable', 'inspected_quantity' => $qty, 'resolution' => 'refund']]]);
        $w->transition($return, $f['admin'], ReturnStatus::RefundPending);
    }

    public function test_open_own_dispute_records_reason_scope_number_and_history(): void
    {
        $f = $this->fixture();
        $d = $this->open($f);
        $this->assertSame($f['vo']->id, $d->vendor_order_id);
        $this->assertSame(DisputeStatus::Open, $d->status);
        $this->assertStringStartsWith('DSP-', $d->dispute_number);
        $this->assertSame(1, $d->history()->count());
        $this->assertSame('customer', $d->messages()->first()->author_role);
    }

    public function test_ownership_and_cross_vendor_references_are_rejected(): void
    {
        $f = $this->fixture();
        $g = $this->fixture();
        $this->actingAs($g['customer'])->post(route('account.orders.dispute.store', $f['order']), ['reason' => 'other', 'description' => 'Not my order'])->assertForbidden();
        $this->rejection(fn () => $this->open($f, ['vendor_order_id' => $g['vo']->id]));
        $return = $this->makeReturn($g);
        $this->rejection(fn () => $this->open($f, ['return_request_id' => $return->id]));
    }

    public function test_duplicate_active_issue_and_invalid_reason_are_rejected(): void
    {
        $f = $this->fixture();
        $this->open($f);
        $this->rejection(fn () => $this->open($f));
        $this->rejection(fn () => $this->open($f, ['reason' => 'invalid']));
        $this->open($f, ['reason' => 'other']);
        $this->assertDatabaseCount('disputes', 2);
    }

    public function test_customer_vendor_admin_can_reply_but_unrelated_users_cannot(): void
    {
        $f = $this->fixture();
        $d = $this->open($f);
        foreach ([[$f['customer'], 'account'], [$f['vo']->vendor->user, 'vendor'], [$f['admin'], 'admin']] as [$actor,$prefix]) {
            $this->actingAs($actor)->post(route($prefix.'.disputes.messages.store', $d), ['body' => 'Valid participant reply'])->assertRedirect();
        }
        $this->assertSame(4, $d->messages()->count());
        $g = $this->fixture();
        foreach ([[$g['customer'], 'account'], [$g['vo']->vendor->user, 'vendor']] as [$actor,$prefix]) {
            $this->actingAs($actor)->post(route($prefix.'.disputes.messages.store', $d), ['body' => 'Unrelated reply'])->assertForbidden();
        }
    }

    public function test_message_and_history_are_immutable(): void
    {
        $d = $this->open($this->fixture());
        try {
            $d->messages()->first()->update(['body' => 'Rewrite']);
            $this->fail();
        } catch (\LogicException) {
            $this->assertSame(1, $d->messages()->count());
        }
        $this->expectException(\LogicException::class);
        $d->history()->first()->delete();
    }

    public function test_customer_response_and_vendor_response_return_to_review(): void
    {
        $f = $this->fixture();
        $d = $this->open($f);
        $this->review($d, $f);
        $w = app(DisputeWorkflowService::class);
        foreach ([[DisputeStatus::AwaitingCustomer, $f['customer']], [DisputeStatus::AwaitingVendor, $f['vo']->vendor->user]] as [$status,$actor]) {
            $w->transition($d, $f['admin'], $status, 'Please provide the requested details.');
            $w->message($d, $actor, 'Here are those details.');
            $this->assertSame(DisputeStatus::UnderReview, $d->fresh()->status);
        }
    }

    public function test_invalid_transitions_and_privileged_customer_action_fail(): void
    {
        $f = $this->fixture();
        $d = $this->open($f);
        $this->rejection(fn () => app(DisputeWorkflowService::class)->transition($d, $f['admin'], DisputeStatus::Closed));
        $this->actingAs($f['customer'])->post(route('admin.disputes.resolve', $d), ['resolution' => 'vendor_favored', 'note' => 'Reject the request.'])->assertForbidden();
        $this->actingAs($f['vo']->vendor->user)->post(route('admin.disputes.transition', $d), ['target' => 'under_review'])->assertForbidden();
    }

    public function test_valid_evidence_is_private_and_participant_scoped(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $d = $this->open($f);
        $file = UploadedFile::fake()->image('photo.jpg');
        $this->actingAs($f['customer'])->post(route('account.disputes.messages.store', $d), ['body' => 'Photo evidence', 'images' => [$file]])->assertRedirect();
        $e = DisputeEvidence::sole();
        Storage::disk('local')->assertExists($e->path);
        $this->get(route('account.disputes.evidence', $e))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $g = $this->fixture();
        $this->actingAs($g['vo']->vendor->user)->get(route('vendor.disputes.evidence', $e))->assertForbidden();
        $this->actingAs($g['customer'])->get(route('account.disputes.evidence', $e))->assertForbidden();
        $this->actingAs($f['admin'])->get(route('admin.disputes.evidence', $e))->assertOk();
    }

    public function test_invalid_evidence_and_limits_roll_back_message_and_initial_open(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $this->actingAs($f['customer'])->post(route('account.orders.dispute.store', $f['order']), ['reason' => 'other', 'description' => 'Unsafe upload test', 'images' => [UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml')]])->assertSessionHasErrors();
        $this->assertDatabaseCount('disputes', 0);
        $d = $this->open($f);
        $files = array_map(fn () => UploadedFile::fake()->image('image.png'), range(1, 6));
        $this->post(route('account.disputes.messages.store', $d), ['body' => 'Too many images', 'images' => $files])->assertSessionHasErrors();
        $this->assertSame(1, $d->messages()->count());
    }

    public function test_assignment_requires_admin_and_is_audited_once(): void
    {
        $f = $this->fixture();
        $d = $this->open($f);
        $w = app(DisputeWorkflowService::class);
        $w->assign($d, $f['admin'], $f['admin']->id);
        $w->assign($d, $f['admin'], $f['admin']->id);
        $this->assertSame(2, $d->history()->count());
        $this->rejection(fn () => $w->assign($d, $f['admin'], $f['customer']->id));
    }

    public function test_role_lists_details_and_customer_create_form_render_safely(): void
    {
        $f = $this->fixture();
        $d = $this->open($f, ['description' => '<script>alert(1)</script>']);
        $g = $this->fixture();
        $other = $this->open($g);
        foreach ([[$f['customer'], 'account'], [$f['vo']->vendor->user, 'vendor'], [$f['admin'], 'admin']] as [$actor,$prefix]) {
            $this->actingAs($actor)->get(route($prefix.'.disputes.index'))->assertOk()->assertSee($d->dispute_number);
            $this->get(route($prefix.'.disputes.show', $d))->assertOk()->assertSee('Send reply')->assertDontSee('<script>alert(1)</script>', false);
            if ($prefix !== 'admin') {
                $this->get(route($prefix.'.disputes.index'))->assertDontSee($other->dispute_number);
            }
        }
        $this->actingAs($f['customer'])->get(route('account.orders.dispute.create', $f['order']))->assertOk();
        $this->actingAs($g['customer'])->get(route('account.disputes.show', $d))->assertForbidden();
    }

    public function test_resolution_requires_review_note_and_an_explicit_outcome(): void
    {
        $f = $this->fixture();
        $d = $this->open($f);
        $w = app(DisputeWorkflowService::class);
        $this->rejection(fn () => $w->resolve($d, $f['admin'], ['resolution' => 'vendor_favored', 'note' => 'Valid note here']));
        $this->review($d, $f);
        $this->rejection(fn () => $w->resolve($d, $f['admin'], ['resolution' => 'invalid', 'note' => 'Valid note here']));
        $this->rejection(fn () => $w->resolve($d, $f['admin'], ['resolution' => 'vendor_favored', 'note' => '']));
    }

    public function test_no_financial_resolution_close_and_terminal_protection(): void
    {
        $f = $this->fixture();
        $d = $this->open($f);
        $this->review($d, $f);
        $before = $this->counters();
        $w = app(DisputeWorkflowService::class);
        $data = ['resolution' => 'no_financial_action', 'note' => 'Resolved with an explanation only.'];
        $w->resolve($d, $f['admin'], $data);
        $w->resolve($d, $f['admin'], $data);
        $this->assertSame($f['admin']->id, $d->fresh()->resolved_by);
        $this->assertNotNull($d->fresh()->resolved_at);
        $this->assertSame($data['note'], $d->fresh()->resolution_note);
        $this->actingAs($f['customer'])->post(route('account.disputes.messages.store', $d), ['body' => 'After resolution'])->assertForbidden();
        $w->transition($d, $f['admin'], DisputeStatus::Closed);
        $w->transition($d, $f['admin'], DisputeStatus::Closed);
        $this->assertSame($before, $this->counters());
        $this->post(route('account.disputes.messages.store', $d), ['body' => 'After close'])->assertForbidden();
        $this->assertSame(4, $d->history()->count());
    }

    public function test_financial_failure_keeps_review_state_and_rolls_back_all_money(): void
    {
        $f = $this->fixture(true);
        $d = $this->open($f);
        $this->review($d, $f);
        $before = $this->counters();
        $this->mock(ExternalRefundGateway::class)->shouldReceive('request')->andThrow(new \RuntimeException('Unavailable'));
        try {
            app(DisputeWorkflowService::class)->resolve($d, $f['admin'], $this->decision($f));
            $this->fail();
        } catch (\RuntimeException) {
            $this->assertSame(DisputeStatus::UnderReview, $d->fresh()->status);
            $this->assertSame($before, $this->counters());
        }
    }

    public function test_journey_a_normal_return_internal_refund(): void
    {
        $f = $this->fixture();
        CommissionRule::factory()->create(['scope' => 'global', 'is_active' => true, 'rate_basis_points' => 1000]);
        app(RecognizeVendorOrderCommission::class)->handle($f['vo']);
        $return = $this->makeReturn($f);
        $this->inspect($return, $f);
        $stock = InventoryMovement::count();
        $refund = app(RefundService::class)->create($f['order'], $f['admin'], ['key' => 'journey-a', 'reason' => 'Normal inspected return', 'items' => [['id' => $f['item']->id, 'quantity' => 2]]], $return);
        app(RefundService::class)->process($refund, $f['admin']);
        $this->assertSame(ReturnStatus::Completed, $return->fresh()->status);
        $this->assertSame($stock, InventoryMovement::count());
        $this->assertSame(3000, $f['card']->fresh()->balance());
        $this->assertSame(6900, app(WalletService::class)->balance($f['wallet']));
        $this->assertSame(-900, (int) CommissionAdjustment::sum('commission_amount_delta'));
    }

    public function test_journey_b_rejected_return_dispute_override_then_refund(): void
    {
        $f = $this->fixture();
        $return = $this->makeReturn($f);
        $rw = app(ReturnWorkflowService::class);
        $rw->transition($return, $f['vo']->vendor->user, ReturnStatus::Rejected, 'Seller rejection reason.');
        $d = $this->open($f, ['reason' => 'return_rejected', 'return_request_id' => $return->id]);
        $this->review($d, $f);
        app(DisputeWorkflowService::class)->resolve($d, $f['admin'], ['resolution' => 'return_approved', 'note' => 'Evidence supports accepting the return.']);
        $this->assertDatabaseHas('return_histories', ['return_id' => $return->id, 'to_status' => 'rejected', 'actor_role' => 'vendor']);
        $this->assertSame(ReturnStatus::Approved, $return->fresh()->status);
        $this->inspect($return, $f);
        $refund = app(RefundService::class)->create($f['order'], $f['admin'], ['key' => 'journey-b', 'reason' => 'Approved inspected return', 'items' => [['id' => $f['item']->id, 'quantity' => 2]]], $return);
        app(RefundService::class)->process($refund, $f['admin']);
        $this->assertSame(ReturnStatus::Completed, $return->fresh()->status);
    }

    public function test_journey_c_additional_refund_preserves_completed_original(): void
    {
        $f = $this->fixture();
        $return = $this->makeReturn($f);
        $this->inspect($return, $f);
        $refund = app(RefundService::class)->create($f['order'], $f['admin'], ['key' => 'journey-c', 'reason' => 'First inspected quantity', 'items' => [['id' => $f['item']->id, 'quantity' => 1]]], $return);
        app(RefundService::class)->process($refund, $f['admin']);
        $original = $refund->fresh()->getRawOriginal();
        $d = $this->open($f, ['reason' => 'refund_amount', 'return_request_id' => $return->id, 'refund_id' => $refund->id]);
        $this->review($d, $f);
        app(DisputeWorkflowService::class)->resolve($d, $f['admin'], $this->decision($f, 1));
        $this->assertSame($original, $refund->fresh()->getRawOriginal());
        $this->assertDatabaseCount('refunds', 2);
        $this->assertSame(ReturnStatus::Completed, $return->fresh()->status);
    }

    public function test_journey_d_vendor_favored_no_money_changes(): void
    {
        $f = $this->fixture();
        $d = $this->open($f);
        app(DisputeWorkflowService::class)->message($d, $f['vo']->vendor->user, 'Seller supplied details.');
        $this->review($d, $f);
        $before = $this->counters();
        app(DisputeWorkflowService::class)->resolve($d, $f['admin'], ['resolution' => 'vendor_favored', 'note' => 'Seller evidence supports the original decision.']);
        $this->assertSame($before, $this->counters());
        $this->assertSame(DisputeStatus::Resolved, $d->fresh()->status);
    }

    public function test_journey_e_split_funding_preserves_external_pending(): void
    {
        $f = $this->fixture(true);
        CommissionRule::factory()->create(['scope' => 'global', 'is_active' => true, 'rate_basis_points' => 1000]);
        app(RecognizeVendorOrderCommission::class)->handle($f['vo']);
        $d = $this->open($f);
        $this->review($d, $f);
        app(DisputeWorkflowService::class)->resolve($d, $f['admin'], $this->decision($f));
        $refund = $d->fresh()->resolutionRefund;
        $this->assertSame(RefundStatus::PartiallyProcessed, $refund->status);
        $this->assertSame(4900, (int) $refund->fundingAllocations()->where('status', 'pending_provider')->sum('amount'));
        $this->assertSame('paid', $f['payment']->fresh()->status->value);
        $this->assertSame(-900, (int) CommissionAdjustment::sum('commission_amount_delta'));
        $this->assertSame(2000, app(WalletService::class)->balance($f['wallet']));
    }

    public function test_journey_f_multi_vendor_privacy_and_financial_isolation(): void
    {
        $f = $this->fixture(true);
        $g = $this->fixture(true);
        $g['vo']->update(['order_id' => $f['order']->id]);
        $f['order']->increment('grand_total', 9900);
        $f['payment']->increment('amount', 9900);
        $g['item']->update(['product_name' => 'PRIVATE-SELLER-B-ITEM']);
        $d = $this->open($f);
        $this->review($d, $f);
        $this->actingAs($f['vo']->vendor->user)->get(route('vendor.disputes.show', $d))->assertOk()->assertDontSee('PRIVATE-SELLER-B-ITEM')->assertDontSee('Wallet balance')->assertDontSee($f['card']->code_hash);
        $this->actingAs($g['vo']->vendor->user)->get(route('vendor.disputes.show', $d))->assertForbidden();
        $this->rejection(fn () => app(DisputeWorkflowService::class)->resolve($d, $f['admin'], $this->decision($g)));
        app(DisputeWorkflowService::class)->resolve($d, $f['admin'], $this->decision($f));
        $this->assertSame(0, VendorLedgerEntry::where('vendor_id', $g['vo']->vendor_id)->count());
        $this->assertSame(0, $g['vo']->refundAllocations()->count());
    }

    public function test_journey_g_post_payout_liability_preserves_payout(): void
    {
        config(['marketplace.vendor_earnings_hold_days' => 0]);
        $f = $this->fixture();
        CommissionRule::factory()->create(['scope' => 'global', 'is_active' => true, 'rate_basis_points' => 1000]);
        app(RecognizeVendorOrderCommission::class)->handle($f['vo']);
        $payout = VendorPayout::factory()->forVendor($f['vo']->vendor)->paid()->create(['amount' => 8100]);
        $original = $payout->fresh()->getRawOriginal();
        VendorLedgerEntry::forceCreate(['vendor_id' => $f['vo']->vendor_id, 'source_key' => 'journey-payout', 'type' => 'payout', 'amount' => -8100, 'currency' => 'USD', 'available_at' => now(), 'description' => 'Paid demo payout']);
        $d = $this->open($f);
        $this->review($d, $f);
        app(DisputeWorkflowService::class)->resolve($d, $f['admin'], $this->decision($f));
        $this->assertSame($original, $payout->fresh()->getRawOriginal());
        $this->assertLessThan(0, app(VendorEarningsService::class)->summaryFor($f['vo']->vendor, 'USD')->availableBalance);
    }

    public function test_journey_h_resolution_retry_has_no_duplicate_effects_or_events(): void
    {
        Event::fake([DisputeActivity::class]);
        $f = $this->fixture();
        $d = $this->open($f);
        $this->review($d, $f);
        $w = app(DisputeWorkflowService::class);
        $data = $this->decision($f);
        $w->resolve($d, $f['admin'], $data);
        $before = $this->counters();
        $history = $d->history()->count();
        $events = Event::dispatched(DisputeActivity::class)->count();
        $this->assertGreaterThan(0, $events);
        $w->resolve($d, $f['admin'], $data);
        $this->assertSame($before, $this->counters());
        $this->assertSame($history, $d->history()->count());
        $this->assertSame($events, Event::dispatched(DisputeActivity::class)->count());
        $this->rejection(fn () => $w->resolve($d, $f['admin'], $this->decision($f, 1)));
    }

    public function test_dispute_seeder_is_repeatable_and_financial_resolution_reconciles(): void
    {
        $this->seed(DatabaseSeeder::class);
        $before = $this->counters();
        $records = [Dispute::count(), DisputeMessage::count(), DisputeHistory::count()];
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($before, $this->counters());
        $this->assertSame($records, [Dispute::count(), DisputeMessage::count(), DisputeHistory::count()]);
        $this->assertSame(7, Dispute::count());
        $d = Dispute::where('description', 'Operational dispute demo: customer_refund')->sole();
        $this->assertSame(RefundStatus::Succeeded, $d->resolutionRefund->status);
        $this->assertSame($d->resolutionRefund->amount, (int) $d->resolutionRefund->fundingAllocations()->sum('amount'));
    }

    public function test_caps_and_original_currency_remain_authoritative(): void
    {
        $f = $this->fixture();
        $return = $this->makeReturn($f,1);
        $this->inspect($return,$f,1);
        $d = $this->open($f,['return_request_id' => $return->id]);
        $this->review($d,$f);
        $this->rejection(fn () => app(DisputeWorkflowService::class)->resolve($d,$f['admin'],$this->decision($f,2)));
        session(['currency_code' => 'EUR']);
        $f['item']->variant->update(['base_price' => 999999]);
        app(DisputeWorkflowService::class)->resolve($d,$f['admin'],$this->decision($f,1));
        $refund = $d->fresh()->resolutionRefund;
        $this->assertSame(4950,$refund->amount);
        $this->assertSame('USD',$refund->currency);
        $this->assertSame(450,(int) $refund->allocations()->sum('tax_amount'));
    }
}
