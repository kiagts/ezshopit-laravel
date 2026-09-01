@extends('layouts.app')

@php
    use App\Support\Money;
@endphp

@section('title', 'Order Placed')

@section('content')
    <x-container class="py-10 sm:py-14">
        <div class="mx-auto max-w-3xl">
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-6">
                <x-badge tone="success">Order placed</x-badge>
                <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Thank you. Your order has been received.</h1>
                <p class="mt-3 text-sm leading-6 text-emerald-900">Your stock has been reserved and the order is pending payment.</p>
            </div>

            <div class="mt-6 rounded-lg border border-neutral-200 bg-white p-6 shadow-soft">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <p class="text-sm text-neutral-500">Order</p>
                        <p class="mt-1 font-semibold text-neutral-950">{{ $order->order_number }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-neutral-500">Sellers</p>
                        <p class="mt-1 font-semibold text-neutral-950">{{ $order->sellerCount() }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-neutral-500">Current total</p>
                        <p class="mt-1 font-semibold text-neutral-950">{{ Money::format($order->grand_total) }}</p>
                    </div>
                </div>

                <div class="mt-6 rounded-md bg-neutral-50 p-4 text-sm">
                    <p class="font-semibold text-neutral-950">Payment</p>
                    <p class="mt-1 text-neutral-600">{{ $order->payment?->method->label() ?? 'Payment pending' }} · {{ ($order->payment?->status ?? $order->payment_status)->label() }}</p>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <x-button :href="route('account.orders.show', $order)">View Order</x-button>
                    <x-button :href="route('home')" variant="secondary">Continue Shopping</x-button>
                </div>
            </div>
        </div>
    </x-container>
@endsection
