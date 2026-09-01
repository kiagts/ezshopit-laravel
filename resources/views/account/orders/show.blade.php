@extends('layouts.app')

@php
    use App\Support\Countries;
    use App\Support\Money;
@endphp

@section('title', $order->order_number)

@section('content')
    <x-container class="py-10 sm:py-14">
        <div class="grid gap-8 lg:grid-cols-[18rem_1fr]">
            <x-account.nav active="orders" />

            <section>
                <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <x-badge :tone="$order->status->badgeTone()">{{ $order->status->label() }}</x-badge>
                        <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">{{ $order->order_number }}</h1>
                        <p class="mt-2 text-sm leading-6 text-neutral-600">Placed {{ $order->placed_at?->format('M j, Y g:i A') }}</p>
                    </div>
                    <x-button :href="route('account.orders.index')" variant="secondary">All Orders</x-button>
                </div>

                @include('account.orders._details', ['order' => $order])
            </section>
        </div>
    </x-container>
@endsection
