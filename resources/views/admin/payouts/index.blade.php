@php
    use App\Support\Money;
@endphp

@extends('layouts.admin')

@section('title', 'Vendor Payouts')

@section('content')
    <div class="mb-8">
        <x-badge tone="brand">Finance</x-badge>
        <h1 class="mt-4 text-2xl font-semibold text-neutral-950">Vendor Payouts</h1>
        <p class="mt-2 text-sm text-neutral-600">Manual payout operations backed by vendor ledger debits when marked paid.</p>
    </div>

    <form method="GET" action="{{ route('admin.payouts.index') }}" class="mb-6 grid gap-3 rounded-lg border border-neutral-200 bg-white p-4 shadow-sm md:grid-cols-5">
        <input name="search" value="{{ $filters['search'] }}" placeholder="Payout number or vendor" class="h-10 rounded-md border border-neutral-300 px-3 text-sm">
        <select name="status" class="h-10 rounded-md border border-neutral-300 px-3 text-sm">
            <option value="">All statuses</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
        <select name="vendor_id" class="h-10 rounded-md border border-neutral-300 px-3 text-sm">
            <option value="">All vendors</option>
            @foreach ($vendors as $vendor)
                <option value="{{ $vendor->id }}" @selected((string) $filters['vendor_id'] === (string) $vendor->id)>{{ $vendor->display_name }}</option>
            @endforeach
        </select>
        <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="h-10 rounded-md border border-neutral-300 px-3 text-sm">
        <div class="flex gap-2">
            <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="h-10 min-w-0 flex-1 rounded-md border border-neutral-300 px-3 text-sm">
            <x-button type="submit" variant="secondary">Filter</x-button>
        </div>
    </form>

    <section class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        <div class="grid gap-4 border-b border-neutral-100 px-5 py-3 text-xs font-semibold uppercase text-neutral-500 md:grid-cols-[11rem_1fr_8rem_6rem_9rem_7rem]">
            <span>Payout</span>
            <span>Vendor</span>
            <span>Amount</span>
            <span>Status</span>
            <span>Requested</span>
            <span></span>
        </div>
        <div class="divide-y divide-neutral-100">
            @forelse ($payouts as $payout)
                <article class="grid gap-3 px-5 py-4 text-sm md:grid-cols-[11rem_1fr_8rem_6rem_9rem_7rem] md:items-center">
                    <a href="{{ route('admin.payouts.show', $payout) }}" class="font-semibold text-brand-700 hover:text-brand-900">{{ $payout->payout_number }}</a>
                    <span class="text-neutral-700">{{ $payout->vendor->display_name }}</span>
                    <span class="font-semibold text-neutral-950">{{ Money::format($payout->amount, $payout->currency) }}</span>
                    <x-badge :tone="$payout->status->badgeTone()">{{ $payout->status->label() }}</x-badge>
                    <span class="text-xs text-neutral-500">{{ $payout->requested_at->format('M j, Y') }}</span>
                    <x-button :href="route('admin.payouts.show', $payout)" variant="secondary" size="sm">Review</x-button>
                </article>
            @empty
                <x-empty-state title="No payouts" description="Vendor payout requests will appear here." class="m-5" />
            @endforelse
        </div>
    </section>

    <div class="mt-6">{{ $payouts->links() }}</div>
@endsection
