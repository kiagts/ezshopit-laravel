<?php

namespace App\Http\Controllers\Account;

use App\Actions\Orders\CanCancelOrder;
use App\Actions\Orders\CancelOrder;
use App\Actions\Payments\CanRetryCardPayment;
use App\Enums\OrderCancellationSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\CancelOrderRequest;
use App\Models\Order;
use App\Services\Refunds\RefundFinancials;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.orders.index', [
            'orders' => $request->user()->orders()
                ->with('payment')
                ->withCount('vendorOrders')
                ->latest('placed_at')
                ->latest()
                ->paginate(10),
        ]);
    }

    public function show(Request $request, Order $order, CanRetryCardPayment $canRetryPayment, CanCancelOrder $canCancelOrder, RefundFinancials $refundFinancials): View
    {
        $this->authorizeOwned($request, $order);
        $order->load(['payment.refunds', 'vendorOrders.vendor', 'vendorOrders.items.reservation', 'vendorOrders.items.review', 'vendorOrders.items.product.reviews']);

        return view('account.orders.show', [
            'order' => $order,
            'paymentRetryEligibility' => $canRetryPayment->check($order),
            'orderCancellationEligibility' => $canCancelOrder->check($order, OrderCancellationSource::Customer),
            'financialSummary' => $order->payment ? $refundFinancials->summaryForPayment($order->payment) : null,
        ]);
    }

    public function success(Request $request, Order $order, CanRetryCardPayment $canRetryPayment, CanCancelOrder $canCancelOrder, RefundFinancials $refundFinancials): View
    {
        $this->authorizeOwned($request, $order);
        $order->load(['payment.refunds', 'vendorOrders.vendor', 'vendorOrders.items.reservation', 'vendorOrders.items.review', 'vendorOrders.items.product.reviews']);

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
