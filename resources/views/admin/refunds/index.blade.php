@extends('layouts.admin')

@php
    use App\Enums\RefundStatus;
    use App\Support\Money;
@endphp

@section('title', 'Refunds')

@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <x-badge tone="brand">Refunds</x-badge>
            <h1 class="mt-4 text-2xl font-semibold tracking-normal text-neutral-950">Marketplace Refunds</h1>
            <p class="mt-2 text-sm leading-6 text-neutral-600">Operational refund records and provider reconciliation state.</p>
        </div>
        <form method="GET" action="{{ route('admin.refunds.index') }}" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-[1fr_10rem_10rem_12rem_9rem_9rem_auto]">
            <x-input name="q" :value="$filters['q']" placeholder="Refund, order, customer" />
            <select name="status" class="h-11 rounded-md border border-neutral-300 bg-white px-3 text-sm text-neutral-950 shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
                <option value="">All statuses</option>
                @foreach (RefundStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <x-input name="provider" :value="$filters['provider']" placeholder="Provider" />
            <select name="vendor_id" class="h-11 rounded-md border border-neutral-300 bg-white px-3 text-sm text-neutral-950 shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
                <option value="">All vendors</option>
                @foreach ($vendors as $vendor)
                    <option value="{{ $vendor->id }}" @selected((string) $filters['vendor_id'] === (string) $vendor->id)>{{ $vendor->display_name }}</option>
                @endforeach
            </select>
            <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="h-11 rounded-md border border-neutral-300 bg-white px-3 text-sm text-neutral-950 shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
            <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="h-11 rounded-md border border-neutral-300 bg-white px-3 text-sm text-neutral-950 shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
            <x-button type="submit" variant="secondary">Filter</x-button>
        </form>
    </div>

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-soft">
        <div class="hidden grid-cols-[1fr_1fr_1.1fr_.8fr_.8fr_1fr_auto] gap-4 border-b border-neutral-100 bg-neutral-50 px-5 py-3 text-xs font-semibold uppercase tracking-normal text-neutral-500 md:grid">
            <span>Refund</span>
            <span>Order</span>
            <span>Customer</span>
            <span>Provider</span>
            <span>Amount</span>
            <span>Status</span>
            <span></span>
        </div>
        <div class="divide-y divide-neutral-100">
            @forelse ($refunds as $refund)
                <article class="grid gap-4 px-5 py-4 md:grid-cols-[1fr_1fr_1.1fr_.8fr_.8fr_1fr_auto] md:items-center">
                    <div>
                        <p class="font-semibold text-neutral-950">{{ $refund->refund_number }}</p>
                        <p class="mt-1 text-sm text-neutral-500">{{ $refund->requested_at?->format('M j, Y') }}</p>
                    </div>
                    <div><p class="font-medium text-neutral-950">{{ $refund->order->order_number }}</p>
                        @if($refund->returnRequest)<p class="text-xs">{{ $refund->returnRequest->return_number }}</p>@endif
                        <p class="text-xs">{{ $refund->allocations->map(fn($a) => $a->vendorOrder?->vendor?->display_name)->filter()->unique()->join(', ') }}</p>
                    </div>
                    <div>
                        <p class="font-medium text-neutral-950">{{ $refund->order->user->name }}</p>
                        <p class="mt-1 text-sm text-neutral-500">{{ $refund->order->user->email }}</p>
                    </div>
                    <p class="text-sm text-neutral-700">{{ str($refund->provider)->replace('_', ' ')->title() }}</p>
                    <p class="font-semibold text-neutral-950">{{ Money::format($refund->amount, $refund->currency) }}</p>
                    <x-badge :tone="$refund->status->badgeTone()">{{ $refund->status->label() }}</x-badge>
                    <x-button :href="route('admin.refunds.show', $refund)" variant="secondary" size="sm">View</x-button>
                </article>
            @empty
                <x-empty-state title="No refunds yet" description="Refund records will appear after an admin issues one." class="m-5" />
            @endforelse
        </div>
    </div>

    <div class="mt-6">{{ $refunds->links() }}</div>
@endsection
