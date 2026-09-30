<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (\App\Models\Dispute::with(['opener','vendorOrder.vendor.user'])->where('description','like','Operational dispute demo:%')->get() as $dispute) {
            foreach (collect([$dispute->opener,$dispute->vendorOrder?->vendor?->user])->filter() as $recipient) {
                $prefix = $recipient->isCustomer() ? 'account' : 'vendor';
                $this->notification($recipient, 'Dispute update', $dispute->dispute_number.': '.$dispute->status->label(), route($prefix.'.disputes.show',$dispute,false), 'dispute_update', 'info', 'demo:notifications:dispute:'.$dispute->id.':'.$recipient->id, now());
            }
        }
        foreach (\App\Models\Refund::where('status','partially_processed')->get() as $refund) {
            foreach (User::where('role','admin')->get() as $recipient) {
                $this->notification($recipient, 'External refund pending', $refund->refund_number.' requires original payment provider processing.', route('admin.refunds.show',$refund,false), 'refund_external_pending', 'warning', 'demo:notifications:pending-refund:'.$refund->id.':'.$recipient->id, now());
            }
        }
        $customer = User::query()->where('email', 'customer@example.com')->first();
        $vendor = User::query()->where('email', 'vendor@example.com')->first();
        $admin = User::query()->where('email', 'admin@example.com')->first();

        if ($customer) {
            $this->notification($customer, 'Order placed', 'Your demo order has been placed.', '/account/orders', 'order_placed', 'success', 'demo:notifications:customer:order-placed', now()->subHours(6));
            $this->notification($customer, 'Order shipped', 'A seller order from TechVerse is on the way.', '/account/orders', 'vendor_order_shipped', 'success', 'demo:notifications:customer:order-shipped', now()->subHours(3));
            $this->notification($customer, 'Refund completed', 'A demo refund was completed for your account.', '/account/orders', 'refund_succeeded', 'info', 'demo:notifications:customer:refund', now()->subDay(), now()->subHours(12));
        }

        if ($vendor) {
            $this->notification($vendor, 'New seller order', 'You received a demo seller order.', '/vendor/orders', 'vendor_order_created', 'success', 'demo:notifications:vendor:new-order', now()->subHours(5));
            $this->notification($vendor, 'New product review', 'A customer submitted a review for one of your products.', '/vendor/reviews', 'product_review_submitted', 'info', 'demo:notifications:vendor:review', now()->subHours(4));
            $this->notification($vendor, 'Payout paid', 'A demo payout was marked paid.', '/vendor/payouts', 'payout_paid', 'success', 'demo:notifications:vendor:payout-paid', now()->subDays(2), now()->subDay());
        }

        if ($admin) {
            $this->notification($admin, 'New payout request', 'TechVerse requested a demo payout.', '/admin/payouts', 'payout_requested', 'warning', 'demo:notifications:admin:payout-request', now()->subHours(2));
            $this->notification($admin, 'Product awaiting approval', 'A demo product is waiting for moderation.', '/admin/products', 'product_submitted', 'warning', 'demo:notifications:admin:product-submitted', now()->subHours(8));
            $this->notification($admin, 'Payment requires review', 'A late payment success requires review.', '/admin/payments', 'payment_requires_review', 'warning', 'demo:notifications:admin:payment-review', now()->subDay());
        }
    }

    private function notification(User $user, string $title, string $message, string $url, string $type, string $severity, string $sourceKey, mixed $createdAt, mixed $readAt = null): void
    {
        if (DB::table('notification_dispatches')->where('notifiable_id', $user->id)->where('source_key', $sourceKey)->exists()) return;
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'database',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'action_url' => $url,
                'severity' => $severity,
                'source_key' => $sourceKey,
            ]),
            'read_at' => $readAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        DB::table('notification_dispatches')->insert([
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'notification_type' => $type,
            'source_key' => $sourceKey,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}

