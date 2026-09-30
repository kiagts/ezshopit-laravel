@extends('layouts.admin')

@php
    use App\Enums\PaymentStatus;
    use App\Support\Money;
@endphp

@section('title', 'Payments')

@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <x-badge tone="brand">Payments</x-badge>
            <h1 class="mt-4 text-2xl font-semibold tracking-normal text-neutral-950">Marketplace Payments</h1>
            <p class="mt-2 text-sm leading-6 text-neutral-600">Read-only payment records for parent marketplace orders.</p>
        </div>
        <form method="GET" action="{{ route('admin.payments.index') }}" class="flex flex-wrap gap-2">
            <x-input name="q" :value="$filters['q']" placeholder="Search order or customer" />
            <select name="status" class="h-11 rounded-md border border-neutral-300 bg-white px-3 text-sm text-neutral-950 shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
                <option value="">All statuses</option>
                @foreach (PaymentStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <select name="review" class="h-11 rounded-md border border-neutral-300 bg-white px-3 text-sm text-neutral-950 shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
                <option value="">All review states</option>
                <option value="required" @selected($filters['review'] === 'required')>Requires review</option>
                <option value="resolved" @selected($filters['review'] === 'resolved')>Review resolved</option>
            </select>
            <x-button type="submit" variant="secondary">Filter</x-button>
        </form>
    </div>

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-soft">
        <div class="hidden grid-cols-[1.1fr_1.1fr_.8fr_.8fr_.8fr_1fr_auto] gap-4 border-b border-neutral-100 bg-neutral-50 px-5 py-3 text-xs font-semibold uppercase tracking-normal text-neutral-500 md:grid">
            <span>Order</span>
            <span>Customer</span>
            <span>Method</span>
            <span>Status</span>
            <span>Attempts</span>
            <span>Amount</span>
            <span></span>
        </div>
        <div class="divide-y divide-neutral-100">
            @forelse ($payments as $payment)
                <article class="grid gap-4 px-5 py-4 md:grid-cols-[1.1fr_1.1fr_.8fr_.8fr_.8fr_1fr_auto] md:items-center">
                    <div>
                        <p class="font-semibold text-neutral-950">{{ $payment->order->order_number }}</p>
                        <p class="mt-1 text-sm text-neutral-500">{{ $payment->created_at->format('M j, Y') }}</p>
                    </div>
                    <div>
                        <p class="font-medium text-neutral-950">{{ $payment->order->user->name }}</p>
                        <p class="mt-1 text-sm text-neutral-500">{{ $payment->order->user->email }}</p>
                    </div>
                    <p class="text-sm text-neutral-700">{{ $payment->method->label() }}</p>
                    <div>
                        <x-badge :tone="$payment->status->badgeTone()">{{ $payment->status->label() }}</x-badge>
                        @if ($payment->requires_review_at && ! $payment->review_resolved_at)
                            <p class="mt-1 text-xs font-medium text-amber-700">Review required</p>
                        @endif
                    </div>
                    <p class="text-sm text-neutral-700">{{ $payment->attempts_count }}</p>
                    <p class="font-semibold text-neutral-950">{{ Money::format($payment->amount) }}</p>
                    <x-button :href="route('admin.payments.show', $payment)" variant="secondary" size="sm">View</x-button>
                </article>
            @empty
                <x-empty-state title="No payments yet" description="Payments will appear after orders are placed." class="m-5" />
            @endforelse
        </div>
    </div>

    <div class="mt-6">{{ $payments->links() }}</div>
@endsection
