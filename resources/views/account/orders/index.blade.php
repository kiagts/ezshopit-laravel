@extends('account.layout')

@php
    use App\Support\Money;
@endphp

@section('title', 'Orders')
@section('account_active', 'orders')
@section('account_badge', 'Orders')
@section('account_title', 'Your Orders')
@section('account_description', 'Track order status, seller groups, payment status, refunds, and review actions.')

@section('account_content')
    <div class="mb-6 flex gap-2 overflow-x-auto pb-1" aria-label="Order filters">
        @foreach ($filters as $key => $label)
            <a
                href="{{ route('account.orders.index', $key === 'all' ? [] : ['filter' => $key]) }}"
                @class([
                    'whitespace-nowrap rounded-md border px-3 py-2 text-sm font-medium transition',
                    'border-neutral-950 bg-neutral-950 text-white' => $filter === $key,
                    'border-neutral-200 bg-white text-neutral-700 hover:border-neutral-300 hover:text-neutral-950' => $filter !== $key,
                ])
            >
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="space-y-4">
        @forelse ($orders as $order)
            <article class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <a href="{{ route('account.orders.show', $order) }}" class="font-semibold text-neutral-950 hover:text-brand-700">{{ $order->order_number }}</a>
                        <p class="mt-1 text-sm text-neutral-600">{{ $order->vendor_orders_count }} {{ str('seller')->plural($order->vendor_orders_count) }} · {{ $order->placed_at?->format('M j, Y') }}</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <x-badge :tone="$order->status->badgeTone()">{{ $order->status->label() }}</x-badge>
                            <x-badge :tone="($order->payment?->status ?? $order->payment_status)->badgeTone()">Payment {{ ($order->payment?->status ?? $order->payment_status)->label() }}</x-badge>
                        </div>
                    </div>
                    <div class="sm:text-right">
                        <p class="text-sm text-neutral-500">Total</p>
                        <p class="mt-1 text-lg font-semibold text-neutral-950">{{ Money::format($order->grand_total) }}</p>
                        <x-button :href="route('account.orders.show', $order)" variant="secondary" size="sm" class="mt-4">View Order</x-button>
                    </div>
                </div>
            </article>
        @empty
            <x-empty-state title="No orders found" description="Orders matching this view will appear here." />
        @endforelse
    </div>

    <div class="mt-6">{{ $orders->links() }}</div>
@endsection
