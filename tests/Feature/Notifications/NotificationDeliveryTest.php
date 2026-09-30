<?php

namespace Tests\Feature\Notifications;

use App\Enums\RefundStatus;
use App\Enums\VendorPayoutStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Refund;
use App\Models\RefundAllocation;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Models\VendorPayout;
use App\Notifications\Orders\OrderPlacedNotification;
use App\Notifications\Payouts\PayoutRequestedNotification;
use App\Notifications\Products\ProductApprovedNotification;
use App\Notifications\Products\ProductRejectedNotification;
use App\Notifications\Products\ProductSubmittedNotification;
use App\Notifications\Refunds\VendorRefundNotification;
use App\Notifications\Vendors\VendorApplicationSubmittedNotification;
use App\Notifications\Vendors\VendorApprovedNotification;
use App\Notifications\Vendors\VendorRejectedNotification;
use App\Services\Notifications\MarketplaceNotifier;
use App\Services\Notifications\SendUniqueNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_new_order_waits_for_authoritative_payment(): void
    {
        Notification::fake();
        $vendor = Vendor::factory()->approved()->create();
        $order = Order::factory()->create();
        VendorOrder::factory()->for($order)->for($vendor)->create();
        $payment = \App\Models\Payment::factory()->for($order)->create(['method'=>'card','status'=>'pending']);
        $notifier = app(MarketplaceNotifier::class);
        $notifier->orderPlaced($order);
        Notification::assertNotSentTo($vendor->user, \App\Notifications\Orders\VendorOrderCreatedNotification::class);
        $payment->update(['status'=>'paid']);
        $notifier->paymentChanged($payment, \App\Enums\PaymentStatus::Paid);
        $notifier->paymentChanged($payment, \App\Enums\PaymentStatus::Paid);
        Notification::assertSentTo($vendor->user, \App\Notifications\Orders\VendorOrderCreatedNotification::class, 1);
    }

    public function test_vendor_product_and_payout_lifecycles(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $v = Vendor::factory()->approved()->create();
        $other = Vendor::factory()->approved()->create();
        $n = app(MarketplaceNotifier::class);
        $n->vendorApplicationSubmitted($v);
        $n->vendorStatusChanged($v, 'approved');
        $n->vendorStatusChanged($v, 'rejected');
        Notification::assertSentTo($admin, VendorApplicationSubmittedNotification::class);
        Notification::assertSentTo($v->user, VendorApprovedNotification::class);
        Notification::assertSentTo($v->user, VendorRejectedNotification::class);
        $p = Product::factory()->for($v)->create();
        $n->productSubmitted($p);
        $n->productStatusChanged($p, 'approved');
        $n->productStatusChanged($p, 'rejected');
        Notification::assertSentTo($admin, ProductSubmittedNotification::class);
        Notification::assertSentTo($v->user, ProductApprovedNotification::class);
        Notification::assertSentTo($v->user, ProductRejectedNotification::class);
        $pay = VendorPayout::factory()->forVendor($v)->create();
        $n->payoutRequested($pay);
        foreach (['Approved', 'Processing', 'Paid', 'Rejected'] as $state) {
            $n->payoutStatusChanged($pay, constant(VendorPayoutStatus::class.'::'.$state));
            $class = 'App\\Notifications\\Payouts\\Payout'.$state.'Notification';
            Notification::assertSentTo($v->user, $class);
            Notification::assertNotSentTo($other->user, $class);
        }Notification::assertSentTo($admin, PayoutRequestedNotification::class);
    }

    public function test_real_database_queue_job_processes_without_smtp(): void
    {
        Mail::fake();
        config(['queue.default' => 'database']);
        $u = User::factory()->customer()->create();
        $n = new OrderPlacedNotification('Order placed', 'Queue test', '/account', 'queue:process', sendMail: true);
        app(SendUniqueNotification::class)->handle($u, $n);
        $this->assertSame(2, DB::table('jobs')->count());
        for ($i = 0; $i < 2; $i++) {
            $job = Queue::connection('database')->pop();
            $this->assertNotNull($job);
            $job->fire();
            $job->delete();
        }
        $this->assertSame(1, $u->notifications()->count());
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertNotNull(DB::table('notification_dispatches')->first()->mail_sent_at);
    }

    public function test_retry_command_requeues_failed_delivery_without_duplicate_inbox(): void
    {
        config(['queue.default' => 'sync']);
        $u = User::factory()->customer()->create();
        $sender = app(SendUniqueNotification::class);
        $n = new OrderPlacedNotification('Order', 'Message', '/account', 'retry:command');
        $sender->handle($u, $n);
        DB::table('notification_dispatches')->update(['status' => 'failed']);
        $this->artisan('notifications:retry')->assertSuccessful();
        $this->assertSame(1, $u->notifications()->count());
        $this->assertSame('queued', DB::table('notification_dispatches')->first()->status);
    }

    public function test_mail_replay_uses_channel_receipt(): void
    {
        Mail::fake();
        config(['queue.default' => 'sync']);
        $u = User::factory()->customer()->create();
        $sender = app(SendUniqueNotification::class);
        $n = new OrderPlacedNotification('Order', 'Message', '/account', 'mail:receipt', sendMail: true);
        $sender->handle($u, $n);
        $before = DB::table('notification_dispatches')->first()->mail_sent_at;
        $this->assertNotNull($before);
        $sender->dispatch($u, $n);
        $this->assertSame($before, DB::table('notification_dispatches')->first()->mail_sent_at);
        $this->assertSame(1, $u->notifications()->count());
    }

    public function test_arabic_and_urdu_email_structure_and_escaping(): void
    {
        $u = User::factory()->customer()->create(['name' => '<script>unsafe</script>']);
        $n = new OrderPlacedNotification('Order placed', '<script>body</script>', '/account', 'rtl');
        foreach (['ar', 'ur'] as $locale) {
            app()->setLocale($locale);
            $html = $n->toMail($u)->render();
            $this->assertStringContainsString('dir="rtl"', $html);
            $this->assertStringContainsString('lang="'.$locale.'"', $html);
            $this->assertStringNotContainsString('<script>', $html);
            $this->assertStringNotContainsString('notifications.hello', $html);
        }
    }

    public function test_vendor_refund_payload_is_private_and_only_authoritative(): void
    {
        Notification::fake();
        $v = Vendor::factory()->approved()->create();
        $other = Vendor::factory()->approved()->create();
        $o = Order::factory()->create();
        $vo = VendorOrder::factory()->for($o)->for($v)->create();
        $r = Refund::factory()->for($o)->create(['status' => 'failed']);
        RefundAllocation::factory()->for($r)->create(['vendor_order_id' => $vo->id]);
        $n = app(MarketplaceNotifier::class);
        $n->refundChanged($r, RefundStatus::Failed);
        Notification::assertNotSentTo($v->user, VendorRefundNotification::class);
        $r->update(['status' => 'succeeded']);
        $n->refundChanged($r, RefundStatus::Succeeded);
        Notification::assertSentTo($v->user, VendorRefundNotification::class, function ($n) use ($v) {
            $p = json_encode($n->toArray($v->user));

            return ! str_contains($p, 'wallet') && ! str_contains($p, 'gift_card') && ! str_contains($p, 'commission');
        });
        Notification::assertNotSentTo($other->user, VendorRefundNotification::class);
    }

    public function test_action_destination_still_enforces_ownership(): void
    {
        config(['queue.default' => 'sync']);
        $u = User::factory()->customer()->create();
        $o = Order::factory()->create();
        app(SendUniqueNotification::class)->handle($u, new OrderPlacedNotification('Order', 'Message', route('account.orders.show', $o, false), 'destination'));
        $this->actingAs($u)->get(route('account.orders.show', $o))->assertNotFound();
    }

    public function test_distinct_orders_and_event_versions_are_not_deduplicated(): void
    {
        Notification::fake();
        $u = User::factory()->customer()->create();
        $n = app(MarketplaceNotifier::class);
        $n->orderPlaced(Order::factory()->for($u)->create());
        $n->orderPlaced(Order::factory()->for($u)->create());
        Notification::assertSentTo($u,OrderPlacedNotification::class,2);
    }
}
