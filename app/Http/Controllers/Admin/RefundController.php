<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Refunds\CreateRefund;
use App\Actions\Refunds\ProcessRefund;
use App\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateRefundRequest;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Vendor;
use App\Services\Refunds\RefundFinancials;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RefundController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:'.collect(RefundStatus::cases())->pluck('value')->implode(',')],
            'provider' => ['nullable', 'string', 'max:80'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $search = $validated['q'] ?? null;

        return view('admin.refunds.index', [
            'refunds' => Refund::query()
                ->with(['order.user', 'payment', 'returnRequest', 'allocations.vendorOrder.vendor'])
                ->when(filled($search), fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                    $query->where('refund_number', 'like', "%{$search}%")
                        ->orWhereHas('order', fn (Builder $query) => $query->where('order_number', 'like', "%{$search}%")
                            ->orWhereHas('user', fn (Builder $query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")));
                }))
                ->when(filled($validated['status'] ?? null), fn (Builder $query) => $query->where('status', $validated['status']))
                ->when(filled($validated['provider'] ?? null), fn (Builder $query) => $query->where('provider', $validated['provider']))
                ->when(filled($validated['vendor_id'] ?? null), fn (Builder $query) => $query->whereHas('allocations.vendorOrder', fn (Builder $query) => $query->where('vendor_id', $validated['vendor_id'])))
                ->when($validated['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('requested_at', '>=', $date))
                ->when($validated['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('requested_at', '<=', $date))
                ->latest('requested_at')
                ->paginate(20)
                ->withQueryString(),
            'vendors' => Vendor::query()->orderBy('display_name')->get(['id', 'display_name']),
            'filters' => [
                'q' => $validated['q'] ?? '',
                'status' => $validated['status'] ?? '',
                'provider' => $validated['provider'] ?? '',
                'vendor_id' => $validated['vendor_id'] ?? '',
                'date_from' => $validated['date_from'] ?? '',
                'date_to' => $validated['date_to'] ?? '',
            ],
        ]);
    }

    public function store(CreateRefundRequest $request, Order $order): RedirectResponse
    {
        $items = []; $shipping = []; $adjustment = 0;
        foreach ($request->validated('allocations') as $allocation) {
            if ($allocation['type'] === 'item' && ($allocation['amount'] ?? 0) > 0) {
                $items[] = ['id'=>$allocation['order_item_id'], 'quantity'=>$allocation['quantity'] ?? 1];
            } elseif ($allocation['type'] === 'shipping' && ($allocation['amount'] ?? 0) > 0) {
                $shipping[] = ['vendor_order_id'=>$allocation['vendor_order_id'],'amount'=>$allocation['amount']];
            } elseif ($allocation['type'] === 'adjustment') $adjustment += $allocation['amount'] ?? 0;
        }
        $service = app(\App\Services\Refunds\RefundService::class);
        $refund = $service->create($order, $request->user(), [
            'key'=>$request->validated('idempotency_key') ?: 'manual:'.\Illuminate\Support\Str::uuid(),
            'reason'=>$request->validated('reason'), 'items'=>$items, 'shipping'=>$shipping, 'adjustment'=>$adjustment,
            'notes'=>$request->validated('notes'),
        ]);
        $service->process($refund, $request->user());
        $message = 'Refund recorded. Any external portion is awaiting a payment provider.';

        return redirect()->route('admin.refunds.show', $refund)->with('status', $message);
    }

    public function show(Refund $refund, RefundFinancials $refundFinancials): View
    {
        return view('admin.refunds.show', [
            'refund' => $refund->load([
                'order.user',
                'payment',
                'requestedBy',
                'allocations.vendorOrder.vendor',
                'allocations.orderItem',
                'allocations.commissionAdjustment',
                'returnRequest',
                'fundingAllocations',
            ]),
            'financialSummary' => $refundFinancials->summaryForPayment($refund->payment),
        ]);
    }
}
