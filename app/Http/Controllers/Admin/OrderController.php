<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Vendor;
use App\Services\Refunds\RefundFinancials;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
        ]);

        $search = $validated['q'] ?? null;

        return view('admin.orders.index', [
            'orders' => Order::query()
                ->with(['user', 'payment'])
                ->withCount('vendorOrders')
                ->when(filled($search), fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                    $query->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('vendorOrders', fn (Builder $query) => $query->where('vendor_order_number', 'like', "%{$search}%"))
                        ->orWhereHas('user', fn (Builder $query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                }))
                ->when(filled($validated['status'] ?? null), fn (Builder $query) => $query->where('status', $validated['status']))
                ->when(filled($validated['payment_status'] ?? null), fn (Builder $query) => $query->where('payment_status', $validated['payment_status']))
                ->when(filled($validated['vendor_id'] ?? null), fn (Builder $query) => $query->whereHas('vendorOrders', fn (Builder $query) => $query->where('vendor_id', $validated['vendor_id'])))
                ->latest('placed_at')
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'vendors' => Vendor::query()->orderBy('display_name')->get(['id', 'display_name']),
            'filters' => [
                'q' => $validated['q'] ?? '',
                'status' => $validated['status'] ?? '',
                'payment_status' => $validated['payment_status'] ?? '',
                'vendor_id' => $validated['vendor_id'] ?? '',
            ],
        ]);
    }

    public function show(Order $order, RefundFinancials $refundFinancials): View
    {
        $order->load([
            'walletTransactions',
            'user',
            'payment.refunds.allocations.vendorOrder.vendor',
            'payment.refunds.allocations.orderItem',
            'payment.attempts',
            'payment.webhookEvents',
            'vendorOrders.vendor',
            'vendorOrders.commission.adjustments',
            'vendorOrders.cancelledBy',
            'vendorOrders.refundAllocations.refund',
            'vendorOrders.items.refundAllocations.refund',
            'vendorOrders.items.reservation.inventory.movements',
        ]);

        return view('admin.orders.show', [
            'order' => $order,
            'financialSummary' => $order->payment ? $refundFinancials->summaryForPayment($order->payment) : null,
            'refundFinancials' => $refundFinancials,
        ]);
    }
}
