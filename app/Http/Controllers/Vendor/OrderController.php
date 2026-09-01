<?php

namespace App\Http\Controllers\Vendor;

use App\Actions\Orders\Fulfillment\TransitionVendorOrderStatus;
use App\Enums\VendorOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\CancelVendorOrderRequest;
use App\Models\VendorOrder;
use App\Services\Payments\CanFulfillVendorOrder;
use App\Services\Refunds\RefundFinancials;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:'.collect(VendorOrderStatus::cases())->pluck('value')->implode(',')],
        ]);

        $search = $validated['q'] ?? null;
        $status = isset($validated['status']) ? VendorOrderStatus::from($validated['status']) : null;
        $vendor = $request->user()->vendor;

        return view('vendor.orders.index', [
            'vendorOrders' => VendorOrder::query()
                ->with(['order.payment:id,order_id,method,status', 'items'])
                ->where('vendor_id', $vendor->id)
                ->when($status, fn (Builder $query) => $query->where('status', $status))
                ->when(filled($search), fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                    $query->where('vendor_order_number', 'like', "%{$search}%")
                        ->orWhereHas('order', fn (Builder $query) => $query->where('order_number', 'like', "%{$search}%"));
                }))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'filters' => [
                'q' => $validated['q'] ?? '',
                'status' => $validated['status'] ?? '',
            ],
            'statuses' => VendorOrderStatus::cases(),
        ]);
    }

    public function show(Request $request, VendorOrder $vendorOrder, CanFulfillVendorOrder $canFulfillVendorOrder, RefundFinancials $refundFinancials): View
    {
        $this->authorizeOwned($request, $vendorOrder);
        Gate::authorize('view', $vendorOrder);
        $vendorOrder->load(['order.payment:id,order_id,method,status', 'items.reservation', 'refundAllocations.refund', 'refundAllocations.orderItem', 'commission.adjustments']);

        return view('vendor.orders.show', [
            'vendorOrder' => $vendorOrder,
            'fulfillmentEligibility' => $canFulfillVendorOrder->check($vendorOrder),
            'vendorRefundedAmount' => $refundFinancials->successfulVendorRefundAmount($vendorOrder),
        ]);
    }

    public function confirm(Request $request, VendorOrder $vendorOrder, TransitionVendorOrderStatus $transition): RedirectResponse
    {
        return $this->transition($request, $vendorOrder, $transition, VendorOrderStatus::Confirmed, 'Vendor order confirmed.');
    }

    public function processing(Request $request, VendorOrder $vendorOrder, TransitionVendorOrderStatus $transition): RedirectResponse
    {
        return $this->transition($request, $vendorOrder, $transition, VendorOrderStatus::Processing, 'Vendor order moved to processing.');
    }

    public function readyToShip(Request $request, VendorOrder $vendorOrder, TransitionVendorOrderStatus $transition): RedirectResponse
    {
        return $this->transition($request, $vendorOrder, $transition, VendorOrderStatus::ReadyToShip, 'Vendor order marked ready to ship.');
    }

    public function ship(Request $request, VendorOrder $vendorOrder, TransitionVendorOrderStatus $transition): RedirectResponse
    {
        return $this->transition($request, $vendorOrder, $transition, VendorOrderStatus::Shipped, 'Vendor order shipped and inventory committed.');
    }

    public function deliver(Request $request, VendorOrder $vendorOrder, TransitionVendorOrderStatus $transition): RedirectResponse
    {
        return $this->transition($request, $vendorOrder, $transition, VendorOrderStatus::Delivered, 'Vendor order marked delivered.');
    }

    public function cancel(CancelVendorOrderRequest $request, VendorOrder $vendorOrder, TransitionVendorOrderStatus $transition): RedirectResponse
    {
        $this->authorizeOwned($request, $vendorOrder);
        Gate::authorize('cancel', $vendorOrder);

        $transition->handle($vendorOrder, VendorOrderStatus::Cancelled, $request->user(), $request->validated('reason'));

        return back()->with('status', 'Vendor order cancelled and reserved stock released.');
    }

    private function transition(Request $request, VendorOrder $vendorOrder, TransitionVendorOrderStatus $transition, VendorOrderStatus $target, string $message): RedirectResponse
    {
        $this->authorizeOwned($request, $vendorOrder);
        Gate::authorize($this->abilityFor($target), $vendorOrder);

        $transition->handle($vendorOrder, $target, $request->user());

        return back()->with('status', $message);
    }

    private function authorizeOwned(Request $request, VendorOrder $vendorOrder): void
    {
        abort_unless($vendorOrder->vendor_id === $request->user()->vendor?->id, 404);
    }

    private function abilityFor(VendorOrderStatus $target): string
    {
        return match ($target) {
            VendorOrderStatus::Confirmed => 'confirm',
            VendorOrderStatus::Processing => 'process',
            VendorOrderStatus::ReadyToShip => 'readyToShip',
            VendorOrderStatus::Shipped => 'ship',
            VendorOrderStatus::Delivered => 'deliver',
            VendorOrderStatus::Cancelled => 'cancel',
            VendorOrderStatus::Pending => 'view',
        };
    }
}
