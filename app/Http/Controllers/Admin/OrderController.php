<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Refunds\RefundFinancials;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
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
                ->latest('placed_at')
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'filters' => ['q' => $validated['q'] ?? ''],
        ]);
    }

    public function show(Order $order, RefundFinancials $refundFinancials): View
    {
        $order->load([
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
