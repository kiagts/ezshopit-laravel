@php
    use App\Support\Money;
@endphp

@extends('layouts.vendor')

@section('title', 'Payouts')

@section('content')
    <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <x-badge tone="brand">Payouts</x-badge>
            <h1 class="mt-4 text-2xl font-semibold text-neutral-950">Vendor Payouts</h1>
            <p class="mt-2 text-sm text-neutral-600">Requestable balance comes from available ledger money minus active payout reservations.</p>
        </div>
        <x-button :href="route('vendor.payout-methods.index')" variant="secondary">Manage Payout Methods</x-button>
    </div>

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ([['Available Balance', $balance->availableLedgerBalance], ['Reserved for Payout', $balance->activePayoutReserved], ['Requestable Balance', $balance->requestableBalance], ['Minimum Payout', $balance->minimumPayout], ['Total Paid Out', $balance->paidOutTotal]] as [$label, $amount])
            <x-card>
                <p class="text-xs font-semibold uppercase text-neutral-500">{{ $label }}</p>
                <p class="mt-3 text-xl font-semibold text-neutral-950">{{ Money::format($amount, $balance->currency) }}</p>
            </x-card>
        @endforeach
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-[1fr_22rem]">
        <section class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
            <div class="border-b border-neutral-100 px-5 py-4">
                <h2 class="text-base font-semibold text-neutral-950">Payout History</h2>
            </div>
            <div class="divide-y divide-neutral-100">
                @forelse ($payouts as $payout)
                    <article class="grid gap-3 px-5 py-4 text-sm md:grid-cols-[11rem_1fr_8rem_8rem_7rem] md:items-center">
                        <a href="{{ route('vendor.payouts.show', $payout) }}" class="font-semibold text-brand-700 hover:text-brand-900">{{ $payout->payout_number }}</a>
                        <span class="text-neutral-600">{{ $payout->methodLabel() }} · {{ $payout->maskedDestinationSnapshot() }}</span>
                        <span class="font-semibold text-neutral-950">{{ Money::format($payout->amount, $payout->currency) }}</span>
                        <x-badge :tone="$payout->status->badgeTone()">{{ $payout->status->label() }}</x-badge>
                        <span class="text-xs text-neutral-500">{{ $payout->requested_at->format('M j, Y') }}</span>
                    </article>
                @empty
                    <x-empty-state title="No payout requests" description="Payout requests will appear here after you submit one." class="m-5" />
                @endforelse
            </div>
        </section>

        <aside class="space-y-6">
            @if ($activePayout)
                <x-card>
                    <x-badge :tone="$activePayout->status->badgeTone()">{{ $activePayout->status->label() }}</x-badge>
                    <h2 class="mt-4 text-base font-semibold text-neutral-950">Payout in Progress</h2>
                    <p class="mt-2 text-sm text-neutral-600">{{ $activePayout->payout_number }} reserves {{ Money::format($activePayout->amount, $activePayout->currency) }}.</p>
                    <x-button :href="route('vendor.payouts.show', $activePayout)" variant="secondary" class="mt-4 w-full">View Request</x-button>
                </x-card>
            @endif

            <x-card>
                <h2 class="text-base font-semibold text-neutral-950">Request Payout</h2>
                <p class="mt-2 text-sm text-neutral-600">Available to request: {{ Money::format($balance->requestableBalance, $balance->currency) }}</p>

                @if (! auth()->user()->vendor?->isApproved())
                    <x-alert type="warning" class="mt-4">Your vendor account must be approved before requesting payouts.</x-alert>
                @elseif ($activePayout)
                    <x-alert type="warning" class="mt-4">You already have a payout in progress.</x-alert>
                @elseif ($methods->isEmpty())
                    <x-alert type="warning" class="mt-4">Add a payout method before requesting funds.</x-alert>
                @else
                    <form method="POST" action="{{ route('vendor.payouts.store') }}" class="mt-5 space-y-4">
                        @csrf
                        <x-input label="Amount" name="amount" value="{{ old('amount') }}" placeholder="{{ Money::input($balance->requestableBalance) }}" inputmode="decimal" required />
                        <label class="block">
                            <span class="mb-2 block text-sm font-medium text-neutral-800">Payout method</span>
                            <select name="payout_method_id" class="block h-11 w-full rounded-md border border-neutral-300 bg-white px-3 text-sm text-neutral-950 shadow-sm transition focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
                                @foreach ($methods as $method)
                                    <option value="{{ $method->id }}" @selected(old('payout_method_id', $methods->firstWhere('is_default', true)?->id) == $method->id)>
                                        {{ $method->label }} · {{ $method->maskedDestination() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('payout_method_id')
                                <span class="mt-2 block text-sm text-red-700">{{ $message }}</span>
                            @enderror
                        </label>
                        <x-button type="submit" class="w-full" @disabled($balance->requestableBalance < $balance->minimumPayout)>Request Payout</x-button>
                    </form>
                @endif
            </x-card>
        </aside>
    </div>

    <div class="mt-6">{{ $payouts->links() }}</div>
@endsection
