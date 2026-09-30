@php
    use App\Support\Money;

    $urgentTotal = array_sum($counters);

    $attentionCards = [
        ['label' => 'Vendors Awaiting Approval', 'value' => $counters['pendingVendors'], 'href' => route('admin.vendors.index', ['status' => 'pending']), 'tone' => 'warning'],
        ['label' => 'Products Awaiting Approval', 'value' => $counters['pendingProducts'], 'href' => route('admin.products.index', ['approval' => 'pending']), 'tone' => 'warning'],
        ['label' => 'Reviews Pending', 'value' => $counters['pendingReviews'], 'href' => route('admin.reviews.index', ['status' => 'pending']), 'tone' => 'warning'],
        ['label' => 'Payments Requiring Review', 'value' => $counters['paymentReviews'], 'href' => route('admin.payments.index', ['review' => 'required']), 'tone' => 'danger'],
        ['label' => 'Refunds Requiring Attention', 'value' => $counters['refundsRequiringAttention'], 'href' => route('admin.refunds.index'), 'tone' => 'brand'],
        ['label' => 'Payouts Awaiting Action', 'value' => $counters['payoutsAwaitingAction'], 'href' => route('admin.payouts.index'), 'tone' => 'brand'],
    ];
@endphp

@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <x-badge tone="brand">Marketplace operations</x-badge>
            <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Admin Dashboard</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-neutral-600">
                Review the queues that need marketplace action and jump into the existing domain workflows for moderation, payments, refunds, finance, and seller operations.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-button :href="route('admin.notifications.index')" variant="secondary">Notifications</x-button>
            <x-button :href="route('admin.orders.index')" variant="secondary">Orders</x-button>
        </div>
    </div>

    @if ($urgentTotal === 0)
        <x-alert class="mb-6">No urgent actions right now.</x-alert>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($attentionCards as $card)
            <a href="{{ $card['href'] }}" class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft transition hover:border-brand-200">
                <div class="flex items-center justify-between gap-4">
                    <p class="text-sm font-medium text-neutral-500">{{ $card['label'] }}</p>
                    <x-badge :tone="$card['tone']">{{ $card['value'] > 0 ? 'Action' : 'Clear' }}</x-badge>
                </div>
                <p class="mt-3 text-3xl font-semibold text-neutral-950">{{ number_format($card['value']) }}</p>
            </a>
        @endforeach
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-card>
            <p class="text-sm font-medium text-neutral-500">Approved Vendors</p>
            <p class="mt-3 text-2xl font-semibold text-neutral-950">{{ number_format($marketplace['approvedVendors']) }}</p>
        </x-card>
        <x-card>
            <p class="text-sm font-medium text-neutral-500">Active Products</p>
            <p class="mt-3 text-2xl font-semibold text-neutral-950">{{ number_format($marketplace['activeProducts']) }}</p>
        </x-card>
        <x-card>
            <p class="text-sm font-medium text-neutral-500">Orders Today</p>
            <p class="mt-3 text-2xl font-semibold text-neutral-950">{{ number_format($marketplace['ordersToday']) }}</p>
        </x-card>
        <x-card>
            <p class="text-sm font-medium text-neutral-500">Gross Order Value</p>
            <p class="mt-3 text-2xl font-semibold text-neutral-950">{{ Money::format($marketplace['grossOrderValue'], $currency) }}</p>
            <p class="mt-1 text-xs text-neutral-500">Immutable order snapshots, not revenue.</p>
        </x-card>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(22rem,0.65fr)]">
        <section class="rounded-lg border border-neutral-200 bg-white shadow-soft">
            <div class="flex items-center justify-between gap-4 border-b border-neutral-100 p-5">
                <div>
                    <h2 class="font-semibold text-neutral-950">Moderation Queues</h2>
                    <p class="mt-1 text-sm text-neutral-600">Open detail pages for context before approving, rejecting, or suspending.</p>
                </div>
            </div>

            <div class="grid gap-0 divide-y divide-neutral-100 lg:grid-cols-3 lg:divide-x lg:divide-y-0">
                <div class="p-5">
                    <div class="flex items-center justify-between gap-4">
                        <h3 class="font-semibold text-neutral-950">Vendor Applications</h3>
                        <x-button :href="route('admin.vendors.index', ['status' => 'pending'])" variant="secondary" size="sm">Review</x-button>
                    </div>
                    <div class="mt-4 space-y-3">
                        @forelse ($vendorQueue as $vendor)
                            <a href="{{ route('admin.vendors.show', $vendor) }}" class="block rounded-md border border-neutral-200 bg-neutral-50 p-3 text-sm hover:border-brand-200">
                                <span class="font-semibold text-neutral-950">{{ $vendor->display_name }}</span>
                                <span class="mt-1 block text-neutral-500">{{ $vendor->user?->name }} · {{ $vendor->submitted_at?->format('M j, Y') ?? 'Not submitted' }}</span>
                            </a>
                        @empty
                            <p class="rounded-md bg-neutral-50 p-3 text-sm text-neutral-600">No vendor applications waiting.</p>
                        @endforelse
                    </div>
                </div>

                <div class="p-5">
                    <div class="flex items-center justify-between gap-4">
                        <h3 class="font-semibold text-neutral-950">Product Submissions</h3>
                        <x-button :href="route('admin.products.index', ['approval' => 'pending'])" variant="secondary" size="sm">Review</x-button>
                    </div>
                    <div class="mt-4 space-y-3">
                        @forelse ($productQueue as $product)
                            <a href="{{ route('admin.products.show', $product) }}" class="block rounded-md border border-neutral-200 bg-neutral-50 p-3 text-sm hover:border-brand-200">
                                <span class="font-semibold text-neutral-950">{{ $product->name }}</span>
                                <span class="mt-1 block text-neutral-500">{{ $product->vendor?->display_name }} · {{ $product->submitted_at?->format('M j, Y') ?? 'Not submitted' }}</span>
                            </a>
                        @empty
                            <p class="rounded-md bg-neutral-50 p-3 text-sm text-neutral-600">No products waiting for review.</p>
                        @endforelse
                    </div>
                </div>

                <div class="p-5">
                    <div class="flex items-center justify-between gap-4">
                        <h3 class="font-semibold text-neutral-950">Review Moderation</h3>
                        <x-button :href="route('admin.reviews.index', ['status' => 'pending'])" variant="secondary" size="sm">Review</x-button>
                    </div>
                    <div class="mt-4 space-y-3">
                        @forelse ($reviewQueue as $review)
                            <a href="{{ route('admin.reviews.show', $review) }}" class="block rounded-md border border-neutral-200 bg-neutral-50 p-3 text-sm hover:border-brand-200">
                                <span class="font-semibold text-neutral-950">{{ $review->product?->name ?? 'Product unavailable' }}</span>
                                <span class="mt-1 block text-neutral-500">{{ $review->rating }}/5 · {{ $review->reviewer_display_name }}</span>
                            </a>
                        @empty
                            <p class="rounded-md bg-neutral-50 p-3 text-sm text-neutral-600">No reviews waiting for moderation.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        <aside class="space-y-6">
            <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
                <h2 class="font-semibold text-neutral-950">Finance Snapshot</h2>
                <p class="mt-1 text-sm text-neutral-600">Commission and payout values from authoritative financial records.</p>
                <div class="mt-4 grid gap-3 text-sm">
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Recognized Commission</span><span class="font-semibold text-neutral-950">{{ Money::format($finance['recognizedCommission'], $currency) }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Commission Adjustments</span><span class="font-semibold text-neutral-950">{{ Money::format($finance['commissionAdjustments'], $currency) }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Net Commission</span><span class="font-semibold text-neutral-950">{{ Money::format($finance['netCommission'], $currency) }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Ledger Payouts</span><span class="font-semibold text-neutral-950">{{ Money::format($finance['ledgerPayouts'], $currency) }}</span></div>
                </div>
                <div class="mt-5 flex flex-wrap gap-2">
                    <x-button :href="route('admin.commissions.index')" variant="secondary" size="sm">Commissions</x-button>
                    <x-button :href="route('admin.vendor-ledger.index')" variant="secondary" size="sm">Vendor Ledger</x-button>
                </div>
            </section>

            <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
                <h2 class="font-semibold text-neutral-950">Operational Notifications</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($latestNotifications as $notification)
                        <a href="{{ route('admin.notifications.open', $notification) }}" class="block rounded-md border border-neutral-200 bg-neutral-50 p-3 text-sm hover:border-brand-200">
                            <span class="font-semibold text-neutral-950">{{ $notification->data['title'] ?? 'Notification' }}</span>
                            <span class="mt-1 line-clamp-2 block text-neutral-600">{{ $notification->data['message'] ?? '' }}</span>
                        </a>
                    @empty
                        <p class="rounded-md bg-neutral-50 p-3 text-sm text-neutral-600">No admin notifications yet.</p>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <section class="rounded-lg border border-neutral-200 bg-white shadow-soft">
            <div class="flex items-center justify-between gap-4 border-b border-neutral-100 p-5">
                <h2 class="font-semibold text-neutral-950">Payment Review Queue</h2>
                <x-button :href="route('admin.payments.index', ['review' => 'required'])" variant="secondary" size="sm">Open</x-button>
            </div>
            <div class="divide-y divide-neutral-100">
                @forelse ($paymentReviewQueue as $payment)
                    <a href="{{ route('admin.payments.show', $payment) }}" class="block p-5 text-sm hover:bg-neutral-50">
                        <span class="font-semibold text-neutral-950">{{ $payment->order?->order_number }}</span>
                        <span class="mt-1 block text-neutral-600">{{ Money::format($payment->amount, $payment->currency) }} · {{ $payment->provider }}</span>
                        <span class="mt-1 block text-xs text-amber-700">{{ $payment->review_reason }}</span>
                    </a>
                @empty
                    <p class="p-5 text-sm text-neutral-600">No payments require review.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-lg border border-neutral-200 bg-white shadow-soft">
            <div class="flex items-center justify-between gap-4 border-b border-neutral-100 p-5">
                <h2 class="font-semibold text-neutral-950">Refund Queue</h2>
                <x-button :href="route('admin.refunds.index')" variant="secondary" size="sm">Open</x-button>
            </div>
            <div class="divide-y divide-neutral-100">
                @forelse ($refundQueue as $refund)
                    <a href="{{ route('admin.refunds.show', $refund) }}" class="grid gap-2 p-5 text-sm hover:bg-neutral-50">
                        <span class="font-semibold text-neutral-950">{{ $refund->refund_number }}</span>
                        <span class="text-neutral-600">{{ $refund->order?->order_number }} · {{ Money::format($refund->amount, $refund->currency) }}</span>
                        <x-badge :tone="$refund->status->badgeTone()">{{ $refund->status->label() }}</x-badge>
                    </a>
                @empty
                    <p class="p-5 text-sm text-neutral-600">No refunds need attention.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-lg border border-neutral-200 bg-white shadow-soft">
            <div class="flex items-center justify-between gap-4 border-b border-neutral-100 p-5">
                <h2 class="font-semibold text-neutral-950">Payout Queue</h2>
                <x-button :href="route('admin.payouts.index')" variant="secondary" size="sm">Open</x-button>
            </div>
            <div class="divide-y divide-neutral-100">
                @forelse ($payoutQueue as $payout)
                    <a href="{{ route('admin.payouts.show', $payout) }}" class="grid gap-2 p-5 text-sm hover:bg-neutral-50">
                        <span class="font-semibold text-neutral-950">{{ $payout->payout_number }}</span>
                        <span class="text-neutral-600">{{ $payout->vendor?->display_name }} · {{ Money::format($payout->amount, $payout->currency) }}</span>
                        <x-badge :tone="$payout->status->badgeTone()">{{ $payout->status->label() }}</x-badge>
                    </a>
                @empty
                    <p class="p-5 text-sm text-neutral-600">No payouts need action.</p>
                @endforelse
            </div>
        </section>
    </div>

    <section class="mt-6 rounded-lg border border-neutral-200 bg-white shadow-soft">
        <div class="flex items-center justify-between gap-4 border-b border-neutral-100 p-5">
            <div>
                <h2 class="font-semibold text-neutral-950">Recent Orders</h2>
                <p class="mt-1 text-sm text-neutral-600">Parent order snapshots with seller split counts and payment state.</p>
            </div>
            <x-button :href="route('admin.orders.index')" variant="secondary" size="sm">All Orders</x-button>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase text-neutral-500">
                    <tr>
                        <th class="px-5 py-3">Order</th>
                        <th class="px-5 py-3">Customer</th>
                        <th class="px-5 py-3">Sellers</th>
                        <th class="px-5 py-3">Payment</th>
                        <th class="px-5 py-3">Total</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($recentOrders as $order)
                        <tr>
                            <td class="px-5 py-4">
                                <p class="font-semibold text-neutral-950">{{ $order->order_number }}</p>
                                <p class="mt-1 text-xs text-neutral-500">{{ $order->placed_at?->format('M j, Y g:i A') }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <p class="font-medium text-neutral-950">{{ $order->user?->name }}</p>
                                <p class="mt-1 text-xs text-neutral-500">{{ $order->user?->email }}</p>
                            </td>
                            <td class="px-5 py-4 text-neutral-700">{{ $order->vendor_orders_count }}</td>
                            <td class="px-5 py-4">
                                <x-badge :tone="$order->payment_status->badgeTone()">{{ $order->payment_status->label() }}</x-badge>
                                @if ($order->payment?->requires_review_at && ! $order->payment?->review_resolved_at)
                                    <p class="mt-1 text-xs font-medium text-amber-700">Review required</p>
                                @endif
                            </td>
                            <td class="px-5 py-4 font-semibold text-neutral-950">{{ Money::format($order->grand_total, $order->currency) }}</td>
                            <td class="px-5 py-4 text-right">
                                <x-button :href="route('admin.orders.show', $order)" variant="secondary" size="sm">View</x-button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-sm text-neutral-600">No marketplace orders yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
