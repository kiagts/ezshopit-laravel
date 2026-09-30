@extends('layouts.vendor')

@php
    use App\Enums\VendorOrderStatus;
    use App\Enums\RefundStatus;
    use App\Enums\RefundAllocationType;
    use App\Support\Countries;
    use App\Support\Money;
    use App\Services\Commissions\CommissionCalculator;
    use App\Services\Commissions\CommissionFinancials;

    $nextStatus = $vendorOrder->status->nextFulfillmentStatus();
    $nextRoute = match ($nextStatus) {
        VendorOrderStatus::Confirmed => 'vendor.orders.confirm',
        VendorOrderStatus::Processing => 'vendor.orders.processing',
        VendorOrderStatus::ReadyToShip => 'vendor.orders.ready-to-ship',
        VendorOrderStatus::Shipped => 'vendor.orders.ship',
        VendorOrderStatus::Delivered => 'vendor.orders.deliver',
        default => null,
    };
    $timeline = [
        ['label' => 'Order Received', 'status' => VendorOrderStatus::Pending, 'at' => $vendorOrder->created_at],
        ['label' => 'Confirmed', 'status' => VendorOrderStatus::Confirmed, 'at' => $vendorOrder->confirmed_at],
        ['label' => 'Processing', 'status' => VendorOrderStatus::Processing, 'at' => $vendorOrder->processing_at],
        ['label' => 'Ready to Ship', 'status' => VendorOrderStatus::ReadyToShip, 'at' => $vendorOrder->ready_to_ship_at],
        ['label' => 'Shipped', 'status' => VendorOrderStatus::Shipped, 'at' => $vendorOrder->shipped_at],
        ['label' => 'Delivered', 'status' => VendorOrderStatus::Delivered, 'at' => $vendorOrder->delivered_at],
    ];
    $commissionFinancials = app(CommissionFinancials::class);
    $commissionCalculator = app(CommissionCalculator::class);
@endphp

@section('title', $vendorOrder->vendor_order_number)

