@extends('layouts.admin')

@php
    use App\Support\Money;
@endphp

@section('title', 'Commissions')

@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <x-badge tone="brand">Commissions</x-badge>
            <h1 class="mt-4 text-2xl font-semibold tracking-normal text-neutral-950">Recognized Commissions</h1>
            <p class="mt-2 text-sm leading-6 text-neutral-600">Per-seller commission snapshots and refund adjustments.</p>
        </div>
        <form method="GET" action="{{ route('admin.commissions.index') }}" class="flex flex-wrap gap-2">
            <x-input name="q" :value="$filters['q']" placeholder="Order or seller order" />
            <select name="vendor_id" class="h-11 rounded-md border border-neutral-300 bg-white px-3 text-sm text-neutral-950 shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
                <option value="">All vendors</option>
                @foreach ($vendors as $vendor)
                    <option value="{{ $vendor->id }}" @selected((string) $filters['vendor_id'] === (string) $vendor->id)>{{ $vendor->display_name }}</option>
                @endforeach
            </select>
            <x-button type="submit" variant="secondary">Filter</x-button>
        </form>
    </div>

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-soft">
        <div class="hidden grid-cols-[1.1fr_1fr_1fr_1fr_1fr_1fr_auto] gap-4 border-b border-neutral-100 bg-neutral-50 px-5 py-3 text-xs font-semibold uppercase tracking-normal text-neutral-500 md:grid">
            <span>Vendor Order</span>
            <span>Vendor</span>
            <span>Original Basis</span>
            <span>Refunded Basis</span>
            <span>Rate</span>
            <span>Net Commission</span>
            <span></span>
        </div>
        <div class="divide-y divide-neutral-100">
            @forelse ($commissions as $commission)
                @php($summary = $financials->summaryFor($commission))
                <article class="grid gap-4 px-5 py-4 md:grid-cols-[1.1fr_1fr_1fr_1fr_1fr_1fr_auto] md:items-center">
                    <div>
                        <p class="font-semibold text-neutral-950">{{ $commission->vendorOrder->vendor_order_number }}</p>
                        <p class="mt-1 text-sm text-neutral-500">{{ $commission->vendorOrder->order->order_number }}</p>
                    </div>
                    <p class="font-medium text-neutral-950">{{ $commission->vendorOrder->vendor->display_name }}</p>
                    <p class="text-sm text-neutral-700">{{ Money::format($commission->original_commissionable_amount) }}</p>
                    <p class="text-sm text-neutral-700">{{ Money::format(abs($summary->originalCommissionableAmount - $summary->netCommissionableAmount)) }}</p>
                    <p class="text-sm text-neutral-700">{{ $calculator->formatRate($commission->rate_basis_points) }}</p>
                    <p class="font-semibold text-neutral-950">{{ Money::format($summary->netCommissionAmount) }}</p>
                    <x-button :href="route('admin.commissions.show', $commission)" variant="secondary" size="sm">View</x-button>
                </article>
            @empty
                <x-empty-state title="No recognized commissions" description="Delivered and collected seller orders will appear after commission recognition." class="m-5" />
            @endforelse
        </div>
    </div>

    <div class="mt-6">{{ $commissions->links() }}</div>
@endsection
