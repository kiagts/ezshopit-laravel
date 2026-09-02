@php
    use App\Enums\VendorPayoutStatus;
    use App\Support\Money;
@endphp

@extends('layouts.vendor')

@section('title', $payout->payout_number)

@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <x-badge :tone="$payout->status->badgeTone()">{{ $payout->status->label() }}</x-badge>
            <h1 class="mt-4 text-2xl font-semibold text-neutral-950">{{ $payout->payout_number }}</h1>
            <p class="mt-2 text-sm text-neutral-600">{{ Money::format($payout->amount, $payout->currency) }} requested {{ $payout->requested_at->format('M j, Y g:i A') }}</p>
        </div>
        <x-button :href="route('vendor.payouts.index')" variant="secondary">Back to Payouts</x-button>
    </div>

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <x-card>
            <h2 class="text-base font-semibold text-neutral-950">Payout Destination</h2>
            <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-neutral-500">Method</dt>
                    <dd class="mt-1 font-medium text-neutral-950">{{ $payout->methodLabel() }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-500">Destination</dt>
                    <dd class="mt-1 font-medium text-neutral-950">{{ $payout->maskedDestinationSnapshot() }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-500">Bank</dt>
                    <dd class="mt-1 font-medium text-neutral-950">{{ $payout->destination_snapshot['bank_name'] ?? 'Not provided' }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-500">Account holder</dt>
                    <dd class="mt-1 font-medium text-neutral-950">{{ $payout->destination_snapshot['account_holder_name'] ?? 'Not provided' }}</dd>
                </div>
            </dl>
        </x-card>

        <x-card>
            <h2 class="text-base font-semibold text-neutral-950">Lifecycle</h2>
            <div class="mt-4 space-y-3 text-sm text-neutral-600">
                <p>Requested: {{ $payout->requested_at?->format('M j, Y g:i A') }}</p>
                <p>Approved: {{ $payout->approved_at?->format('M j, Y g:i A') ?? '-' }}</p>
                <p>Processing: {{ $payout->processing_at?->format('M j, Y g:i A') ?? '-' }}</p>
                <p>Paid: {{ $payout->paid_at?->format('M j, Y g:i A') ?? '-' }}</p>
                @if ($payout->rejection_reason)
                    <p>Rejected: {{ $payout->rejection_reason }}</p>
                @endif
                @if ($payout->cancellation_reason)
                    <p>Cancelled: {{ $payout->cancellation_reason }}</p>
                @endif
            </div>

            @if ($payout->status === VendorPayoutStatus::Pending)
                <form method="POST" action="{{ route('vendor.payouts.cancel', $payout) }}" class="mt-5">
                    @csrf
                    <x-button type="submit" variant="danger" class="w-full">Cancel Request</x-button>
                </form>
            @endif
        </x-card>
    </div>
@endsection
