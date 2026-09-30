<?php

namespace Tests\Feature\Notifications;

use App\Enums\PaymentStatus;
use App\Enums\VendorOrderStatus;
use App\Events\DisputeActivity;
use App\Events\FinancialNotificationRequested;
use App\Events\ReturnStatusChanged;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Notifications\Lifecycle\DisputeMessageNotification;
use App\Notifications\Lifecycle\DisputeOpenedNotification;
use App\Notifications\Lifecycle\DisputeResolvedNotification;
use App\Notifications\Lifecycle\DisputeResponseRequiredNotification;
use App\Notifications\Lifecycle\GiftCardIssuedNotification;
use App\Notifications\Lifecycle\RefundExternalPendingNotification;
use App\Notifications\Lifecycle\ReturnApprovedNotification;
use App\Notifications\Lifecycle\ReturnRefundPendingNotification;
use App\Notifications\Lifecycle\ReturnRequestedNotification;
use App\Notifications\Lifecycle\WalletCreditNotification;
use App\Notifications\Lifecycle\WalletDebitNotification;
use App\Notifications\Orders\OrderCancelledNotification;
use App\Notifications\Orders\OrderPlacedNotification;
use App\Notifications\Orders\PaymentFailedNotification;
use App\Notifications\Orders\PaymentSucceededNotification;
use App\Notifications\Orders\VendorOrderCancelledNotification;
use App\Notifications\Orders\VendorOrderDeliveredNotification;
use App\Notifications\Orders\VendorOrderShippedNotification;
use App\Notifications\Products\ProductApprovedNotification;
use App\Notifications\Refunds\RefundSucceededNotification;
use App\Services\GiftCardService;
use App\Services\Notifications\MarketplaceNotifier;
use App\Services\Notifications\SendUniqueNotification;
use App\Services\WalletService;
use Database\Seeders\NotificationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TransactionalNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function commerce(): array
    {
        $u = User::factory()->customer()->create();
        $a = User::factory()->admin()->create();
        $v = Vendor::factory()->approved()->create();
        $o = Order::factory()->for($u)->create(['currency' => 'USD']);
        $vo = VendorOrder::factory()->for($o)->for($v)->create();

        return compact('u', 'a', 'v', 'o', 'vo');
    }

    public function test_preferences_required_channels_cannot_be_disabled(): void
    {
        $u = User::factory()->customer()->create();
        $this->actingAs($u)->put(route('account.notifications.preferences.update'), ['locale' => 'ur', 'preferences' => ['order' => ['database_enabled' => 0, 'email_enabled' => 0], 'product' => ['database_enabled' => 0, 'email_enabled' => 0]]])->assertRedirect();
        $this->assertDatabaseHas('notification_preferences', ['user_id' => $u->id, 'category' => 'order', 'email_enabled' => 1, 'database_enabled' => 1]);
        $this->assertSame('ur', $u->fresh()->notification_locale);
        $n = new OrderPlacedNotification('Title', 'Message', '/account', 'test', sendMail: true);
        $this->assertSame(['database', 'mail'], $n->via($u));
        $optional = new ProductApprovedNotification('Title', 'Message', '/vendor', 'optional', sendMail: true);
        $this->assertSame([], $optional->via($u));
    }

    public function test_preferences_ui_and_role_access(): void
    {
        foreach (['customer' => 'account', 'vendor' => 'vendor', 'admin' => 'admin'] as $role => $prefix) {
            $u = User::factory()->create(['role' => $role]);
            $this->actingAs($u)->get(route($prefix.'.notifications.preferences'))->assertOk()->assertSee('Email')->assertDontSee('WhatsApp');
        }
        $u = User::factory()->customer()->create();
        $this->actingAs($u)->get(route('vendor.notifications.preferences'))->assertForbidden();
    }

    public function test_database_delivery_and_channel_replay_are_idempotent(): void
    {
        config(['queue.default' => 'sync']);
        $u = User::factory()->customer()->create();
        $n = new OrderPlacedNotification('Order placed', 'Safe message', '/account', 'durable:1');
        $sender = app(SendUniqueNotification::class);
        $sender->handle($u, $n);
        $sender->handle($u, $n);
        $sender->dispatch($u, $n);
        $this->assertSame(1, $u->notifications()->count());
        $this->assertNotNull(DB::table('notification_dispatches')->first()->database_sent_at);
        $this->assertSame('order', $u->notifications()->first()->data['category']);
    }

    public function test_queue_uses_configured_connection_and_recipient_locale(): void
    {
        Queue::fake();
        $u = User::factory()->customer()->create(['notification_locale' => 'ar']);
        app(SendUniqueNotification::class)->handle($u, new OrderPlacedNotification('Title', 'Message', '/account', 'queued', sendMail: true));
        Queue::assertPushed(SendQueuedNotifications::class, fn ($job) => $job->notification->locale === 'ar');
    }

    public function test_mail_rendering_uses_brand_and_safe_link(): void
    {
        $u = User::factory()->customer()->create();
        $n = new OrderPlacedNotification('Order placed', 'Order ORD-123 confirmed', '/account', 'mail', sendMail: true);
        $mail = $n->toMail($u);
        $this->assertSame(url('/account'), $mail->actionUrl);
        $this->assertStringContainsString('ORD-123', $mail->render());
        $this->assertStringNotContainsString('notifications.thanks', $mail->render());
        $unsafe = new OrderPlacedNotification('Title', 'Message', '/\\evil.test', 'unsafe');
        $this->assertNull($unsafe->toArray($u)['action_url']);
    }

    public function test_rollback_does_not_emit_notifications(): void
    {
        Notification::fake();
        $f = $this->commerce();
        try {
            DB::transaction(function () use ($f) {
                app(MarketplaceNotifier::class)->orderPlaced($f['o']);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }Notification::assertNothingSent();
    }

    public function test_mail_failure_does_not_invalidate_committed_order_and_retry_is_available(): void
    {
        config(['queue.default' => 'sync']);
        $f = $this->commerce();
        $manager = app(ChannelManager::class);
        $manager->extend('mail', fn () => new class
        {
            public function send($u, $n)
            {
                throw new \RuntimeException('Simulated transport failure');
            }
        });
        $manager->forgetDrivers();
        app(MarketplaceNotifier::class)->orderPlaced($f['o']);
        $this->assertDatabaseHas('orders', ['id' => $f['o']->id]);
        $this->assertSame(1, $f['u']->notifications()->count());
        $this->assertDatabaseHas('notification_dispatches', ['notifiable_id' => $f['u']->id, 'status' => 'failed']);
    }

    public function test_authoritative_payment_and_failure_deduplication(): void
    {
        Notification::fake();
        $f = $this->commerce();
        $p = Payment::factory()->for($f['o'])->create(['status' => 'pending']);
        $n = app(MarketplaceNotifier::class);
        $n->paymentChanged($p, PaymentStatus::Paid);
        Notification::assertNothingSent();
        $p->update(['status' => 'paid']);
        $n->paymentChanged($p, PaymentStatus::Paid);
        Notification::assertSentTo($f['u'], PaymentSucceededNotification::class, 1);
        $p->update(['status' => 'failed']);
        $n->paymentChanged($p, PaymentStatus::Failed);
        $n->paymentChanged($p, PaymentStatus::Failed);
        Notification::assertSentTo($f['u'], PaymentFailedNotification::class, 1);
    }

    public function test_fulfillment_and_cancellation_are_distinct(): void
    {
        Notification::fake();
        $f = $this->commerce();
        $n = app(MarketplaceNotifier::class);
        $n->vendorOrderStatusChanged($f['vo'], VendorOrderStatus::Shipped);
        $n->vendorOrderStatusChanged($f['vo'], VendorOrderStatus::Delivered);
        $n->orderCancelled($f['o']);
        Notification::assertSentTo($f['u'], VendorOrderShippedNotification::class);
        Notification::assertSentTo($f['u'], VendorOrderDeliveredNotification::class);
        Notification::assertSentTo($f['u'], OrderCancelledNotification::class);
        Notification::assertSentTo($f['v']->user, VendorOrderCancelledNotification::class);
    }

    public function test_return_events_preserve_lifecycle_and_seller_scope(): void
    {
        Notification::fake();
        $f = $this->commerce();
        $other = Vendor::factory()->approved()->create();
        $r = ReturnRequest::create(['return_number' => 'RET-NOTIFY', 'order_id' => $f['o']->id, 'vendor_order_id' => $f['vo']->id, 'user_id' => $f['u']->id, 'reason' => 'other', 'status' => 'requested']);
        foreach (['requested', 'approved', 'rejected', 'received', 'inspected', 'refund_pending', 'cancelled'] as $i => $status) {
            event(new ReturnStatusChanged($r->id, null, $status, $f['a']->id, $i + 1));
        }
        Notification::assertSentTo($f['u'], ReturnApprovedNotification::class);
        Notification::assertSentTo($f['v']->user, ReturnRequestedNotification::class);
        Notification::assertSentTo($f['a'], ReturnRefundPendingNotification::class);
        Notification::assertNotSentTo($other->user, ReturnRequestedNotification::class);
    }

    public function test_dispute_recipients_self_message_and_required_response(): void
    {
        Notification::fake();
        $f = $this->commerce();
        $d = Dispute::create(['dispute_number' => 'DSP-NOTIFY', 'order_id' => $f['o']->id, 'vendor_order_id' => $f['vo']->id, 'opened_by' => $f['u']->id, 'reason' => 'other', 'description' => 'Please review', 'status' => 'open']);
        event(new DisputeActivity($d->id, 'opened', $f['u']->id, 1, 'open'));
        foreach ([$f['u'], $f['v']->user, $f['a']] as $u) {
            Notification::assertSentTo($u, DisputeOpenedNotification::class);
        }
        event(new DisputeActivity($d->id, 'message_added', $f['u']->id, 2, 'under_review'));
        Notification::assertNotSentTo($f['u'], DisputeMessageNotification::class);
        Notification::assertSentTo($f['v']->user, DisputeMessageNotification::class);
        event(new DisputeActivity($d->id, 'status_changed', $f['a']->id, 3, 'awaiting_vendor'));
        event(new DisputeActivity($d->id, 'status_changed', $f['a']->id, 4, 'awaiting_customer'));
        foreach ([$f['u'], $f['v']->user] as $u) {
            Notification::assertSentTo($u, DisputeResponseRequiredNotification::class);
        }
        event(new DisputeActivity($d->id, 'resolved', $f['a']->id, 5, 'resolved'));
        Notification::assertSentTo($f['u'], DisputeResolvedNotification::class, fn ($n) => str_contains($n->toArray($f['u'])['message'], 'pending provider'));
    }

    public function test_wallet_admin_credit_debit_and_gift_card_without_secret(): void
    {
        Notification::fake();
        $f = $this->commerce();
        $s = app(WalletService::class);
        $w = $s->wallet($f['u'], 'USD');
        $s->credit($w, 1000, 'Manual credit', 'credit-notify', $f['a']->id);
        $s->debit($w, 100, 'Manual debit', 'debit-notify', $f['a']->id);
        Notification::assertSentTo($f['u'], WalletCreditNotification::class);
        Notification::assertSentTo($f['u'], WalletDebitNotification::class);
        [$card,$code] = app(GiftCardService::class)->issue(500, 'USD', $f['a']->id, ['recipient_user_id' => $f['u']->id]);
        Notification::assertSentTo($f['u'], GiftCardIssuedNotification::class, fn ($n) => ! str_contains(json_encode($n->toArray($f['u'])), $code) && ! str_contains(json_encode($n->toArray($f['u'])), $card->code_hash));
    }

    public function test_refund_pending_and_completed_currency_and_privacy(): void
    {
        Notification::fake();
        $f = $this->commerce();
        $r = Refund::factory()->for($f['o'])->create(['status' => 'partially_processed', 'currency' => 'USD', 'amount' => 1234]);
        event(new FinancialNotificationRequested('refund', $r->id, 'partially_processed'));
        Notification::assertSentTo($f['u'], RefundExternalPendingNotification::class, fn ($n) => str_contains($n->toArray($f['u'])['message'], 'await') && ! str_contains($n->toArray($f['u'])['message'], 'completed'));
        Notification::assertSentTo($f['a'], RefundExternalPendingNotification::class);
        $r->update(['status' => 'succeeded']);
        event(new FinancialNotificationRequested('refund', $r->id, 'succeeded'));
        Notification::assertSentTo($f['u'], RefundSucceededNotification::class, fn ($n) => str_contains($n->toArray($f['u'])['message'], '12.34'));
    }

    public function test_repeat_seed_preserves_notification_ids_and_read_state(): void
    {
        User::factory()->customer()->create(['email' => 'customer@example.com']);
        $this->seed(NotificationSeeder::class);
        $before = DB::table('notifications')->orderBy('id')->get()->toJson();
        $this->seed(NotificationSeeder::class);
        $this->assertSame($before,DB::table('notifications')->orderBy('id')->get()->toJson());
    }
}
