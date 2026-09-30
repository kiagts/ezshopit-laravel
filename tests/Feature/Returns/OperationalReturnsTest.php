<?php

namespace Tests\Feature\Returns;

use App\Enums\ReturnStatus;
use App\Enums\VendorOrderStatus;
use App\Models\Dispute;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnHistory;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Services\Returns\ReturnEligibilityService;
use App\Services\Returns\ReturnWorkflowService;
use App\Services\SettingsService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ReturnWorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OperationalReturnsTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create(['user_id' => $customer->id]);
        $vo = VendorOrder::factory()->create(['order_id' => $order->id, 'status' => VendorOrderStatus::Delivered, 'delivered_at' => now()->subDays(2)]);
        $item = OrderItem::factory()->create(['vendor_order_id' => $vo->id, 'quantity' => 3]);

        return [$customer, $order, $vo, $item];
    }

    private function payload(OrderItem $item, int $qty = 1): array
    {
        return ['reason' => 'damaged', 'notes' => '<script>unsafe</script>', 'items' => [['order_item_id' => $item->id, 'quantity' => $qty]]];
    }

    private function makeReturn(): array
    {
        [$customer, $order, $vo, $item] = $this->fixture();
        $return = app(ReturnWorkflowService::class)->request($order, $customer, $this->payload($item));

        return [$customer, $order, $vo, $item, $return];
    }

    private function received(ReturnRequest $return, User $actor): void
    {
        foreach ([ReturnStatus::Approved, ReturnStatus::AwaitingReturn, ReturnStatus::Received] as $target) {
            app(ReturnWorkflowService::class)->transition($return, $actor, $target);
        }
    }

    private function inspection(ReturnRequest $return, string $condition = 'restockable', int $qty = 1): array
    {
        return ['items' => [['id' => $return->items()->firstOrFail()->id, 'condition' => $condition, 'inspected_quantity' => $qty, 'resolution' => 'refund', 'inspection_note' => 'Checked contents.']]];
    }

    public function test_request_records_items_vendor_scope_and_initial_history(): void
    {
        [$user, $order, $vo, $item] = $this->fixture();
        $this->actingAs($user)->post(route('account.orders.return.store', $order), $this->payload($item, 2))->assertRedirect();
        $return = ReturnRequest::firstOrFail();
        $this->assertSame($vo->id, $return->vendor_order_id);
        $this->assertSame(2, $return->items()->first()->quantity);
        $this->assertDatabaseHas('return_histories', ['return_id' => $return->id, 'to_status' => 'requested', 'actor_id' => $user->id, 'actor_role' => 'customer']);
        $this->assertSame(1, app(ReturnEligibilityService::class)->remaining($item));
    }

    public static function ineligible(): array
    {
        return [['undelivered'], ['expired'], ['missing_date'], ['future_date'], ['nonreturnable'], ['zero'], ['excess'], ['wrong_item'], ['mixed_vendor'], ['duplicate'], ['reason']];
    }

    #[DataProvider('ineligible')]
    public function test_invalid_requests_are_rejected_without_records(string $case): void
    {
        [$user, $order, $vo, $item] = $this->fixture();
        $payload = $this->payload($item);
        match ($case) {
            'undelivered' => $vo->update(['status' => VendorOrderStatus::Shipped]),
            'expired' => $vo->update(['delivered_at' => now()->subDays(31)]),
            'missing_date' => $vo->update(['delivered_at' => null]),
            'future_date' => $vo->update(['delivered_at' => now()->addDay()]),
            'nonreturnable' => $item->update(['is_returnable' => false]),
            'zero' => $payload['items'][0]['quantity'] = 0,
            'excess' => $payload['items'][0]['quantity'] = 4,
            'wrong_item' => $payload['items'][0]['order_item_id'] = OrderItem::factory()->create()->id,
            'mixed_vendor' => $payload['items'][] = ['order_item_id' => OrderItem::factory()->create(['vendor_order_id' => VendorOrder::factory()->create(['order_id' => $order->id, 'status' => VendorOrderStatus::Delivered, 'delivered_at' => now()])->id])->id, 'quantity' => 1],
            'duplicate' => $payload['items'][] = $payload['items'][0],
            'reason' => $payload['reason'] = 'anything',
        };
        $this->actingAs($user)->postJson(route('account.orders.return.store', $order), $payload)->assertUnprocessable();
        $this->assertDatabaseCount('returns', 0);
    }

    public function test_window_setting_is_authoritative(): void
    {
        app(SettingsService::class)->update('returns', ['window_days' => 1]);
        [$user, $order, $vo, $item] = $this->fixture();
        $this->actingAs($user)->postJson(route('account.orders.return.store', $order), $this->payload($item))->assertUnprocessable();
    }

    public function test_wrong_customer_and_guest_cannot_create(): void
    {
        [$user, $order, $vo, $item] = $this->fixture();
        $this->post(route('account.orders.return.store', $order), $this->payload($item))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->customer()->create())->post(route('account.orders.return.store', $order), $this->payload($item))->assertForbidden();
    }

    public function test_cumulative_quantities_cancel_and_rejection_release(): void
    {
        [$user, $order, $vo, $item, $return] = $this->makeReturn();
        $this->actingAs($user)->postJson(route('account.orders.return.store', $order), $this->payload($item, 3))->assertUnprocessable();
        app(ReturnWorkflowService::class)->transition($return, $user, ReturnStatus::Cancelled);
        $this->assertSame(3, app(ReturnEligibilityService::class)->remaining($item));
        $second = app(ReturnWorkflowService::class)->request($order, $user, $this->payload($item, 3));
        app(ReturnWorkflowService::class)->transition($second, $vo->vendor->user, ReturnStatus::Rejected, 'Not supported.');
        $this->assertSame(3, app(ReturnEligibilityService::class)->remaining($item));
        $this->assertNotSame($return->return_number, $second->return_number);
    }

    public function test_rejection_requires_reason_and_override_preserves_history(): void
    {
        [$user, $order, $vo, $item, $return] = $this->makeReturn();
        $vendor = $vo->vendor->user;
        $this->actingAs($vendor)->postJson(route('vendor.returns.transition', $return), ['status' => 'rejected'])->assertUnprocessable();
        $this->post(route('vendor.returns.transition', $return), ['status' => 'rejected', 'note' => 'Please clarify the issue.'])->assertRedirect();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.returns.transition', $return), ['status' => 'approved', 'note' => 'Evidence supports approval.'])->assertRedirect();
        $this->assertSame(['requested', 'rejected', 'approved'], $return->history()->pluck('to_status')->all());
        $this->assertDatabaseHas('return_histories', ['return_id' => $return->id, 'actor_role' => 'admin', 'from_status' => 'rejected', 'to_status' => 'approved']);
    }

    public function test_invalid_transition_and_late_customer_cancel_fail(): void
    {
        [$user, $order, $vo, $item, $return] = $this->makeReturn();
        $this->actingAs($vo->vendor->user)->postJson(route('vendor.returns.transition', $return), ['status' => 'completed'])->assertUnprocessable();
        $this->received($return, $vo->vendor->user);
        $this->actingAs($user)->post(route('account.returns.transition', $return), ['status' => 'cancelled'])->assertForbidden();
        $this->assertSame(ReturnStatus::Received, $return->fresh()->status);
    }

    public function test_all_role_screens_and_isolation(): void
    {
        [$user, $order, $vo, $item, $return] = $this->makeReturn();
        foreach ([['account', $user], ['vendor', $vo->vendor->user], ['admin', User::factory()->admin()->create()]] as [$prefix, $actor]) {
            $this->actingAs($actor)->get(route($prefix.'.returns.index'))->assertOk()->assertSee($return->return_number);
            $this->get(route($prefix.'.returns.show', $return))->assertOk()->assertDontSee('<script>unsafe</script>', false)->assertSee('&lt;script&gt;unsafe&lt;/script&gt;', false);
        }
        $other = User::factory()->customer()->create();
        $this->actingAs($other)->get(route('account.returns.index'))->assertDontSee($return->return_number);
        $this->get(route('account.returns.show', $return))->assertForbidden();
        $otherVendor = Vendor::factory()->approved()->create()->user;
        $this->actingAs($otherVendor)->get(route('vendor.returns.index'))->assertDontSee($return->return_number);
        $this->get(route('vendor.returns.show', $return))->assertForbidden();
        $this->post(route('vendor.returns.transition', $return), ['status' => 'approved'])->assertForbidden();
    }

    public function test_order_return_action_and_form(): void
    {
        [$user, $order, $vo, $item] = $this->fixture();
        $this->actingAs($user)->get(route('account.orders.show', $order))->assertOk()->assertSee('Return an item');
        $this->get(route('account.orders.returns.create', $order))->assertOk()->assertSee($item->product_name);
        $vo->update(['status' => VendorOrderStatus::Pending]);
        $this->get(route('account.orders.show', $order))->assertOk()->assertDontSee('Return an item');
    }

    public function test_history_is_immutable(): void
    {
        [, , , , $return] = $this->makeReturn();
        $this->expectException(\LogicException::class);
        $return->history()->first()->update(['note' => 'Overwrite']);
    }

    public function test_evidence_images_are_private_and_ownership_protected(): void
    {
        Storage::fake('local');
        [$user, $order, $vo, $item, $return] = $this->makeReturn();
        $this->actingAs($user)->post(route('account.returns.upload', $return), ['images' => [UploadedFile::fake()->image('proof.jpg')]])->assertRedirect();
        $evidence = $return->evidence()->firstOrFail();
        Storage::disk('local')->assertExists($evidence->path);
        $this->get(route('account.returns.evidence', $evidence))->assertOk();
        $this->actingAs($vo->vendor->user)->get(route('vendor.returns.evidence', $evidence))->assertOk();
        $this->actingAs(User::factory()->customer()->create())->get(route('account.returns.evidence', $evidence))->assertForbidden();
        $this->post(route('account.returns.upload', $return), ['images' => [UploadedFile::fake()->image('bad.jpg')]])->assertForbidden();
        $this->actingAs(Vendor::factory()->approved()->create()->user)->get(route('vendor.returns.evidence', $evidence))->assertForbidden();
    }

    public function test_evidence_rejects_unsafe_files_size_and_total_limit(): void
    {
        Storage::fake('local');
        [$user, , , , $return] = $this->makeReturn();
        $this->actingAs($user);
        foreach ([UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml'), UploadedFile::fake()->image('large.png')->size(5121)] as $file) {
            $this->postJson(route('account.returns.upload', $return), ['images' => [$file]])->assertUnprocessable();
        }
        $this->post(route('account.returns.upload', $return), ['images' => array_map(fn ($i) => UploadedFile::fake()->image("proof{$i}.png"), range(1, 5))])->assertRedirect();
        $this->postJson(route('account.returns.upload', $return), ['images' => [UploadedFile::fake()->image('extra.png')]])->assertUnprocessable();
        $this->assertSame(5, $return->evidence()->count());
    }

    public function test_inspection_restock_is_atomic_idempotent_and_nonfinancial(): void
    {
        [$user, $order, $vo, $item, $return] = $this->makeReturn();
        $actor = $vo->vendor->user;
        $this->received($return, $actor);
        $before = $item->variant->inventory?->on_hand ?? 0;
        $this->actingAs($actor)->post(route('vendor.returns.inspect', $return), $this->inspection($return))->assertRedirect();
        $this->assertSame($before + 1, $item->variant->fresh()->inventory->on_hand);
        $this->assertSame(1, InventoryMovement::where('reference_type', 'return_item')->count());
        $this->postJson(route('vendor.returns.inspect', $return), $this->inspection($return))->assertUnprocessable();
        $this->assertSame($before + 1, $item->variant->fresh()->inventory->on_hand);
        $this->post(route('vendor.returns.transition', $return), ['status' => 'refund_pending'])->assertRedirect();
        $this->assertSame(ReturnStatus::RefundPending, $return->fresh()->status);
        foreach (['refunds', 'gift_card_transactions', 'wallet_transactions'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->postJson(route('vendor.returns.transition', $return), ['status' => 'completed'])->assertUnprocessable();
    }

    public function test_damaged_and_invalid_inspections_do_not_restock(): void
    {
        [, , $vo, $item, $return] = $this->makeReturn();
        $this->received($return, $vo->vendor->user);
        $this->actingAs($vo->vendor->user)->postJson(route('vendor.returns.inspect', $return), $this->inspection($return, 'restockable', 2))->assertUnprocessable();
        $this->assertSame(ReturnStatus::Received, $return->fresh()->status);
        $this->post(route('vendor.returns.inspect', $return), $this->inspection($return, 'damaged'))->assertRedirect();
        $this->assertSame(0, InventoryMovement::where('reference_type', 'return_item')->count());
        $this->assertFalse($return->items()->first()->restock);
    }

    public function test_dispute_endpoints_still_work(): void
    {
        [$user, $order] = $this->fixture();
        $this->actingAs($user)->post(route('account.orders.dispute.store', $order), ['reason' => 'delivery', 'description' => 'Need help with delivery.'])->assertRedirect();
        $dispute = Dispute::firstOrFail();
        $this->post(route('account.disputes.messages.store', $dispute), ['body' => 'Additional information.'])->assertRedirect();
        $this->assertSame(2, $dispute->messages()->count());
    }

    public function test_seed_is_repeatable_and_preserves_operational_inventory(): void
    {
        for ($i = 0; $i < 6; $i++) {
            [, $order] = $this->fixture();
            $order->update(['order_number' => 'DEMO-LIFE-RETURN-TEST-'.$i]);
        }
        $this->seed(ReturnWorkflowSeeder::class);
        $this->assertDatabaseCount('returns', 6);
        $this->assertSame(6, ReturnRequest::distinct()->count('status'));
        $counts = [ReturnHistory::count(), InventoryMovement::count(), Inventory::sum('on_hand')];
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('returns', 6);
        $this->assertSame($counts, [ReturnHistory::count(), InventoryMovement::count(), Inventory::sum('on_hand')]);
    }

    public function test_initial_evidence_and_invalid_upload_roll_back_request(): void
    {
        Storage::fake('local');
        [$user, $order, $vo, $item] = $this->fixture();
        $this->actingAs($user)->postJson(route('account.orders.return.store', $order), $this->payload($item) + [
            'images' => [UploadedFile::fake()->create('bad.php', 1, 'text/plain')],
        ])->assertUnprocessable();
        $this->assertDatabaseCount('returns', 0);
        $this->assertDatabaseCount('return_histories', 0);
        $this->post(route('account.orders.return.store', $order), $this->payload($item) + [
            'images' => [UploadedFile::fake()->image('proof.png')],
        ])->assertRedirect();
        $this->assertDatabaseCount('return_evidence', 1);
    }

    public function test_customer_cannot_inspect_and_admin_can_complete_no_action_inspection(): void
    {
        [$user, , $vo, , $return] = $this->makeReturn();
        $this->received($return, $vo->vendor->user);
        $this->actingAs($user)->post(route('admin.returns.inspect', $return), $this->inspection($return))->assertForbidden();
        $admin = User::factory()->admin()->create();
        $data = $this->inspection($return, 'damaged');
        $data['items'][0]['resolution'] = 'no_action';
        $this->actingAs($admin)->post(route('admin.returns.inspect', $return), $data)->assertRedirect();
        $this->post(route('admin.returns.transition', $return), ['status' => 'completed'])->assertRedirect();
        $this->assertSame(ReturnStatus::Completed, $return->fresh()->status);
        $this->assertSame(0, InventoryMovement::where('reference_type', 'return_item')->count());
    }

    public function test_override_cannot_reclaim_quantity_allocated_to_another_return(): void
    {
        [$user, $order, $vo, $item, $return] = $this->makeReturn();
        app(ReturnWorkflowService::class)->transition($return, $vo->vendor->user, ReturnStatus::Rejected, 'Declined with explanation.');
        app(ReturnWorkflowService::class)->request($order, $user, $this->payload($item, 3));
        $this->actingAs(User::factory()->admin()->create())->postJson(route('admin.returns.transition', $return), [
            'status' => 'approved', 'note' => 'Override request.',
        ])->assertUnprocessable();
        $this->assertSame(ReturnStatus::Rejected, $return->fresh()->status);
    }

    public function test_inspection_item_ownership_and_pre_receipt_rules(): void
    {
        [, , $vo, , $return] = $this->makeReturn();
        $this->actingAs($vo->vendor->user)->postJson(route('vendor.returns.inspect', $return), $this->inspection($return))->assertUnprocessable();
        $this->received($return, $vo->vendor->user);
        $data = $this->inspection($return);
        $data['items'][0]['id'] = 999999;
        $this->postJson(route('vendor.returns.inspect', $return), $data)->assertUnprocessable();
        $this->assertSame(ReturnStatus::Received, $return->fresh()->status);
        $this->assertSame(0, InventoryMovement::where('reference_type', 'return_item')->count());
    }
}
