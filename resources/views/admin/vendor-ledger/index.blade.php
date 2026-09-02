@php
    use App\Support\Money;
@endphp

@extends('layouts.admin')

@section('title', 'Vendor Ledger')

@section('content')
    <div class="mb-8">
        <x-badge tone="brand">Vendor Ledger</x-badge>
        <h1 class="mt-4 text-2xl font-semibold tracking-normal text-neutral-950">Vendor Ledger</h1>
        <p class="mt-2 text-sm leading-6 text-neutral-600">Read-only seller ledger entries across vendors, currencies, and source references.</p>
    </div>

    <form method="GET" action="{{ route('admin.vendor-ledger.index') }}" class="mb-6 grid gap-3 rounded-lg border border-neutral-200 bg-white p-4 shadow-soft md:grid-cols-[1fr_12rem_10rem_10rem_1fr_auto]">
        <select name="vendor_id" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
            <option value="">All vendors</option>
            @foreach ($vendors as $vendor)
                <option value="{{ $vendor->id }}" @selected((string) $filters['vendor_id'] === (string) $vendor->id)>{{ $vendor->display_name }}</option>
            @endforeach
        </select>
        <select name="type" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
            <option value="">All types</option>
            @foreach ($types as $type)
                <option value="{{ $type->value }}" @selected($filters['type'] === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
        <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
        <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
        <input name="reference" value="{{ $filters['reference'] }}" class="rounded-md border border-neutral-300 px-3 py-2 text-sm" placeholder="Order, refund, or source key">
        <x-button type="submit" variant="secondary">Filter</x-button>
    </form>

    <section class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-soft">
        <div class="grid gap-4 border-b border-neutral-100 px-5 py-3 text-xs font-semibold uppercase tracking-normal text-neutral-500 lg:grid-cols-[9rem_10rem_11rem_1fr_8rem_10rem]">
            <span>Date</span>
            <span>Vendor</span>
            <span>Type</span>
            <span>Reference</span>
            <span>Amount</span>
            <span>Availability</span>
        </div>
        <div class="divide-y divide-neutral-100">
            @forelse ($entries as $entry)
                <article class="grid gap-3 px-5 py-4 text-sm lg:grid-cols-[9rem_10rem_11rem_1fr_8rem_10rem] lg:items-center">
                    <span class="text-neutral-500">{{ $entry->created_at->format('M j, Y') }}</span>
                    <a href="{{ route('admin.vendors.financials', $entry->vendor) }}" class="font-medium text-brand-700 hover:text-brand-900">{{ $entry->vendor->display_name }}</a>
                    <span class="font-medium text-neutral-950">{{ $entry->type->label() }}</span>
                    <div>
                        @if ($entry->order)
                            <a href="{{ route('admin.orders.show', $entry->order) }}" class="font-medium text-brand-700 hover:text-brand-900">{{ $entry->order->order_number }}</a>
                        @elseif ($entry->refund)
                            <a href="{{ route('admin.refunds.show', $entry->refund) }}" class="font-medium text-brand-700 hover:text-brand-900">{{ $entry->refund->refund_number }}</a>
                        @else
                            <span class="font-medium text-neutral-950">{{ $entry->source_key }}</span>
                        @endif
                        <p class="mt-1 text-xs text-neutral-500">{{ $entry->description }}</p>
                    </div>
                    <span class="{{ $entry->amount < 0 ? 'text-neutral-700' : 'text-emerald-700' }} font-semibold">{{ $entry->amount < 0 ? '-' : '+' }}{{ Money::format(abs($entry->amount), $entry->currency) }}</span>
                    <span class="text-xs text-neutral-500">{{ $entry->isAvailable() ? 'Available' : 'Available '.$entry->available_at->format('M j') }}</span>
                </article>
            @empty
                <x-empty-state title="No ledger entries" description="Vendor financial events will appear here after ledger posting." class="m-5" />
            @endforelse
        </div>
    </section>

    <div class="mt-6">{{ $entries->links() }}</div>
@endsection