@section('content')
    <div class="mb-4"><x-button :href="route('vendor.documents.seller', $vendorOrder)" variant="secondary">{{ __('documents.download_seller') }}</x-button></div>
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <x-badge :tone="$vendorOrder->status->badgeTone()">{{ $vendorOrder->status->label() }}</x-badge>
            <h1 class="mt-4 text-2xl font-semibold tracking-normal text-neutral-950">{{ $vendorOrder->vendor_order_number }}</h1>
            <p class="mt-2 text-sm leading-6 text-neutral-600">Marketplace order {{ $vendorOrder->order->order_number }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-button :href="route('vendor.orders.index')" variant="secondary">All Orders</x-button>
            @if ($nextStatus && $nextRoute && ($fulfillmentEligibility->allowed ?? true))
                <form method="POST" action="{{ route($nextRoute, $vendorOrder) }}" @if(in_array($nextStatus, [VendorOrderStatus::Shipped, VendorOrderStatus::Delivered], true)) onsubmit="return confirm('Confirm this fulfillment action?')" @endif>
                    @csrf
                    <x-button type="submit">{{ $nextStatus->actionLabel() }}</x-button>
                </form>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    @if (! ($fulfillmentEligibility->allowed ?? true) && $nextStatus)
        <div class="mb-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ $fulfillmentEligibility->message ?? 'This seller order is waiting on payment before fulfillment can continue.' }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
        <div class="space-y-6">
            <section class="rounded-lg border border-neutral-200 bg-white shadow-soft">
                <div class="border-b border-neutral-100 p-5">
                    <h2 class="font-semibold text-neutral-950">Fulfillment Timeline</h2>
                </div>
                <div class="grid gap-3 p-5 sm:grid-cols-2">
                    @foreach ($timeline as $step)
                        <div class="rounded-md border {{ $step['at'] ? 'border-emerald-200 bg-emerald-50' : 'border-neutral-200 bg-white' }} p-4">
                            <p class="font-medium {{ $step['at'] ? 'text-emerald-950' : 'text-neutral-600' }}">{{ $step['label'] }}</p>
                            <p class="mt-1 text-xs {{ $step['at'] ? 'text-emerald-700' : 'text-neutral-400' }}">{{ $step['at']?->format('M j, Y g:i A') ?? 'Not reached' }}</p>
                        </div>
                    @endforeach
                    @if ($vendorOrder->status === VendorOrderStatus::Cancelled)
                        <div class="rounded-md border border-red-200 bg-red-50 p-4 sm:col-span-2">
                            <p class="font-medium text-red-950">Cancelled</p>
                            <p class="mt-1 text-xs text-red-700">{{ $vendorOrder->cancelled_at?->format('M j, Y g:i A') }}</p>
                            <p class="mt-2 text-sm text-red-800">{{ $vendorOrder->cancellation_reason }}</p>
                        </div>
                    @endif
                </div>
            </section>

            <section class="rounded-lg border border-neutral-200 bg-white shadow-soft">
                <div class="border-b border-neutral-100 p-5">
                    <h2 class="font-semibold text-neutral-950">Items</h2>
                </div>
                <div class="divide-y divide-neutral-100">
                    @foreach ($vendorOrder->items as $item)
                        <article class="grid gap-4 p-5 sm:grid-cols-[1fr_auto]">
                            <div>
                                <p class="font-semibold text-neutral-950">{{ $item->product_name }}</p>
                                @if ($item->variant_description)
                                    <p class="mt-1 text-sm text-neutral-600">{{ $item->variant_description }}</p>
                                @endif
                                <p class="mt-1 text-xs text-neutral-500">SKU: {{ $item->sku }}</p>
                                <p class="mt-2 text-sm text-neutral-600">Qty {{ $item->quantity }} · {{ Money::format($item->unit_price) }} each</p>
                            </div>
                            <p class="font-semibold text-neutral-950 sm:text-right">{{ Money::format($item->line_total) }}</p>
                        </article>
                    @endforeach
                </div>
            </section>
        </div>

        <aside class="space-y-6">
            @if ($vendorOrder->status->isCancellable())
                <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
                    <h2 class="font-semibold text-neutral-950">Cancel Seller Order</h2>
                    <form method="POST" action="{{ route('vendor.orders.cancel', $vendorOrder) }}" class="mt-4 space-y-3" onsubmit="return confirm('Cancel this seller order and release its reserved stock?')">
                        @csrf
                        <div>
                            <label for="reason" class="text-sm font-medium text-neutral-800">Reason</label>
                            <textarea id="reason" name="reason" rows="4" required maxlength="1000" class="mt-2 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm shadow-sm focus:border-neutral-950 focus:outline-none focus:ring-2 focus:ring-neutral-950/10" placeholder="Item unavailable, inventory discrepancy, or another fulfillment issue.">{{ old('reason') }}</textarea>
                        </div>
                        <x-button type="submit" variant="danger">Cancel Order</x-button>
                    </form>
                </section>
            @endif

            <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
                <h2 class="font-semibold text-neutral-950">Seller Total</h2>
                <div class="mt-4 grid gap-3 text-sm">
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Items</span><span class="font-medium">{{ Money::format($vendorOrder->items_subtotal) }}</span></div>
                    @if ($vendorOrder->discount_total > 0)
                        <div class="flex justify-between gap-4 text-emerald-700"><span>Customer coupon discount</span><span class="font-medium">-{{ Money::format($vendorOrder->discount_total) }}</span></div>
                    @endif
                    @if ($vendorOrder->vendor_discount_total > 0)
                        <div class="flex justify-between gap-4 text-red-700"><span>Vendor-funded discount</span><span class="font-medium">-{{ Money::format($vendorOrder->vendor_discount_total) }}</span></div>
                    @endif
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">{{ $vendorOrder->shipping_method_name }}</span><span class="font-medium">{{ $vendorOrder->shipping_total === 0 ? 'Free' : Money::format($vendorOrder->shipping_total) }}</span></div>
                    @if (($vendorRefundedAmount ?? 0) > 0)
                        <div class="flex justify-between gap-4 text-emerald-700"><span>Refund attributed</span><span class="font-medium">-{{ Money::format($vendorRefundedAmount) }}</span></div>
                    @endif
                    <div class="flex justify-between gap-4 border-t border-neutral-100 pt-3"><span class="font-semibold text-neutral-950">Total</span><span class="font-semibold">{{ Money::format($vendorOrder->grand_total) }}</span></div>
                    @if (($vendorRefundedAmount ?? 0) > 0)
                        <div class="flex justify-between gap-4"><span class="font-semibold text-neutral-950">Net order value</span><span class="font-semibold">{{ Money::format(max(0, $vendorOrder->grand_total - $vendorRefundedAmount)) }}</span></div>
                    @endif
                </div>
                @if ($vendorOrder->deliveryEstimate())
                    <p class="mt-3 text-xs text-neutral-500">{{ $vendorOrder->deliveryEstimate() }}</p>
                @endif
            </section>

            @php
                $sellerRefundAllocations = $vendorOrder->refundAllocations
                    ->filter(fn ($allocation) => $allocation->refund?->economicsRecognized());
            @endphp
            @if ($sellerRefundAllocations->isNotEmpty())
                <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
                    <h2 class="font-semibold text-neutral-950">Refund Attribution</h2>
                    <p class="mt-2 text-sm leading-6 text-neutral-600">These refunds are attributed to this seller order. Refund liability and commission credits are recorded in your earnings ledger; paid payouts remain unchanged.</p>
                    <div class="mt-4 space-y-3 text-sm">
                        @foreach ($sellerRefundAllocations as $allocation)
                            <div class="rounded-md border border-neutral-200 bg-neutral-50 p-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="font-medium text-neutral-950">
                                        @if ($allocation->type === RefundAllocationType::Item)
                                            {{ $allocation->orderItem?->product_name ?? 'Item refund' }}
                                        @elseif ($allocation->type === RefundAllocationType::Shipping)
                                            Shipping refunded
                                        @else
                                            Seller adjustment
                                        @endif
                                    </span>
                                    <span class="font-semibold text-emerald-700">{{ Money::format((int)($allocation->seller_amount ?? $allocation->amount), $vendorOrder->order->currency) }}</span>
                                </div>
                                @if ($allocation->quantity)
                                    <p class="mt-1 text-xs text-neutral-500">Qty refunded: {{ $allocation->quantity }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
                <h2 class="font-semibold text-neutral-950">Payment</h2>
                <div class="mt-4 grid gap-3 text-sm">
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Method</span><span class="text-right font-medium">{{ $vendorOrder->order->payment?->method->label() ?? 'Not recorded' }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Status</span><span class="text-right font-medium">{{ ($vendorOrder->order->payment?->status ?? $vendorOrder->order->payment_status)->label() }}</span></div>
                </div>
            </section>

            @if ($vendorOrder->commission)
                @php
                    $commissionSummary = $commissionFinancials->summaryFor($vendorOrder->commission);
                @endphp
                <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
                    <h2 class="font-semibold text-neutral-950">Marketplace Commission</h2>
                    <div class="mt-4 grid gap-3 text-sm">
                        <div class="flex justify-between gap-4"><span class="text-neutral-600">Commission rate</span><span class="font-medium">{{ $commissionCalculator->formatRate($vendorOrder->commission->rate_basis_points) }}</span></div>
                        <div class="flex justify-between gap-4"><span class="text-neutral-600">Marketplace commission</span><span class="font-medium">{{ Money::format($commissionSummary->originalCommissionAmount) }}</span></div>
                        <div class="flex justify-between gap-4"><span class="text-neutral-600">Refund adjustments</span><span class="font-medium">{{ Money::format($commissionSummary->commissionAdjustmentAmount) }}</span></div>
                        <div class="flex justify-between gap-4 border-t border-neutral-100 pt-3"><span class="font-semibold text-neutral-950">Net commission</span><span class="font-semibold">{{ Money::format($commissionSummary->netCommissionAmount) }}</span></div>
                        <div class="flex justify-between gap-4"><span class="font-semibold text-neutral-950">Net after commission</span><span class="font-semibold">{{ Money::format($commissionSummary->netAfterCommission) }}</span></div>
                    </div>
                </section>
            @endif

            <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
                <h2 class="font-semibold text-neutral-950">Delivery Snapshot</h2>
                <div class="mt-3 text-sm leading-6 text-neutral-600">
                    <p class="font-medium text-neutral-950">{{ $vendorOrder->order->shippingRecipientName() }}</p>
                    <p>{{ $vendorOrder->order->shipping_phone }}</p>
                    <p>{{ $vendorOrder->order->shipping_address_line_1 }}</p>
                    @if ($vendorOrder->order->shipping_address_line_2)<p>{{ $vendorOrder->order->shipping_address_line_2 }}</p>@endif
                    <p>{{ $vendorOrder->order->shipping_city }}@if($vendorOrder->order->shipping_state_region), {{ $vendorOrder->order->shipping_state_region }}@endif @if($vendorOrder->order->shipping_postal_code) {{ $vendorOrder->order->shipping_postal_code }}@endif</p>
                    <p>{{ Countries::name($vendorOrder->order->shipping_country_code) }}</p>
                </div>
            </section>
        </aside>
    </div>
@endsection
