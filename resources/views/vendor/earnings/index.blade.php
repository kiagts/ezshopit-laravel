@php
    use App\Support\Money;
@endphp

@extends('layouts.vendor')

@section('title', 'Earnings')

@section('content')
    <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <x-badge tone="brand">Earnings</x-badge>
            <h1 class="mt-4 text-2xl font-semibold tracking-normal text-neutral-950">Vendor Earnings</h1>
            <p class="mt-2 text-sm leading-6 text-neutral-600">Available and pending balances are derived from immutable ledger entries.</p>
        </div>
        <div class="flex flex-col gap-3 lg:items-end">
            <x-button :href="route('vendor.payouts.index')" variant="secondary">Manage Payouts</x-button>
            <form method="GET" action="{{ route('vendor.earnings.index') }}" class="flex flex-wrap gap-2">
                <select name="currency" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
                    @foreach ($currencies as $currency)
                        <option value="{{ $currency }}" @selected($filters['currency'] === $currency)>{{ $currency }}</option>
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
                <x-button type="submit" variant="secondary">Filter</x-button>
            </form>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        @foreach ([['Available Balance', $summary->availableBalance], ['Pending Balance', $summary->pendingBalance], ['Net Earnings', $summary->netEarnings], ['Gross Sales', $summary->grossSales], ['Commission', $summary->commissionCharged], ['Refunds', $summary->refunds]] as [$label, $amount])
            <x-card>
                <p class="text-xs font-semibold uppercase tracking-normal text-neutral-500">{{ $label }}</p>
                <p class="mt-3 text-xl font-semibold text-neutral-950">{{ Money::format($amount, $summary->currency) }}</p>
            </x-card>
        @endforeach
    </div>

    <section class="mt-8 overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-soft">
        <div class="grid gap-4 border-b border-neutral-100 px-5 py-3 text-xs font-semibold uppercase tracking-normal text-neutral-500 md:grid-cols-[7rem_11rem_1fr_8rem_10rem]">
            <span>Date</span>
            <span>Type</span>
            <span>Reference</span>
            <span>Amount</span>
            <span>Availability</span>
        </div>
        <div class="divide-y divide-neutral-100">
            @forelse ($entries as $entry)
                <article class="grid gap-3 px-5 py-4 text-sm md:grid-cols-[7rem_11rem_1fr_8rem_10rem] md:items-center">
                    <span class="text-neutral-500">{{ $entry->created_at->format('M j') }}</span>
                    <span class="font-medium text-neutral-950">{{ $entry->type->label() }}</span>
                    <div>
                        @if ($entry->vendorOrder)
                            <a href="{{ route('vendor.orders.show', $entry->vendorOrder) }}" class="font-medium text-brand-700 hover:text-brand-900">{{ $entry->vendorOrder->vendor_order_number }}</a>
                        @elseif ($entry->order)
                            <span class="font-medium text-neutral-950">{{ $entry->order->order_number }}</span>
                        @else
                            <span class="font-medium text-neutral-950">{{ $entry->source_key }}</span>
                        @endif
                        <p class="mt-1 text-xs text-neutral-500">{{ $entry->description }}</p>
                    </div>
                    <span class="{{ $entry->amount < 0 ? 'text-neutral-700' : 'text-emerald-700' }} font-semibold">{{ $entry->amount < 0 ? '-' : '+' }}{{ Money::format(abs($entry->amount), $entry->currency) }}</span>
                    <span class="text-xs text-neutral-500">
                        {{ $entry->isAvailable() ? 'Available' : 'Available '.$entry->available_at->format('M j') }}
                    </span>
                </article>
            @empty
                <x-empty-state title="No ledger entries" description="Delivered paid seller orders will create earnings after commission recognition." class="m-5" />
            @endforelse
        </div>
    </section>

    <div class="mt-6">{{ $entries->links() }}</div>
@endsection
