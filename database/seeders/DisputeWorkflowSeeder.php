<?php

namespace Database\Seeders;

use App\Enums\DisputeStatus;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\User;
use App\Services\Disputes\DisputeWorkflowService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DisputeWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $order = Order::where('order_number', 'DEMO-REFUND-PARTIAL')->first();
        $admin = User::where('role', 'admin')->orderBy('id')->first();
        if (! $order || ! $admin) {
            return;
        }
        $vo = $order->vendorOrders()->with('vendor.user', 'items')->first();
        if (! $vo) {
            return;
        }
        $cases = ['open' => 'other', 'under_review' => 'item_condition', 'awaiting_customer' => 'not_as_described', 'awaiting_vendor' => 'seller_disagreement', 'vendor_favored' => 'wrong_item', 'customer_refund' => 'refund_amount', 'closed' => 'item_not_received'];
        foreach ($cases as $target => $reason) {
            DB::transaction(function () use ($order, $admin, $vo, $target, $reason) {
                Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                $marker = 'Operational dispute demo: '.$target;
                if (Dispute::where('order_id', $order->id)->where('description', $marker)->exists()) {
                    return;
                }
                $w = app(DisputeWorkflowService::class);
                $extra = $target === 'customer_refund' ? ['refund_id' => $order->payment->refunds()->where('idempotency_key', 'demo-financial:partial')->value('id')] : [];
                $d = $w->open($order, $order->user, ['vendor_order_id' => $vo->id, 'reason' => $reason, 'description' => $marker] + $extra);
                if ($target === 'open') {
                    return;
                }
                $w->transition($d, $admin, DisputeStatus::UnderReview);
                if ($target === 'under_review') {
                    return;
                }
                if (in_array($target, ['awaiting_customer', 'awaiting_vendor'], true)) {
                    $w->transition($d, $admin, DisputeStatus::from($target), 'Please provide additional evidence for review.');

                    return;
                }
                if ($target === 'customer_refund') {
                    $w->resolve($d, $admin, ['resolution' => 'customer_favored_partial_refund', 'note' => 'Additional eligible quantity approved after review.', 'items' => [['id' => $vo->items->first()->id, 'quantity' => 1]]]);

                    return;
                }
                $w->message($d, $vo->vendor->user, 'Seller has supplied their explanation.');
                $w->resolve($d, $admin, ['resolution' => 'vendor_favored', 'note' => 'The evidence supports the seller decision.']);
                if ($target === 'closed') {
                    $w->transition($d,$admin,DisputeStatus::Closed,'Review complete.');
                }
            });
        }
    }
}
