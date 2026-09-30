@extends('account.layout')

@php
    use App\Support\Countries;
    use App\Support\Money;
@endphp

@section('title', 'Account')
@section('account_active', 'account')
@section('account_title', 'Hi, '.strtok($user->name, ' '))
@section('account_description', 'A quick view of your orders, saved products, reviews, addresses, and messages.')

@section('account_content')
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('account.orders.index') }}" class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft transition hover:border-brand-200">
            <p class="text-sm font-medium text-neutral-500">Orders</p>
            <p class="mt-2 text-3xl font-semibold text-neutral-950">{{ number_format($ordersCount) }}</p>
            <p class="mt-1 text-xs text-neutral-500">All marketplace orders</p>
        </a>

        <a href="{{ route('account.wishlist.index') }}" class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft transition hover:border-brand-200">
            <p class="text-sm font-medium text-neutral-500">Wishlist</p>
            <p class="mt-2 text-3xl font-semibold text-neutral-950">{{ number_format($wishlistCount) }}</p>
            <p class="mt-1 text-xs text-neutral-500">Saved products</p>
        </a>

        <a href="{{ route('account.reviews.index') }}" class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft transition hover:border-brand-200">
            <p class="text-sm font-medium text-neutral-500">Awaiting Review</p>
            <p class="mt-2 text-3xl font-semibold text-neutral-950">{{ number_format($awaitingReviewsCount) }}</p>
            <p class="mt-1 text-xs text-neutral-500">Delivered purchases</p>
        </a>

        <a href="{{ route('account.notifications.index', ['filter' => 'unread']) }}" class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft transition hover:border-brand-200">
            <p class="text-sm font-medium text-neutral-500">Unread Messages</p>
            <p class="mt-2 text-3xl font-semibold text-neutral-950">{{ number_format($unreadNotificationsCount) }}</p>
            <p class="mt-1 text-xs text-neutral-500">Account notifications</p>
        </a>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="rounded-lg border border-neutral-200 bg-white shadow-soft">
            <div class="flex items-center justify-between gap-4 border-b border-neutral-100 p-5">
                <h2 class="font-semibold text-neutral-950">Latest Orders</h2>
                <a href="{{ route('account.orders.index') }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">View all</a>
            </div>
            <div class="divide-y divide-neutral-100">
                @forelse ($latestOrders as $order)
                    <article class="grid gap-3 p-5 text-sm sm:grid-cols-[1fr_auto] sm:items-center">
                        <div>
                            <a href="{{ route('account.orders.show', $order) }}" class="font-semibold text-neutral-950 hover:text-brand-700">{{ $order->order_number }}</a>
                            <p class="mt-1 text-neutral-600">{{ $order->vendor_orders_count }} {{ str('seller')->plural($order->vendor_orders_count) }} · {{ $order->placed_at?->format('M j, Y') }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <x-badge :tone="$order->status->badgeTone()">{{ $order->status->label() }}</x-badge>
                                <x-badge :tone="($order->payment?->status ?? $order->payment_status)->badgeTone()">Payment {{ ($order->payment?->status ?? $order->payment_status)->label() }}</x-badge>
                            </div>
                        </div>
                        <p class="font-semibold text-neutral-950 sm:text-right">{{ Money::format($order->grand_total) }}</p>
                    </article>
                @empty
                    <x-empty-state title="No orders yet" description="Your checkout orders will appear here." class="m-5" />
                @endforelse
            </div>
        </section>

        <div class="space-y-6">
            <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
                <h2 class="font-semibold text-neutral-950">Default Address</h2>
                @if ($defaultAddress)
                    <div class="mt-3 text-sm leading-6 text-neutral-600">
                        <p class="font-medium text-neutral-950">{{ trim($defaultAddress->first_name.' '.$defaultAddress->last_name) }}</p>
                        <p>{{ $defaultAddress->address_line_1 }}</p>
                        @if ($defaultAddress->address_line_2)
                            <p>{{ $defaultAddress->address_line_2 }}</p>
                        @endif
                        <p>{{ collect([$defaultAddress->city, $defaultAddress->state_region, $defaultAddress->postal_code])->filter()->implode(', ') }}</p>
                        <p>{{ Countries::name($defaultAddress->country_code) }}</p>
                    </div>
                @else
                    <p class="mt-2 text-sm leading-6 text-neutral-600">Add a delivery address to make checkout faster.</p>
                @endif
                <x-button :href="route('account.addresses.index')" variant="secondary" size="sm" class="mt-5">Manage Addresses</x-button>
            </section>

            <section class="rounded-lg border border-neutral-200 bg-white shadow-soft">
                <div class="flex items-center justify-between gap-4 border-b border-neutral-100 p-5">
                    <h2 class="font-semibold text-neutral-950">Notifications</h2>
                    <a href="{{ route('account.notifications.index') }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">Open</a>
                </div>
                <div class="divide-y divide-neutral-100">
                    @forelse ($latestNotifications as $notification)
                        <a href="{{ route('account.notifications.open', $notification) }}" class="block p-5 text-sm hover:bg-neutral-50">
                            <p class="font-medium text-neutral-950">{{ $notification->data['title'] ?? 'Account update' }}</p>
                            <p class="mt-1 line-clamp-2 text-neutral-600">{{ $notification->data['message'] ?? '' }}</p>
                            <p class="mt-2 text-xs text-neutral-500">{{ $notification->created_at?->diffForHumans() }}</p>
                        </a>
                    @empty
                        <p class="p-5 text-sm text-neutral-600">No notifications yet.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection
