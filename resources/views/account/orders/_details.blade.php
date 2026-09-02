@php
    use App\Enums\OrderStatus;
    use App\Enums\PaymentMethod;
    use App\Enums\RefundStatus;
    use App\Enums\VendorOrderStatus;
    use App\Models\VendorOrder as VendorOrderModel;
    use App\Support\Countries;
    use App\Support\Money;
    use App\Services\Commissions\CommissionCalculator;
    use App\Services\Commissions\CommissionFinancials;

    $adminView = $adminView ?? false;
    $orderCancellationEligibility = $orderCancellationEligibility ?? null;
    $financialSummary = $financialSummary ?? null;
    $refundFinancials = $refundFinancials ?? null;
    $commissionFinancials = app(CommissionFinancials::class);
    $commissionCalculator = app(CommissionCalculator::class);
@endphp

<div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
    <div class="space-y-6">
        @foreach ($order->vendorOrders as $vendorOrder)
            <section class="rounded-lg border border-neutral-200 bg-white shadow-soft">
                <div class="flex flex-col gap-3 border-b border-neutral-100 p-5 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Sold by</p>
                        <p class="mt-1 font-semibold text-neutral-950">{{ $vendorOrder->vendor?->display_name ?? 'Seller' }}</p>
                        <p class="mt-1 text-sm text-neutral-500">{{ $vendorOrder->vendor_order_number }}</p>
                        @if ($vendorOrder->status === VendorOrderStatus::Cancelled)
                            <p class="mt-2 text-sm text-neutral-600">
                                @if ($adminView)
                                    {{ $vendorOrder->cancellation_reason }}
                                @else
                                    This seller was unable to fulfill this part of your order.
                                @endif
                            </p>
                        @endif
                    </div>
                    <x-badge :tone="$vendorOrder->status->badgeTone()">{{ $vendorOrder->status->label() }}</x-badge>
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
                            @if ($adminView && $item->reservation)
                                <div class="rounded-md border border-neutral-100 bg-neutral-50 p-3 text-xs text-neutral-600 sm:col-span-2">
                                    <div class="flex flex-wrap gap-x-5 gap-y-1">
                                        <span>Reservation: <strong class="font-medium text-neutral-900">{{ $item->reservation->status->label() }}</strong></span>
                                        <span>Quantity: <strong class="font-medium text-neutral-900">{{ $item->reservation->quantity }}</strong></span>
                                        <span>Inventory: <strong class="font-medium text-neutral-900">#{{ $item->reservation->inventory_id }}</strong></span>
                                    </div>
                                    @php
                                        $movement = $item->reservation->inventory?->movements
                                            ?->first(fn ($movement) => $movement->reference_type === VendorOrderModel::class && (int) $movement->reference_id === (int) $vendorOrder->id);
                                    @endphp
                                    @if ($movement)
                                        <p class="mt-2">Movement {{ $movement->type->label() }}: {{ $movement->quantity }} units, {{ $movement->before_quantity }} to {{ $movement->after_quantity }}.</p>
                                    @endif
                                </div>
                            @endif
                            @php
                                $itemRefunded = $item->relationLoaded('refundAllocations')
                                    ? $item->refundAllocations->filter(fn ($allocation) => $allocation->refund?->status === RefundStatus::Succeeded)->sum('amount')
                                    : 0;
                            @endphp
                            @if ($itemRefunded > 0)
                                <p class="sm:col-span-2 text-xs font-medium text-emerald-700">Refunded from this item: {{ Money::format($itemRefunded) }}</p>
                            @endif
                            @if (! $adminView && $vendorOrder->status === VendorOrderStatus::Delivered && $item->product_id)
                                @php
                                    $existingReview = $item->review
                                        ?: $item->product?->reviews?->firstWhere('user_id', auth()->id());
                                @endphp
                                <div class="sm:col-span-2">
                                    <x-button
                                        :href="$existingReview ? route('account.reviews.edit', $existingReview) : route('account.order-items.reviews.create', $item)"
                                        variant="secondary"
                                        size="sm"
                                    >
                                        {{ $existingReview ? 'Edit Your Review' : 'Write a Review' }}
                                    </x-button>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
                @if ($adminView)
                    <div class="grid gap-2 border-t border-neutral-100 p-5 text-xs text-neutral-600 sm:grid-cols-2 lg:grid-cols-3">
                        <span>Confirmed: {{ $vendorOrder->confirmed_at?->format('M j, Y g:i A') ?? 'Not reached' }}</span>
                        <span>Processing: {{ $vendorOrder->processing_at?->format('M j, Y g:i A') ?? 'Not reached' }}</span>
                        <span>Ready: {{ $vendorOrder->ready_to_ship_at?->format('M j, Y g:i A') ?? 'Not reached' }}</span>
                        <span>Shipped: {{ $vendorOrder->shipped_at?->format('M j, Y g:i A') ?? 'Not reached' }}</span>
                        <span>Delivered: {{ $vendorOrder->delivered_at?->format('M j, Y g:i A') ?? 'Not reached' }}</span>
                        <span>Cancelled: {{ $vendorOrder->cancelled_at?->format('M j, Y g:i A') ?? 'Not reached' }}</span>
                        @if ($vendorOrder->cancelledBy)
                            <span>Cancelled by: {{ $vendorOrder->cancelledBy->name }}</span>
                        @endif
                    </div>
                @endif
                <div class="grid gap-2 border-t border-neutral-100 p-5 text-sm">
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Items</span><span class="font-medium">{{ Money::format($vendorOrder->items_subtotal) }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">{{ $vendorOrder->shipping_method_name }}</span><span class="font-medium">{{ $vendorOrder->shipping_total === 0 ? 'Free' : Money::format($vendorOrder->shipping_total) }}</span></div>
                    @if ($vendorOrder->deliveryEstimate())
                        <p class="text-xs text-neutral-500">{{ $vendorOrder->deliveryEstimate() }}</p>
                    @endif
                    @php
                        $vendorRefunded = $refundFinancials ? $refundFinancials->successfulVendorRefundAmount($vendorOrder) : 0;
                    @endphp
                    @if ($vendorRefunded > 0)
                        <div class="flex justify-between gap-4 text-emerald-700"><span>Refunded</span><span class="font-medium">-{{ Money::format($vendorRefunded) }}</span></div>
                    @endif
                    <div class="flex justify-between gap-4 border-t border-neutral-100 pt-2"><span class="font-semibold text-neutral-950">Seller total</span><span class="font-semibold">{{ Money::format($vendorOrder->grand_total) }}</span></div>
                    @if ($vendorRefunded > 0)
                        <div class="flex justify-between gap-4"><span class="font-semibold text-neutral-950">Net seller value</span><span class="font-semibold">{{ Money::format(max(0, $vendorOrder->grand_total - $vendorRefunded)) }}</span></div>
                    @endif
                    @if ($adminView && $vendorOrder->commission)
                        @php
                            $commissionSummary = $commissionFinancials->summaryFor($vendorOrder->commission);
                        @endphp
                        <div class="mt-2 rounded-md border border-neutral-200 bg-neutral-50 p-3">
                            <div class="flex justify-between gap-4"><span class="text-neutral-600">Applied commission rate</span><span class="font-medium">{{ $commissionCalculator->formatRate($vendorOrder->commission->rate_basis_points) }}</span></div>
                            <div class="mt-2 flex justify-between gap-4"><span class="text-neutral-600">Original commission</span><span class="font-medium">{{ Money::format($commissionSummary->originalCommissionAmount) }}</span></div>
                            <div class="mt-2 flex justify-between gap-4"><span class="text-neutral-600">Refund adjustments</span><span class="font-medium">{{ Money::format($commissionSummary->commissionAdjustmentAmount) }}</span></div>
                            <div class="mt-2 flex justify-between gap-4 border-t border-neutral-200 pt-2"><span class="font-semibold text-neutral-950">Net commission</span><span class="font-semibold">{{ Money::format($commissionSummary->netCommissionAmount) }}</span></div>
                            <a href="{{ route('admin.commissions.show', $vendorOrder->commission) }}" class="mt-2 inline-block text-xs font-medium text-brand-700 hover:text-brand-900">View commission record</a>
                        </div>
                    @endif
                </div>
            </section>
        @endforeach
    </div>

    <aside class="space-y-6">
        <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
            <h2 class="font-semibold text-neutral-950">Order Summary</h2>
            <div class="mt-4 grid gap-3 text-sm">
                <div class="flex justify-between gap-4"><span class="text-neutral-600">Items</span><span class="font-medium">{{ Money::format($order->items_subtotal) }}</span></div>
                <div class="flex justify-between gap-4"><span class="text-neutral-600">Shipping</span><span class="font-medium">{{ $order->shipping_total === 0 ? 'Free' : Money::format($order->shipping_total) }}</span></div>
                <div class="flex justify-between gap-4 border-t border-neutral-100 pt-3"><span class="font-semibold text-neutral-950">Total</span><span class="font-semibold">{{ Money::format($order->grand_total) }}</span></div>
                @if ($financialSummary && ($financialSummary->refundedAmount > 0 || $financialSummary->pendingRefundAmount > 0))
                    <div class="flex justify-between gap-4 text-emerald-700"><span>Refunded</span><span class="font-medium">-{{ Money::format($financialSummary->refundedAmount) }}</span></div>
                    @if ($financialSummary->pendingRefundAmount > 0)
                        <div class="flex justify-between gap-4 text-amber-700"><span>Refund processing</span><span class="font-medium">{{ Money::format($financialSummary->pendingRefundAmount) }}</span></div>
                    @endif
                    <div class="flex justify-between gap-4 border-t border-neutral-100 pt-3"><span class="font-semibold text-neutral-950">Net paid</span><span class="font-semibold">{{ Money::format($financialSummary->netPaidAmount()) }}</span></div>
                @endif
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <x-badge :tone="($order->payment?->status ?? $order->payment_status)->badgeTone()">Payment {{ ($order->payment?->status ?? $order->payment_status)->label() }}</x-badge>
                <x-badge tone="neutral">{{ $order->currency }}</x-badge>
            </div>
            @if ($order->status === OrderStatus::Cancelled)
                <div class="mt-5 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                    <p class="font-semibold text-red-950">Order Cancelled</p>
                    <p class="mt-1 leading-6">
                        @if ($adminView)
                            {{ $order->cancellation_reason ?? 'No cancellation reason recorded.' }}
                        @else
                            {{ $order->cancellation_source?->publicMessage() ?? 'This order was cancelled before payment was completed.' }}
                        @endif
                    </p>
                    @if ($adminView)
                        <div class="mt-3 grid gap-1 text-xs">
                            <span>Source: {{ $order->cancellation_source?->label() ?? 'Not recorded' }}</span>
                            <span>Cancelled: {{ $order->cancelled_at?->format('M j, Y g:i A') ?? 'Not recorded' }}</span>
                        </div>
                    @endif
                </div>
            @endif
        </section>

        <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
            <h2 class="font-semibold text-neutral-950">Payment</h2>
            <div class="mt-4 grid gap-3 text-sm">
                <div class="flex justify-between gap-4"><span class="text-neutral-600">Method</span><span class="text-right font-medium">{{ $order->payment?->method->label() ?? 'Not recorded' }}</span></div>
                <div class="flex justify-between gap-4"><span class="text-neutral-600">Status</span><span class="text-right font-medium">{{ ($order->payment?->status ?? $order->payment_status)->label() }}</span></div>
                <div class="flex justify-between gap-4"><span class="text-neutral-600">Amount</span><span class="text-right font-medium">{{ Money::format($order->payment?->amount ?? $order->grand_total) }}</span></div>
                @if ($financialSummary)
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Refunded</span><span class="text-right font-medium">{{ Money::format($financialSummary->refundedAmount) }}</span></div>
                    @if ($adminView)
                        <div class="flex justify-between gap-4"><span class="text-neutral-600">Pending refunds</span><span class="text-right font-medium">{{ Money::format($financialSummary->pendingRefundAmount) }}</span></div>
                        <div class="flex justify-between gap-4"><span class="text-neutral-600">Refundable</span><span class="text-right font-medium">{{ Money::format($financialSummary->refundableAmount()) }}</span></div>
                    @endif
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Net paid</span><span class="text-right font-medium">{{ Money::format($financialSummary->netPaidAmount()) }}</span></div>
                @endif
                @if ($order->payment?->method === PaymentMethod::Card)
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Expires</span><span class="text-right font-medium">{{ $order->payment->expires_at?->format('M j, Y g:i A') ?? 'No expiry recorded' }}</span></div>
                @endif
                @if ($adminView && $order->payment)
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Provider</span><span class="text-right font-medium">{{ $order->payment->provider }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Provider Reference</span><span class="text-right font-medium">{{ $order->payment->provider_reference ?? 'Not assigned' }}</span></div>
                    @if ($order->payment->requires_review_at)
                        <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-amber-900">
                            <p class="font-semibold">Payment requires review</p>
                            <p class="mt-1 leading-6">{{ $order->payment->review_reason }}</p>
                            @if ($order->payment->review_resolved_at)
                                <p class="mt-1 text-xs">Resolved {{ $order->payment->review_resolved_at->format('M j, Y g:i A') }}</p>
                            @endif
                        </div>
                    @endif
                @endif
            </div>

            @if ($order->payment?->refunds?->isNotEmpty())
                <div class="mt-5 rounded-md border border-neutral-200 bg-neutral-50 p-4">
                    <p class="text-sm font-semibold text-neutral-950">Refunds</p>
                    <div class="mt-3 space-y-3 text-xs text-neutral-600">
                        @foreach ($order->payment->refunds->whereIn('status', [RefundStatus::Pending, RefundStatus::Processing, RefundStatus::Succeeded]) as $refund)
                            <div class="rounded-md border border-neutral-200 bg-white p-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="font-medium text-neutral-950">{{ $refund->refund_number }}</span>
                                    <x-badge :tone="$refund->status->badgeTone()">{{ $adminView ? $refund->status->label() : $refund->status->customerLabel() }}</x-badge>
                                </div>
                                <div class="mt-2 grid gap-1">
                                    <span>{{ Money::format($refund->amount) }}</span>
                                    <span>{{ $refund->processed_at?->format('M j, Y g:i A') ?? $refund->requested_at?->format('M j, Y g:i A') }}</span>
                                    @if ($adminView)
                                        <span>Provider: {{ $refund->provider }} · {{ $refund->provider_reference ?? 'Pending' }}</span>
                                        <a href="{{ route('admin.refunds.show', $refund) }}" class="font-medium text-brand-700 hover:text-brand-900">View refund detail</a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($adminView && $order->payment && $financialSummary && $financialSummary->refundableAmount() > 0)
                <div class="mt-5 rounded-md border border-neutral-200 bg-neutral-50 p-4">
                    <p class="text-sm font-semibold text-neutral-950">Issue Refund</p>
                    <p class="mt-1 text-xs leading-5 text-neutral-600">Allocate the refund to items, seller shipping, or a marketplace adjustment. Provider and currency are derived from the payment.</p>
                    <form method="POST" action="{{ route('admin.orders.refunds.store', $order) }}" class="mt-4 space-y-4" onsubmit="return confirm('Issue this refund? Provider refunds may be irreversible.')">
                        @csrf
                        <div>
                            <label for="refund-reason" class="text-sm font-medium text-neutral-800">Reason</label>
                            <input id="refund-reason" name="reason" required maxlength="255" value="{{ old('reason') }}" class="mt-2 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm shadow-sm focus:border-neutral-950 focus:outline-none focus:ring-2 focus:ring-neutral-950/10" placeholder="Customer service adjustment, damaged item, or late payment after cancellation">
                            @error('reason')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="refund-notes" class="text-sm font-medium text-neutral-800">Internal notes</label>
                            <textarea id="refund-notes" name="notes" rows="3" maxlength="2000" class="mt-2 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm shadow-sm focus:border-neutral-950 focus:outline-none focus:ring-2 focus:ring-neutral-950/10" placeholder="Optional internal context.">{{ old('notes') }}</textarea>
                        </div>

                        @php $allocationIndex = 0; @endphp
                        <div class="space-y-3">
                            @foreach ($order->vendorOrders as $vendorOrder)
                                <div class="rounded-md border border-neutral-200 bg-white p-3">
                                    <p class="font-medium text-neutral-950">{{ $vendorOrder->vendor?->display_name ?? 'Seller' }}</p>
                                    @foreach ($vendorOrder->items as $item)
                                        <div class="mt-3 grid gap-2 text-xs sm:grid-cols-[1fr_5rem_7rem] sm:items-end">
                                            <input type="hidden" name="allocations[{{ $allocationIndex }}][type]" value="item">
                                            <input type="hidden" name="allocations[{{ $allocationIndex }}][order_item_id]" value="{{ $item->id }}">
                                            <div>
                                                <p class="font-medium text-neutral-800">{{ $item->product_name }}</p>
                                                <p class="text-neutral-500">Available {{ Money::format(max(0, $item->line_total - ($refundFinancials?->protectedItemAmount($item->id) ?? 0))) }} · Qty {{ $item->quantity - ($refundFinancials?->protectedItemQuantity($item->id) ?? 0) }}</p>
                                            </div>
                                            <input name="allocations[{{ $allocationIndex }}][quantity]" type="number" min="1" max="{{ $item->quantity }}" class="rounded-md border border-neutral-300 px-2 py-2 text-sm" placeholder="Qty">
                                            <input name="allocations[{{ $allocationIndex }}][amount]" class="rounded-md border border-neutral-300 px-2 py-2 text-sm" placeholder="0.00">
                                        </div>
                                        @php $allocationIndex++; @endphp
                                    @endforeach
                                    <div class="mt-3 grid gap-2 text-xs sm:grid-cols-[1fr_7rem] sm:items-end">
                                        <input type="hidden" name="allocations[{{ $allocationIndex }}][type]" value="shipping">
                                        <input type="hidden" name="allocations[{{ $allocationIndex }}][vendor_order_id]" value="{{ $vendorOrder->id }}">
                                        <div>
                                            <p class="font-medium text-neutral-800">Shipping</p>
                                            <p class="text-neutral-500">Available {{ Money::format(max(0, $vendorOrder->shipping_total - ($refundFinancials?->protectedShippingAmount($vendorOrder->id) ?? 0))) }}</p>
                                        </div>
                                        <input name="allocations[{{ $allocationIndex }}][amount]" class="rounded-md border border-neutral-300 px-2 py-2 text-sm" placeholder="0.00">
                                    </div>
                                    @php $allocationIndex++; @endphp
                                </div>
                            @endforeach
                            <div class="rounded-md border border-neutral-200 bg-white p-3">
                                <div class="grid gap-2 text-xs sm:grid-cols-[1fr_7rem] sm:items-end">
                                    <input type="hidden" name="allocations[{{ $allocationIndex }}][type]" value="adjustment">
                                    <div>
                                        <p class="font-medium text-neutral-800">Marketplace adjustment</p>
                                        <p class="text-neutral-500">Use only for explicit financial adjustments not tied to item or shipping.</p>
                                    </div>
                                    <input name="allocations[{{ $allocationIndex }}][amount]" class="rounded-md border border-neutral-300 px-2 py-2 text-sm" placeholder="0.00">
                                </div>
                            </div>
                        </div>

                        @if ($errors->any())
                            <p class="rounded-md border border-red-200 bg-red-50 p-3 text-xs text-red-800">{{ $errors->first() }}</p>
                        @endif
                        <x-button type="submit" variant="danger" size="sm">Issue Refund</x-button>
                    </form>
                </div>
            @endif

            @if (! $adminView && $order->payment?->method === PaymentMethod::Card && isset($paymentRetryEligibility))
                <div class="mt-5 rounded-md border {{ $paymentRetryEligibility->allowed ? 'border-brand-200 bg-brand-50' : 'border-neutral-200 bg-neutral-50' }} p-4 text-sm">
                    <p class="font-semibold text-neutral-950">Card Payment</p>
                    <p class="mt-1 leading-6 {{ $paymentRetryEligibility->allowed ? 'text-brand-900' : 'text-neutral-600' }}">
                        {{ $paymentRetryEligibility->allowed ? 'Complete secure card payment to release this order for fulfillment.' : $paymentRetryEligibility->message }}
                    </p>
                    @if ($paymentRetryEligibility->allowed)
                        <form method="POST" action="{{ route('account.orders.payment.retry', $order) }}" class="mt-4 space-y-3" data-stripe-retry data-stripe-key="{{ config('payments.stripe.public_key') }}">
                            @csrf
                            <x-button type="submit" size="sm" data-stripe-retry-submit>Retry Card Payment</x-button>
                            <div class="hidden space-y-3" data-stripe-retry-panel>
                                <div class="rounded-md border border-neutral-200 bg-white p-3">
                                    <div data-stripe-retry-element></div>
                                </div>
                                <button type="button" data-stripe-retry-confirm class="inline-flex h-10 items-center justify-center rounded-md border border-neutral-950 bg-neutral-950 px-3 text-sm font-semibold text-white shadow-sm transition hover:bg-neutral-800">
                                    Complete Card Payment
                                </button>
                            </div>
                            <p class="text-xs leading-5 text-neutral-500" data-stripe-retry-message>Stripe securely collects card details. This page never receives raw card data.</p>
                        </form>
                    @endif
                </div>
            @endif

            @if (! $adminView && $orderCancellationEligibility?->allowed)
                <div class="mt-5 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                    <p class="font-semibold text-red-950">Cancel Unpaid Order</p>
                    <p class="mt-1 leading-6">Your reserved items will be released and this order cannot be restored.</p>
                    <form method="POST" action="{{ route('account.orders.cancel', $order) }}" class="mt-4 space-y-3" onsubmit="return confirm('Cancel this unpaid order? Your reserved items will be released and this order cannot be restored.')">
                        @csrf
                        <label for="cancel-reason-{{ $order->id }}" class="block text-sm font-medium text-red-950">Reason <span class="font-normal text-red-700">(optional)</span></label>
                        <textarea id="cancel-reason-{{ $order->id }}" name="reason" rows="3" maxlength="500" class="w-full rounded-md border border-red-200 bg-white px-3 py-2 text-sm text-neutral-950 shadow-sm focus:border-red-700 focus:outline-none focus:ring-2 focus:ring-red-700/10" placeholder="Changed my mind, ordered by mistake, or another reason.">{{ old('reason') }}</textarea>
                        <x-button type="submit" variant="danger" size="sm">Cancel Order</x-button>
                    </form>
                </div>
            @endif

            @if ($adminView && $order->payment)
                <div class="mt-5 rounded-md border border-neutral-200 bg-neutral-50 p-4">
                    <p class="text-sm font-semibold text-neutral-950">Payment Attempts</p>
                    <div class="mt-3 space-y-3 text-xs text-neutral-600">
                        @forelse ($order->payment->attempts as $attempt)
                            <div class="rounded-md border border-neutral-200 bg-white p-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="font-medium text-neutral-950">Attempt {{ $attempt->attempt_number }}</span>
                                    <x-badge :tone="$attempt->status->badgeTone()">{{ $attempt->status->label() }}</x-badge>
                                </div>
                                <div class="mt-2 grid gap-1">
                                    <span>Provider reference: {{ $attempt->provider_reference ?? 'Not assigned' }}</span>
                                    @if ($attempt->failure_code || $attempt->failure_message)
                                        <span>Failure: {{ $attempt->failure_code ?? 'unknown' }} {{ $attempt->failure_message ? '- '.$attempt->failure_message : '' }}</span>
                                    @endif
                                    <span>Started: {{ $attempt->started_at?->format('M j, Y g:i A') ?? 'Not recorded' }}</span>
                                    <span>Completed: {{ $attempt->completed_at?->format('M j, Y g:i A') ?? 'Not completed' }}</span>
                                </div>
                            </div>
                        @empty
                            <p>No payment attempts recorded.</p>
                        @endforelse
                    </div>
                </div>

                <div class="mt-5 rounded-md border border-neutral-200 bg-neutral-50 p-4">
                    <p class="text-sm font-semibold text-neutral-950">Webhook Events</p>
                    <div class="mt-3 space-y-2 text-xs text-neutral-600">
                        @forelse ($order->payment->webhookEvents as $event)
                            <div class="flex flex-wrap justify-between gap-2 rounded-md border border-neutral-200 bg-white p-3">
                                <span>{{ $event->event_type }}</span>
                                <span>{{ $event->processed_at?->format('M j, Y g:i A') ?? 'Pending' }}</span>
                            </div>
                        @empty
                            <p>No Stripe webhook events recorded.</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </section>

        <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
            <h2 class="font-semibold text-neutral-950">Delivery Address</h2>
            <div class="mt-3 text-sm leading-6 text-neutral-600">
                <p class="font-medium text-neutral-950">{{ $order->shippingRecipientName() }}</p>
                <p>{{ $order->shipping_phone }}</p>
                <p>{{ $order->shipping_address_line_1 }}</p>
                @if ($order->shipping_address_line_2)<p>{{ $order->shipping_address_line_2 }}</p>@endif
                <p>{{ $order->shipping_city }}@if($order->shipping_state_region), {{ $order->shipping_state_region }}@endif @if($order->shipping_postal_code) {{ $order->shipping_postal_code }}@endif</p>
                <p>{{ Countries::name($order->shipping_country_code) }}</p>
            </div>
        </section>
    </aside>
</div>

@if (! $adminView && $order->payment?->method === PaymentMethod::Card && isset($paymentRetryEligibility) && $paymentRetryEligibility->allowed)
    @push('scripts')
        <script src="https://js.stripe.com/v3/"></script>
        <script>
            (() => {
                const form = document.querySelector('[data-stripe-retry]');

                if (! form || ! window.Stripe) {
                    return;
                }

                const submit = form.querySelector('[data-stripe-retry-submit]');
                const panel = form.querySelector('[data-stripe-retry-panel]');
                const elementTarget = form.querySelector('[data-stripe-retry-element]');
                const confirmButton = form.querySelector('[data-stripe-retry-confirm]');
                const message = form.querySelector('[data-stripe-retry-message]');
                let stripe = null;
                let elements = null;

                const setMessage = (text, isError = false) => {
                    message.textContent = text;
                    message.classList.toggle('text-red-700', isError);
                    message.classList.toggle('text-neutral-500', ! isError);
                };

                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    submit.disabled = true;
                    setMessage('Preparing secure card payment...');

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: new FormData(form),
                        });
                        const data = await response.json();

                        if (! response.ok || ! data.client_secret) {
                            throw new Error(data.message || 'Card payment could not be prepared.');
                        }

                        stripe = Stripe(data.publishable_key || form.dataset.stripeKey);
                        elements = stripe.elements({ clientSecret: data.client_secret });
                        elements.create('payment').mount(elementTarget);
                        panel.classList.remove('hidden');
                        submit.classList.add('hidden');
                        setMessage('Enter your card details securely, then complete payment.');

                        confirmButton.onclick = async () => {
                            confirmButton.disabled = true;
                            setMessage('Confirming payment with Stripe...');

                            const result = await stripe.confirmPayment({
                                elements,
                                confirmParams: { return_url: data.return_url },
                            });

                            if (result.error) {
                                confirmButton.disabled = false;
                                setMessage(result.error.message || 'Payment was not completed.', true);
                            }
                        };
                    } catch (error) {
                        submit.disabled = false;
                        setMessage(error.message || 'Card payment could not be prepared.', true);
                    }
                });
            })();
        </script>
    @endpush
@endif
