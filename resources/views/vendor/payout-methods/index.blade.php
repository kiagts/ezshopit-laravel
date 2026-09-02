@extends('layouts.vendor')

@section('title', 'Payout Methods')

@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <x-badge tone="brand">Payouts</x-badge>
            <h1 class="mt-4 text-2xl font-semibold text-neutral-950">Payout Methods</h1>
            <p class="mt-2 text-sm text-neutral-600">Bank destinations are encrypted at rest and shown masked in operational views.</p>
        </div>
        <x-button :href="route('vendor.payout-methods.create')">Add Bank Account</x-button>
    </div>

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($methods as $method)
            <x-card>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-semibold text-neutral-950">{{ $method->label }}</h2>
                            @if ($method->is_default)
                                <x-badge tone="success">Default</x-badge>
                            @endif
                        </div>
                        <p class="mt-2 text-sm text-neutral-600">{{ $method->bank_name }} · {{ $method->maskedDestination() }}</p>
                        <p class="mt-1 text-xs text-neutral-500">{{ $method->account_holder_name }}</p>
                    </div>
                    <div class="flex flex-wrap justify-end gap-2">
                        @unless ($method->is_default)
                            <form method="POST" action="{{ route('vendor.payout-methods.default', $method) }}">
                                @csrf
                                <x-button type="submit" variant="secondary" size="sm">Set Default</x-button>
                            </form>
                        @endunless
                        <x-button :href="route('vendor.payout-methods.edit', $method)" variant="secondary" size="sm">Edit</x-button>
                        <form method="POST" action="{{ route('vendor.payout-methods.destroy', $method) }}">
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" variant="danger" size="sm">Remove</x-button>
                        </form>
                    </div>
                </div>
            </x-card>
        @empty
            <x-empty-state title="No payout methods" description="Add a bank account before requesting a vendor payout." />
        @endforelse
    </div>
@endsection
