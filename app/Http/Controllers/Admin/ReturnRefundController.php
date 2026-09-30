<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReturnStatus;
use App\Http\Controllers\Controller;
use App\Models\Refund;
use App\Models\RefundAllocation;
use App\Models\ReturnRequest;
use App\Services\Refunds\RefundCalculator;
use App\Services\Refunds\RefundService;
use Illuminate\Http\Request;

class ReturnRefundController extends Controller
{
    public function create(ReturnRequest $return, RefundCalculator $calculator)
    {
        $return->load(['items.orderItem', 'order']);
        abort_unless($return->status === ReturnStatus::RefundPending, 422);
        $rows = [];
        foreach ($return->items->where('resolution', 'refund') as $item) {
            $used = RefundAllocation::where('return_item_id', $item->id)->whereHas('refund', fn ($q) => $q->whereNotIn('status', ['failed', 'cancelled']))->sum('quantity');
            $remaining = $item->inspected_quantity - $used;
            if ($remaining > 0) {
                $rows[] = ['item' => $item, 'quantity' => $remaining, 'quote' => $calculator->item($item->orderItem, $remaining, $item->id)];
            }
        }

        return view('admin.refunds.return', compact('return', 'rows'));
    }

    public function store(Request $request, ReturnRequest $return, RefundService $service)
    {
        $refund = $service->create($return->order, $request->user(), $request->all(), $return);
        $service->process($refund, $request->user());

        return redirect()->route('admin.refunds.show', $refund)->with('status', 'Refund recorded. External amounts remain pending provider.');
    }

    public function process(Request $request, Refund $refund, RefundService $service)
    {
        $service->process($refund, $request->user());

        return back()->with('status','Refund processing checked.');
    }
}
