@extends('layouts.vendor')

@section('title', 'Add Payout Method')

@section('content')
    <div class="mb-8">
        <x-badge tone="brand">Bank transfer</x-badge>
        <h1 class="mt-4 text-2xl font-semibold text-neutral-950">Add Payout Method</h1>
        <p class="mt-2 text-sm text-neutral-600">Enter the bank account where you want marketplace payouts sent.</p>
    </div>

    <form method="POST" action="{{ route('vendor.payout-methods.store') }}" class="rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
        @csrf
        @include('vendor.payout-methods._form')
        <div class="mt-6 flex gap-3">
            <x-button type="submit">Save Method</x-button>
            <x-button :href="route('vendor.payout-methods.index')" variant="secondary">Cancel</x-button>
        </div>
    </form>
@endsection
