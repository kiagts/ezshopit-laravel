<?php

namespace App\Http\Controllers\Account;

use App\Actions\Orders\CanCancelOrder;
use App\Actions\Orders\CancelOrder;
use App\Actions\Payments\CanRetryCardPayment;
use App\Enums\OrderCancellationSource;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\CancelOrderRequest;
use App\Models\Order;
use App\Services\Refunds\RefundFinancials;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'filter' => ['nullable', 'string', 'in:all,active,completed,cancelled'],
        ]);
        $filter = $validated['filter'] ?? 'all';

        return view('account.orders.index', [
            'orders' => $request->user()->orders()
                ->with('payment')
                ->withCount('vendorOrders')
                ->when($filter === 'active', fn (Builder $query) => $query->whereIn('status', [
                    OrderStatus::Pending,
                    OrderStatus::Confirmed,
                    OrderStatus::Processing,
                ]))
                ->when($filter === 'completed', fn (Builder $query) => $query->where('status', OrderStatus::Completed))
                ->when($filter === 'cancelled', fn (Builder $query) => $query->where('status', OrderStatus::Cancelled))
                ->latest('placed_at')
                ->latest()
                ->paginate(10)
                ->withQueryString(),
            'filter' => $filter,
            'filters' => [
                'all' => 'All',
                'active' => 'Active',
                'completed' => 'Completed',
                'cancelled' => 'Cancelled',
            ],
        ]);
    }

    public function show(Request $request, Order $order, CanRetryCardPayment $canRetryPayment, CanCancelOrder $canCancelOrder, RefundFinancials $refundFinancials): View
    {
        $this->authorizeOwned($request, $order);
        $order->load(['payment.refunds.allocations', 'payment.refunds.fundingAllocations', 'vendorOrders.vendor', 'vendorOrders.items.reservation', 'vendorOrders.items.review', 'vendorOrders.items.product.reviews']);

        $order->load('walletTransactions');
        return view('account.orders.show', [
            'hasReturnableItems' => $order->vendorOrders->flatMap->items->contains(fn ($item) => app(\App\Services\Returns\ReturnEligibilityService::class)->check($item)['quantity'] > 0),
            'order' => $order,
            'paymentRetryEligibility' => $canRetryPayment->check($order),
            'orderCancellationEligibility' => $canCancelOrder->check($order, OrderCancellationSource::Customer),
            'financialSummary' => $order->payment ? $refundFinancials->summaryForPayment($order->payment) : null,
        ]);
    }

    public function success(Request $request, Order $order, CanRetryCardPayment $canRetryPayment, CanCancelOrder $canCancelOrder, RefundFinancials $refundFinancials): View
    {
        $this->authorizeOwned($request, $order);
        $order->load(['payment.refunds.allocations', 'payment.refunds.fundingAllocations', 'vendorOrders.vendor', 'vendorOrders.items.reservation', 'vendorOrders.items.review', 'vendorOrders.items.product.reviews']);

        $order->load('walletTransactions');
        return view('account.orders.success', [
            'order' => $order,
            'paymentRetryEligibility' => $canRetryPayment->check($order),
            'orderCancellationEligibility' => $canCancelOrder->check($order, OrderCancellationSource::Customer),
            'financialSummary' => $order->payment ? $refundFinancials->summaryForPayment($order->payment) : null,
        ]);
    }

    public function cancel(CancelOrderRequest $request, Order $order, CancelOrder $cancelOrder): RedirectResponse
    {
        $this->authorizeOwned($request, $order);

        $cancelOrder->handle($order, OrderCancellationSource::Customer, $request->validated('reason'));

        return redirect()
            ->route('account.orders.show', $order)
            ->with('status', 'Your unpaid order was cancelled and reserved items were released.');
    }

    private function authorizeOwned(Request $request, Order $order): void
    {
        abort_unless($order->user_id === $request->user()->id, 404);
    }
}
