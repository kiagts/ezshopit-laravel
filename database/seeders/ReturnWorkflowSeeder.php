<?php

namespace Database\Seeders;

use App\Enums\ReturnStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Services\Returns\ReturnEligibilityService;
use App\Services\Returns\ReturnWorkflowService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReturnWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $workflow = app(ReturnWorkflowService::class);
        $eligibility = app(ReturnEligibilityService::class);
        $targets = ['requested', 'awaiting_return', 'received', 'inspected', 'rejected', 'refund_pending'];
        foreach ($targets as $target) {
            $marker = "Operational return demo: {$target}";
            if (ReturnRequest::where('notes', $marker)->exists()) {
                continue;
            }
            // Only existing demo orders with actual delivery/quantity are eligible.
            $candidates = OrderItem::whereHas('vendorOrder.order', fn ($q) => $q->where('order_number', 'like', 'DEMO-LIFE-%'))
                ->with(['vendorOrder.order.user', 'vendorOrder.vendor.user'])->orderBy('id')->get();
            $item = $candidates->first(fn ($item) => $eligibility->check($item)['quantity'] > 0
                && $item->vendorOrder->vendor->isApproved()
                && ! ReturnRequest::whereHas('items', fn ($q) => $q->where('order_item_id', $item->id))->where('notes', 'like', 'Operational return demo:%')->exists());
            if (! $item) {
                $this->command?->warn("No unused eligible delivered demo item for {$target}; no order or stock was fabricated.");

                continue;
            }
            DB::transaction(function () use ($workflow, $marker, $item, $target) {
                $order = Order::whereKey($item->vendorOrder->order_id)->lockForUpdate()->firstOrFail();
                if (ReturnRequest::where('notes', $marker)->exists()) {
                    return;
                }
                $return = $workflow->request($order, $item->vendorOrder->order->user, [
                    'reason' => 'changed_mind', 'notes' => $marker, 'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
                ]);
                $actor = $item->vendorOrder->vendor->user;
                if ($target === 'requested') {
                    return;
                }
                if ($target === 'rejected') {
                    $workflow->transition($return, $actor, ReturnStatus::Rejected, 'Demo rejection after seller review.');

                    return;
                }
                $workflow->transition($return, $actor, ReturnStatus::Approved);
                $workflow->transition($return, $actor, ReturnStatus::AwaitingReturn);
                if ($target === 'awaiting_return') {
                    return;
                }
                $workflow->transition($return, $actor, ReturnStatus::Received);
                if ($target === 'received') {
                    return;
                }
                $workflow->inspect($return, $actor, ['items' => [[
                    'id' => $return->items()->firstOrFail()->id, 'condition' => 'restockable',
                    'inspected_quantity' => 1, 'resolution' => 'refund', 'inspection_note' => 'Demo inspection: original item received in sellable condition.',
                ]]]);
                if ($target === 'refund_pending') {
                    $workflow->transition($return, $actor, ReturnStatus::RefundPending);
                }
            });
        }
    }
}
