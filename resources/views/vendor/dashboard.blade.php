@php
    use App\Support\Money;
@endphp

@extends('layouts.vendor')

@section('title', 'Dashboard')

@section('content')
    <div class="mb-8">
        <div class="flex items-center gap-4">
            @if ($vendor->logo_path)
                <img src="{{ Storage::url($vendor->logo_path) }}" alt="{{ $vendor->display_name }} logo" class="h-14 w-14 rounded-md object-cover">
            @endif
            <div>
                <x-badge :tone="$vendor->status->badgeTone()">{{ $vendor->status->label() }}</x-badge>
                <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Welcome, {{ $vendor->display_name }}</h1>
            </div>
        </div>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-neutral-600">
            Manage product drafts, approvals, and operational stock from this workspace.
        </p>
    </div>

    <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([['Available Balance', Money::format($earningsSummary->availableBalance, $earningsSummary->currency)], ['Reserved for Payout', Money::format($payoutBalance->activePayoutReserved, $payoutBalance->currency)], ['Requestable Balance', Money::format($payoutBalance->requestableBalance, $payoutBalance->currency)], ['Total Paid Out', Money::format($payoutBalance->paidOutTotal, $payoutBalance->currency)]] as [$label, $value])
            <x-card>
                <p class="text-sm font-medium text-neutral-500">{{ $label }}</p>
                <p class="mt-3 text-3xl font-semibold text-neutral-950">{{ $value }}</p>
            </x-card>
        @endforeach
    </div>

    <section class="mt-8 rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-neutral-950">Earnings</h2>
                <p class="mt-2 text-sm leading-6 text-neutral-600">Review available balance, pending earnings, payout reservations, and the ledger entries that explain every movement.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-button :href="route('vendor.earnings.index')" variant="secondary">View Earnings</x-button>
                <x-button :href="route('vendor.payouts.index')" variant="secondary">Manage Payouts</x-button>
            </div>
        </div>
    </section>

    <section class="mt-8 rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-neutral-950">Inventory workspace</h2>
                <p class="mt-2 text-sm leading-6 text-neutral-600">Review SKU availability, identify low-stock variants, and record auditable stock adjustments.</p>
            </div>
            <x-button :href="route('vendor.inventory.index')" variant="secondary">Manage Inventory</x-button>
        </div>
    </section>

    <section class="mt-8 rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 class="text-base font-semibold text-neutral-950">Product Reviews</h2>
                <p class="mt-2 text-sm leading-6 text-neutral-600">
                    {{ $reviewStats['published'] }} published reviews
                    @if ($reviewStats['published'] > 0)
                        · average {{ number_format((float) $reviewStats['average'], 1) }}
                    @endif
                </p>
                <div class="mt-4 space-y-2 text-sm text-neutral-600">
                    @forelse ($recentReviews as $review)
                        <p>{{ $review->product?->name }} · {{ $review->status->adminLabel() }} · {{ $review->rating }}/5</p>
                    @empty
                        <p>No product reviews yet.</p>
                    @endforelse
                </div>
            </div>
            <x-button :href="route('vendor.reviews.index')" variant="secondary">View Reviews</x-button>
        </div>
    </section>
@endsection
