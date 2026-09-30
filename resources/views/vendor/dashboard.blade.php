@php
    use App\Support\Money;
@endphp

@extends('layouts.vendor')

@section('title', 'Dashboard')

@section('content')
    <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="flex items-start gap-4">
            @if ($vendor->logo_path)
                <img src="{{ Storage::url($vendor->logo_path) }}" alt="{{ $vendor->display_name }} logo" class="h-14 w-14 rounded-md border border-neutral-200 bg-white object-cover">
            @else
                <div class="flex h-14 w-14 items-center justify-center rounded-md border border-neutral-200 bg-white text-lg font-semibold text-neutral-700">
                    {{ str($vendor->display_name)->substr(0, 1)->upper() }}
                </div>
            @endif

            <div>
                <x-badge :tone="$vendor->status->badgeTone()">{{ $vendor->status->label() }}</x-badge>
                <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">{{ $vendor->display_name }}</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-neutral-600">
                    Monitor fulfillment, inventory, customer reviews, promotions, and ledger-backed seller finances from one workspace.
                </p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            @if ($vendor->isApproved())
                <x-button :href="route('vendor.products.create')">Add Product</x-button>
                <x-button :href="route('storefront.stores.show', $vendor)" variant="secondary">View Store</x-button>
            @endif
        </div>
    </div>

    @if ($vendor->status !== \App\Enums\VendorStatus::Approved)
        <x-alert type="warning" class="mb-6">
            Your store is currently {{ strtolower($vendor->status->label()) }}. Selling actions are available after marketplace approval.
        </x-alert>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('vendor.products.index', ['approval' => 'approved', 'status' => 'active']) }}" class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft transition hover:border-brand-200">
            <p class="text-sm font-medium text-neutral-500">Active Products</p>
            <p class="mt-2 text-3xl font-semibold text-neutral-950">{{ number_format($productStats['active']) }}</p>
            <p class="mt-1 text-xs text-neutral-500">Approved and active listings</p>
        </a>

        <a href="{{ route('vendor.orders.index') }}" class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft transition hover:border-brand-200">
            <p class="text-sm font-medium text-neutral-500">Orders Requiring Action</p>
            <p class="mt-2 text-3xl font-semibold text-neutral-950">{{ number_format($orderStats['requiringAction']) }}</p>
            <p class="mt-1 text-xs text-neutral-500">New through ready to ship</p>
        </a>

        <a href="{{ route('vendor.inventory.index', ['status' => 'low_stock']) }}" class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft transition hover:border-brand-200">
            <p class="text-sm font-medium text-neutral-500">Low Stock Variants</p>
            <p class="mt-2 text-3xl font-semibold text-neutral-950">{{ number_format($inventoryStats['lowStock']) }}</p>
            <p class="mt-1 text-xs text-neutral-500">Available stock at threshold</p>
        </a>

        <a href="{{ route('vendor.earnings.index') }}" class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft transition hover:border-brand-200">
            <p class="text-sm font-medium text-neutral-500">Available Balance</p>
            <p class="mt-2 text-3xl font-semibold text-neutral-950">{{ Money::format($earningsSummary->availableBalance, $earningsSummary->currency) }}</p>
            <p class="mt-1 text-xs text-neutral-500">Ledger funds eligible for payout</p>
        </a>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(20rem,0.65fr)]">
        <section class="rounded-lg border border-neutral-200 bg-white shadow-soft">
            <div class="flex flex-col gap-3 border-b border-neutral-100 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-semibold text-neutral-950">Orders Needing Attention</h2>
                    <p class="mt-1 text-sm text-neutral-600">Status changes stay on order detail pages.</p>
                </div>
                <x-button :href="route('vendor.orders.index')" variant="secondary" size="sm">View Orders</x-button>
            </div>
            <div class="grid gap-3 p-5 sm:grid-cols-3">
                <a href="{{ route('vendor.orders.index', ['status' => 'pending']) }}" class="rounded-md border border-neutral-200 bg-neutral-50 p-4">
                    <p class="text-sm font-medium text-neutral-600">New</p>
                    <p class="mt-2 text-2xl font-semibold text-neutral-950">{{ number_format($orderStats['pending']) }}</p>
                </a>
                <a href="{{ route('vendor.orders.index', ['status' => 'confirmed']) }}" class="rounded-md border border-neutral-200 bg-neutral-50 p-4">
                    <p class="text-sm font-medium text-neutral-600">Processing</p>
                    <p class="mt-2 text-2xl font-semibold text-neutral-950">{{ number_format($orderStats['processing']) }}</p>
                </a>
                <a href="{{ route('vendor.orders.index', ['status' => 'ready_to_ship']) }}" class="rounded-md border border-neutral-200 bg-neutral-50 p-4">
                    <p class="text-sm font-medium text-neutral-600">Ready to Ship</p>
                    <p class="mt-2 text-2xl font-semibold text-neutral-950">{{ number_format($orderStats['readyToShip']) }}</p>
                </a>
            </div>
            <div class="divide-y divide-neutral-100 border-t border-neutral-100">
                @forelse ($recentOrders as $vendorOrder)
                    <article class="grid gap-3 p-5 text-sm md:grid-cols-[1.2fr_0.8fr_0.7fr_auto] md:items-center">
                        <div>
                            <a href="{{ route('vendor.orders.show', $vendorOrder) }}" class="font-semibold text-neutral-950 hover:text-brand-700">{{ $vendorOrder->vendor_order_number }}</a>
                            <p class="mt-1 text-neutral-500">{{ $vendorOrder->order?->order_number }} · {{ $vendorOrder->order?->placed_at?->format('M j, Y') }}</p>
                            <p class="mt-1 text-xs text-neutral-500">Customer: {{ $vendorOrder->order?->shippingRecipientName() ?: 'Order customer' }}</p>
                        </div>
                        <x-badge :tone="$vendorOrder->status->badgeTone()">{{ $vendorOrder->status->label() }}</x-badge>
                        <p class="text-neutral-600">{{ $vendorOrder->items->sum('quantity') }} {{ str('item')->plural($vendorOrder->items->sum('quantity')) }}</p>
                        <p class="font-semibold text-neutral-950 md:text-right">{{ Money::format($vendorOrder->grand_total) }}</p>
                    </article>
                @empty
                    <x-empty-state title="No seller orders yet" description="Orders will appear here after customers purchase your products." class="m-5" />
                @endforelse
            </div>
        </section>

        <div class="space-y-6">
            <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
                <h2 class="font-semibold text-neutral-950">Product Attention</h2>
                <div class="mt-4 grid gap-3 text-sm">
                    @foreach ([['Draft', $productAttention['draft'], route('vendor.products.index', ['status' => 'draft'])], ['Pending Approval', $productAttention['pending'], route('vendor.products.index', ['approval' => 'pending'])], ['Rejected', $productAttention['rejected'], route('vendor.products.index', ['approval' => 'rejected'])], ['Inactive', $productAttention['inactive'], route('vendor.products.index', ['status' => 'inactive'])]] as [$label, $count, $url])
                        <a href="{{ $url }}" class="flex items-center justify-between gap-4 rounded-md border border-neutral-200 bg-neutral-50 px-3 py-2 hover:border-brand-200">
                            <span class="text-neutral-600">{{ $label }}</span>
                            <span class="font-semibold text-neutral-950">{{ number_format($count) }}</span>
                        </a>
                    @endforeach
                </div>
            </section>

            <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
                <h2 class="font-semibold text-neutral-950">Inventory</h2>
                <div class="mt-4 grid gap-3 text-sm">
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Total variants</span><span class="font-semibold text-neutral-950">{{ number_format($inventoryStats['variants']) }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Out of stock</span><span class="font-semibold text-neutral-950">{{ number_format($inventoryStats['outOfStock']) }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Low stock</span><span class="font-semibold text-neutral-950">{{ number_format($inventoryStats['lowStock']) }}</span></div>
                </div>
                <x-button :href="route('vendor.inventory.index')" variant="secondary" size="sm" class="mt-5">Manage Inventory</x-button>
            </section>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-neutral-950">Earnings</h2>
                <a href="{{ route('vendor.earnings.index') }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">Ledger</a>
            </div>
            <div class="mt-4 grid gap-3 text-sm">
                <div class="flex justify-between gap-4"><span class="text-neutral-600">Available Balance</span><span class="font-semibold text-neutral-950">{{ Money::format($earningsSummary->availableBalance, $earningsSummary->currency) }}</span></div>
                <div class="flex justify-between gap-4"><span class="text-neutral-600">Pending Balance</span><span class="font-semibold text-neutral-950">{{ Money::format($earningsSummary->pendingBalance, $earningsSummary->currency) }}</span></div>
                <div class="flex justify-between gap-4"><span class="text-neutral-600">Net Earnings</span><span class="font-semibold text-neutral-950">{{ Money::format($earningsSummary->netEarnings, $earningsSummary->currency) }}</span></div>
            </div>
        </section>

        <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-neutral-950">Payouts</h2>
                <a href="{{ route('vendor.payouts.index') }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">Manage</a>
            </div>
            <div class="mt-4 grid gap-3 text-sm">
                <div class="flex justify-between gap-4"><span class="text-neutral-600">Reserved for Payout</span><span class="font-semibold text-neutral-950">{{ Money::format($payoutBalance->activePayoutReserved, $payoutBalance->currency) }}</span></div>
                <div class="flex justify-between gap-4"><span class="text-neutral-600">Requestable Balance</span><span class="font-semibold text-neutral-950">{{ Money::format($payoutBalance->requestableBalance, $payoutBalance->currency) }}</span></div>
                <div class="flex justify-between gap-4"><span class="text-neutral-600">Total Paid Out</span><span class="font-semibold text-neutral-950">{{ Money::format($payoutBalance->paidOutTotal, $payoutBalance->currency) }}</span></div>
            </div>
            @if ($activePayout)
                <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    <p class="font-semibold">{{ $activePayout->payout_number }}</p>
                    <p class="mt-1">{{ $activePayout->status->label() }} · {{ Money::format($activePayout->amount, $activePayout->currency) }}</p>
                </div>
            @elseif ($payoutBalance->requestableBalance >= $payoutBalance->minimumPayout)
                <x-button :href="route('vendor.payouts.index')" size="sm" class="mt-5">Request Payout</x-button>
            @endif
        </section>

        <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-neutral-950">Promotions</h2>
                <a href="{{ route('vendor.promotions.index') }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">Open</a>
            </div>
            <div class="mt-4 grid gap-3 text-sm">
                <div class="flex justify-between gap-4"><span class="text-neutral-600">Active Promotions</span><span class="font-semibold text-neutral-950">{{ number_format($promotionStats['active']) }}</span></div>
                <div class="flex justify-between gap-4"><span class="text-neutral-600">Total Redemptions</span><span class="font-semibold text-neutral-950">{{ number_format($promotionStats['redemptions']) }}</span></div>
                <div class="flex justify-between gap-4"><span class="text-neutral-600">Vendor-Funded Discounts</span><span class="font-semibold text-neutral-950">-{{ Money::format($promotionStats['vendorFundedDiscounts']) }}</span></div>
            </div>
            <p class="mt-4 text-xs leading-5 text-neutral-500">Vendor-funded discounts reduce your seller order value.</p>
        </section>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="rounded-lg border border-neutral-200 bg-white shadow-soft">
            <div class="flex items-center justify-between gap-4 border-b border-neutral-100 p-5">
                <div>
                    <h2 class="font-semibold text-neutral-950">Product Reviews</h2>
                    <p class="mt-1 text-sm text-neutral-600">
                        {{ $reviewStats['published'] }} published reviews
                        @if ($reviewStats['averageProductRating'])
                            · Average Product Rating {{ number_format($reviewStats['averageProductRating'], 1) }}/5
                        @endif
                    </p>
                </div>
                <x-button :href="route('vendor.reviews.index')" variant="secondary" size="sm">View Reviews</x-button>
            </div>
            <div class="divide-y divide-neutral-100">
                @forelse ($recentReviews as $review)
                    <article class="p-5 text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-semibold text-neutral-950">{{ $review->product?->name ?? 'Product unavailable' }}</p>
                            <x-star-rating :rating="$review->rating" />
                        </div>
                        <p class="mt-2 text-neutral-600">{{ $review->title ?: str($review->body)->limit(100) }}</p>
                    </article>
                @empty
                    <x-empty-state title="No product reviews yet" description="Product reviews will appear after delivered purchases are reviewed." class="m-5" />
                @endforelse
            </div>
        </section>

        <section class="rounded-lg border border-neutral-200 bg-white shadow-soft">
            <div class="flex items-center justify-between gap-4 border-b border-neutral-100 p-5">
                <div>
                    <h2 class="font-semibold text-neutral-950">Recent Notifications</h2>
                    <p class="mt-1 text-sm text-neutral-600">{{ number_format($unreadNotificationsCount) }} unread</p>
                </div>
                <x-button :href="route('vendor.notifications.index')" variant="secondary" size="sm">Open Center</x-button>
            </div>
            <div class="divide-y divide-neutral-100">
                @forelse ($latestNotifications as $notification)
                    <a href="{{ route('vendor.notifications.open', $notification) }}" class="block p-5 text-sm hover:bg-neutral-50">
                        <p class="font-semibold text-neutral-950">{{ $notification->data['title'] ?? 'Vendor update' }}</p>
                        <p class="mt-1 line-clamp-2 text-neutral-600">{{ $notification->data['message'] ?? '' }}</p>
                        <p class="mt-2 text-xs text-neutral-500">{{ $notification->created_at?->diffForHumans() }}</p>
                    </a>
                @empty
                    <p class="p-5 text-sm text-neutral-600">No notifications yet.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
