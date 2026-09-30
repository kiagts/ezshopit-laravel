@php
    use App\Support\Money;
@endphp

@extends('layouts.admin')

@section('title', $vendor->display_name.' Financials')

@section('content')
    <a class="mb-6 inline-flex min-h-11 items-center rounded border px-4" href="{{ route('admin.finance.index', ['vendor' => $vendor, 'currency' => $filters['currency']]) }}">Seller finance, statements and reconciliation</a>
    <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <a href="{{ route('admin.vendors.show', $vendor) }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">Back to vendor</a>
            <x-badge tone="brand" class="mt-4">Vendor Financials</x-badge>
            <h1 class="mt-4 text-2xl font-semibold tracking-normal text-neutral-950">{{ $vendor->display_name }}</h1>
            <p class="mt-2 text-sm leading-6 text-neutral-600">Read-only balance, payout reservations, and recent ledger activity generated from seller commerce events.</p>
        </div>
        <form method="GET" action="{{ route('admin.vendors.financials', $vendor) }}" class="flex gap-2">
            <select name="currency" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
                @foreach ($currencies as $currency)
                    <option value="{{ $currency }}" @selected($filters['currency'] === $currency)>{{ $currency }}</option>
                @endforeach
            </select>
            <x-button type="submit" variant="secondary">Apply</x-button>
        </form>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ([['Available Balance', $summary->availableBalance], ['Reserved for Payout', $payoutBalance->activePayoutReserved], ['Requestable Balance', $payoutBalance->requestableBalance], ['Total Paid Out', $payoutBalance->paidOutTotal], ['Pending Balance', $summary->pendingBalance]] as [$label, $amount])
            <x-card>
                <p class="text-xs font-semibold uppercase tracking-normal text-neutral-500">{{ $label }}</p>
                <p class="mt-3 text-lg font-semibold text-neutral-950">{{ Money::format($amount, $summary->currency) }}</p>
            </x-card>
        @endforeach
    </div>

    <section class="mt-8 overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-soft">
        <div class="flex items-center justify-between border-b border-neutral-100 px-5 py-4">
            <h2 class="font-semibold text-neutral-950">Recent Payouts</h2>
            <a href="{{ route('admin.payouts.index', ['vendor_id' => $vendor->id]) }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">View payouts</a>
        </div>
        <div class="divide-y divide-neutral-100">
            @forelse ($recentPayouts as $payout)
                <article class="grid gap-3 px-5 py-4 text-sm md:grid-cols-[11rem_1fr_8rem_7rem] md:items-center">
                    <a href="{{ route('admin.payouts.show', $payout) }}" class="font-semibold text-brand-700 hover:text-brand-900">{{ $payout->payout_number }}</a>
                    <span class="text-neutral-600">{{ $payout->requested_at->format('M j, Y') }}</span>
                    <span class="font-semibold text-neutral-950">{{ Money::format($payout->amount, $payout->currency) }}</span>
                    <x-badge :tone="$payout->status->badgeTone()">{{ $payout->status->label() }}</x-badge>
                </article>
            @empty
                <x-empty-state title="No payouts" description="This vendor has not requested payouts yet." class="m-5" />
            @endforelse
        </div>
    </section>

    <section class="mt-8 overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-soft">
        <div class="flex items-center justify-between border-b border-neutral-100 px-5 py-4">
            <h2 class="font-semibold text-neutral-950">Recent Ledger Entries</h2>
            <a href="{{ route('admin.vendor-ledger.index', ['vendor_id' => $vendor->id]) }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">View all</a>
        </div>
        <div class="divide-y divide-neutral-100">
            @forelse ($entries as $entry)
                <article class="grid gap-3 px-5 py-4 text-sm md:grid-cols-[7rem_11rem_1fr_8rem_10rem] md:items-center">
                    <span class="text-neutral-500">{{ $entry->created_at->format('M j') }}</span>
                    <span class="font-medium text-neutral-950">{{ $entry->type->label() }}</span>
                    <div>
                        @if ($entry->order)
                            <a href="{{ route('admin.orders.show', $entry->order) }}" class="font-medium text-brand-700 hover:text-brand-900">{{ $entry->order->order_number }}</a>
                        @else
                            <span class="font-medium text-neutral-950">{{ $entry->source_key }}</span>
                        @endif
                        <p class="mt-1 text-xs text-neutral-500">{{ $entry->description }}</p>
                    </div>
                    <span class="{{ $entry->amount < 0 ? 'text-neutral-700' : 'text-emerald-700' }} font-semibold">{{ $entry->amount < 0 ? '-' : '+' }}{{ Money::format(abs($entry->amount), $entry->currency) }}</span>
                    <span class="text-xs text-neutral-500">{{ $entry->isAvailable() ? 'Available' : 'Available '.$entry->available_at->format('M j') }}</span>
                </article>
            @empty
                <x-empty-state title="No ledger entries" description="This vendor has no posted financial activity yet." class="m-5" />
            @endforelse
        </div>
    </section>
@endsection

