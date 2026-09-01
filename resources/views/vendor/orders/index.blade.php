@extends('layouts.vendor')

@php
    use App\Support\Money;
@endphp

@section('title', 'Orders')

@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <x-badge tone="brand">Orders</x-badge>
            <h1 class="mt-4 text-2xl font-semibold tracking-normal text-neutral-950">Seller Orders</h1>
            <p class="mt-2 text-sm leading-6 text-neutral-600">Manage fulfillment for your own seller order portions.</p>
        </div>
        <form method="GET" action="{{ route('vendor.orders.index') }}" class="flex flex-col gap-2 sm:flex-row">
            <x-input name="q" :value="$filters['q']" placeholder="Search order number" />
            <select name="status" class="h-11 rounded-md border border-neutral-300 bg-white px-3 text-sm text-neutral-800 shadow-sm focus:border-neutral-950 focus:outline-none focus:ring-2 focus:ring-neutral-950/10">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <x-button type="submit" variant="secondary">Search</x-button>
        </form>
    </div>

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-soft">
        <div class="hidden grid-cols-[1.3fr_1fr_1fr_1fr_auto] gap-4 border-b border-neutral-100 bg-neutral-50 px-5 py-3 text-xs font-semibold uppercase tracking-normal text-neutral-500 md:grid">
            <span>Seller order</span>
            <span>Status</span>
            <span>Items</span>
            <span>Total</span>
            <span></span>
        </div>
        <div class="divide-y divide-neutral-100">
            @forelse ($vendorOrders as $vendorOrder)
                <article class="grid gap-4 px-5 py-4 md:grid-cols-[1.3fr_1fr_1fr_1fr_auto] md:items-center">
                    <div>
                        <p class="font-semibold text-neutral-950">{{ $vendorOrder->vendor_order_number }}</p>
                        <p class="mt-1 text-sm text-neutral-500">{{ $vendorOrder->order->order_number }}</p>
                    </div>
                    <x-badge :tone="$vendorOrder->status->badgeTone()">{{ $vendorOrder->status->label() }}</x-badge>
                    <p class="text-sm text-neutral-600">{{ $vendorOrder->items->sum('quantity') }} {{ str('item')->plural($vendorOrder->items->sum('quantity')) }}</p>
                    <p class="font-semibold text-neutral-950">{{ Money::format($vendorOrder->grand_total) }}</p>
                    <x-button :href="route('vendor.orders.show', $vendorOrder)" variant="secondary" size="sm">View</x-button>
                </article>
            @empty
                <x-empty-state title="No seller orders yet" description="Seller portions of customer orders will appear here." class="m-5" />
            @endforelse
        </div>
    </div>

    <div class="mt-6">{{ $vendorOrders->links() }}</div>
@endsection
