@extends('layouts.admin')

@php
    use App\Support\Money;
@endphp

@section('title', 'Orders')

@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <x-badge tone="brand">Orders</x-badge>
            <h1 class="mt-4 text-2xl font-semibold tracking-normal text-neutral-950">Marketplace Orders</h1>
            <p class="mt-2 text-sm leading-6 text-neutral-600">Read-only visibility into parent orders and seller splits.</p>
        </div>
        <form method="GET" action="{{ route('admin.orders.index') }}" class="flex gap-2">
            <x-input name="q" :value="$filters['q']" placeholder="Search orders or customers" />
            <x-button type="submit" variant="secondary">Search</x-button>
        </form>
    </div>

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-soft">
        <div class="hidden grid-cols-[1.2fr_1.2fr_1fr_1fr_1fr_auto] gap-4 border-b border-neutral-100 bg-neutral-50 px-5 py-3 text-xs font-semibold uppercase tracking-normal text-neutral-500 md:grid">
            <span>Order</span>
            <span>Customer</span>
            <span>Status</span>
            <span>Sellers</span>
            <span>Total</span>
            <span></span>
        </div>
        <div class="divide-y divide-neutral-100">
            @forelse ($orders as $order)
                <article class="grid gap-4 px-5 py-4 md:grid-cols-[1.2fr_1.2fr_1fr_1fr_1fr_auto] md:items-center">
                    <div>
                        <p class="font-semibold text-neutral-950">{{ $order->order_number }}</p>
                        <p class="mt-1 text-sm text-neutral-500">{{ $order->placed_at?->format('M j, Y') }}</p>
                    </div>
                    <div>
                        <p class="font-medium text-neutral-950">{{ $order->user->name }}</p>
                        <p class="mt-1 text-sm text-neutral-500">{{ $order->user->email }}</p>
                    </div>
                    <x-badge :tone="$order->status->badgeTone()">{{ $order->status->label() }}</x-badge>
                    <p class="text-sm text-neutral-600">{{ $order->vendor_orders_count }}</p>
                    <p class="font-semibold text-neutral-950">{{ Money::format($order->grand_total) }}</p>
                    <x-button :href="route('admin.orders.show', $order)" variant="secondary" size="sm">View</x-button>
                </article>
            @empty
                <x-empty-state title="No orders yet" description="Marketplace orders will appear here after checkout placement." class="m-5" />
            @endforelse
        </div>
    </div>

    <div class="mt-6">{{ $orders->links() }}</div>
@endsection
