<?php

namespace Tests\Feature\Notifications;

use App\Enums\UserRole;
use App\Enums\RefundStatus;
use App\Actions\Refunds\MarkRefundFailed;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Notifications\Orders\OrderPlacedNotification;
use App\Notifications\Orders\VendorOrderCreatedNotification;
use App\Services\Notifications\MarketplaceNotifier;
use App\Services\Notifications\SendUniqueNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unique_notification_dispatch_deduplicates_by_recipient_and_source_key(): void
    {
        Notification::fake();
        $user = User::factory()->customer()->create();
        $notification = new OrderPlacedNotification(
            'Order placed',
            'Your order ORD-1 has been placed.',
            '/account/orders/ORD-1',
            'order:1:placed:user:'.$user->id,
            'success',
            true,
        );

        app(SendUniqueNotification::class)->handle($user, $notification);
        app(SendUniqueNotification::class)->handle($user, $notification);

        Notification::assertSentTo($user, OrderPlacedNotification::class, 1);
        $this->assertSame(1, DB::table('notification_dispatches')->where('source_key', $notification->sourceKey())->count());
        $this->assertSame(['database', 'mail'], $notification->via($user));
        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertArrayNotHasKey('shipping_address_line_1', $notification->toArray($user));
    }

    public function test_notification_centers_are_role_protected_and_scoped_to_the_current_user(): void
    {
        $customer = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $vendor = Vendor::factory()->approved()->create();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $ownNotification = $this->storedNotification($customer, 'Own update', '/account/orders');
        $otherNotification = $this->storedNotification($other, 'Private update', '/account/orders');
        $this->storedNotification($vendor->user, 'Vendor update', '/vendor/orders');
        $this->storedNotification($admin, 'Admin update', '/admin/payouts');

        $this->actingAs($customer)
            ->get(route('account.notifications.index'))
            ->assertOk()
            ->assertSeeText('Own update')
            ->assertDontSeeText('Private update')
            ->assertDontSeeText('Vendor update')
            ->assertDontSeeText('Admin update');

        $this->actingAs($vendor->user)
            ->get(route('vendor.notifications.index'))
            ->assertOk()
            ->assertSeeText('Vendor update')
            ->assertDontSeeText('Own update');

        $this->actingAs($admin)
            ->get(route('admin.notifications.index'))
            ->assertOk()
            ->assertSeeText('Admin update');

        $this->actingAs($customer)
            ->post(route('account.notifications.read', $otherNotification))
            ->assertNotFound();

        $this->actingAs($customer)
            ->post(route('account.notifications.read', $ownNotification))
            ->assertRedirect();

        $this->assertNotNull($customer->notifications()->whereKey($ownNotification)->first()->read_at);
        $this->assertNull($other->notifications()->whereKey($otherNotification)->first()->read_at);
    }

    public function test_notification_open_marks_read_and_rejects_external_redirects(): void
    {
        $customer = User::factory()->customer()->create();
        $safe = $this->storedNotification($customer, 'Safe update', '/account/orders');
        $external = $this->storedNotification($customer, 'Unsafe update', 'https://example.test/phish');

        $this->actingAs($customer)
            ->get(route('account.notifications.open', $safe))
            ->assertRedirect('/account/orders');

        $this->assertNotNull($customer->notifications()->whereKey($safe)->first()->read_at);

        $this->actingAs($customer)
            ->get(route('account.notifications.open', $external))
            ->assertRedirect(route('account.notifications.index'));
    }

    public function test_mark_all_read_only_affects_current_user(): void
    {
        $customer = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $this->storedNotification($customer, 'First', '/account');
        $this->storedNotification($customer, 'Second', '/account');
        $this->storedNotification($other, 'Other', '/account');

        $this->actingAs($customer)
            ->post(route('account.notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $customer->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    public function test_order_placed_notification_is_customer_and_vendor_specific(): void
    {
        Notification::fake();

        $customer = User::factory()->customer()->create();
        $order = Order::factory()->for($customer)->create(['order_number' => 'ORD-PRIVATE']);
        \App\Models\Payment::factory()->for($order)->create();
        $vendorA = Vendor::factory()->approved()->create(['display_name' => 'Alpha Store']);
        $vendorB = Vendor::factory()->approved()->create(['display_name' => 'Beta Store']);
        $vendorOrderA = VendorOrder::factory()->for($order)->for($vendorA)->create(['vendor_order_number' => 'VO-ALPHA']);
        $vendorOrderB = VendorOrder::factory()->for($order)->for($vendorB)->create(['vendor_order_number' => 'VO-BETA']);

        app(MarketplaceNotifier::class)->orderPlaced($order);

        Notification::assertSentTo($customer, OrderPlacedNotification::class);
        Notification::assertSentTo($vendorA->user, VendorOrderCreatedNotification::class, function (VendorOrderCreatedNotification $notification) use ($vendorA, $vendorOrderA, $vendorOrderB): bool {
            $payload = $notification->toArray($vendorA->user);

            return str_contains($payload['message'], $vendorOrderA->vendor_order_number)
                && ! str_contains($payload['message'], $vendorOrderB->vendor_order_number);
        });
        Notification::assertSentTo($vendorB->user, VendorOrderCreatedNotification::class);
    }

    public function test_stale_refund_failure_does_not_notify_for_succeeded_refund(): void
    {
        Notification::fake();
        $refund = Refund::factory()->create([
            'status' => RefundStatus::Succeeded,
            'processed_at' => now(),
        ]);

        app(MarkRefundFailed::class)->handle($refund, 'stale_event', 'Stale provider event.');

        Notification::assertNothingSent();
        $this->assertSame(RefundStatus::Succeeded, $refund->refresh()->status);
    }

    private function storedNotification(User $user, string $title, string $actionUrl): string
    {
        $id = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'database',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'type' => 'test',
                'title' => $title,
                'message' => $title.' message.',
                'action_url' => $actionUrl,
                'severity' => 'info',
                'source_key' => 'test:'.Str::uuid(),
            ]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
