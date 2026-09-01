@extends('layouts.app')

@php
    use App\Support\Money;
@endphp

@section('title', 'Orders')

@section('content')
    <x-container class="py-10 sm:py-14">
        <div class="grid gap-8 lg:grid-cols-[18rem_1fr]">
            <x-account.nav active="orders" />

            <section>
                <div class="mb-8">
                    <x-badge tone="brand">Orders</x-badge>
                    <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Your Orders</h1>
                    <p class="mt-2 text-sm leading-6 text-neutral-600">View pending marketplace orders and seller groups.</p>
                </div>

                <div class="space-y-4">
                    @forelse ($orders as $order)
                        <article class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="font-semibold text-neutral-950">{{ $order->order_number }}</p>
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
                        <x-empty-state title="No orders yet" description="Orders you place from checkout will appear here." />
                    @endforelse
                </div>

                <div class="mt-6">{{ $orders->links() }}</div>
            </section>
        </div>
    </x-container>
@endsection
