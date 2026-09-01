@extends('layouts.vendor')

@php
    use App\Support\Money;
@endphp

@section('title', 'Commissions')

@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <x-badge tone="brand">Commissions</x-badge>
            <h1 class="mt-4 text-2xl font-semibold tracking-normal text-neutral-950">Order Commission History</h1>
            <p class="mt-2 text-sm leading-6 text-neutral-600">Read-only seller order value and marketplace commission records.</p>
        </div>
        <form method="GET" action="{{ route('vendor.commissions.index') }}" class="flex gap-2">
            <x-input name="q" :value="$filters['q']" placeholder="Order number" />
            <x-button type="submit" variant="secondary">Search</x-button>
        </form>
    </div>

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-soft">
        <div class="hidden grid-cols-[1fr_1fr_1fr_1fr_1fr_1fr] gap-4 border-b border-neutral-100 bg-neutral-50 px-5 py-3 text-xs font-semibold uppercase tracking-normal text-neutral-500 md:grid">
            <span>Vendor Order</span>
            <span>Order Date</span>
            <span>Order Value</span>
            <span>Refunded</span>
            <span>Commission</span>
            <span>Net After Commission</span>
        </div>
        <div class="divide-y divide-neutral-100">
            @forelse ($commissions as $commission)
                @php($summary = $financials->summaryFor($commission))
                <article class="grid gap-4 px-5 py-4 md:grid-cols-[1fr_1fr_1fr_1fr_1fr_1fr] md:items-center">
                    <div>
                        <p class="font-semibold text-neutral-950">{{ $commission->vendorOrder->vendor_order_number }}</p>
                        <p class="mt-1 text-sm text-neutral-500">{{ $commission->vendorOrder->order->order_number }}</p>
                    </div>
                    <p class="text-sm text-neutral-600">{{ $commission->vendorOrder->order->placed_at?->format('M j, Y') }}</p>
                    <p class="font-medium text-neutral-950">{{ Money::format($summary->originalSellerValue) }}</p>
                    <p class="text-sm text-neutral-700">{{ Money::format($summary->successfulVendorRefunds) }}</p>
                    <div>
                        <p class="font-medium text-neutral-950">{{ Money::format($summary->netCommissionAmount) }}</p>
                        <p class="mt-1 text-xs text-neutral-500">{{ $calculator->formatRate($commission->rate_basis_points) }}</p>
                    </div>
                    <p class="font-semibold text-neutral-950">{{ Money::format($summary->netAfterCommission) }}</p>
                </article>
            @empty
                <x-empty-state title="No recognized commissions" description="Delivered paid seller orders will appear here after commission recognition." class="m-5" />
            @endforelse
        </div>
    </div>

    <div class="mt-6">{{ $commissions->links() }}</div>
@endsection
