@extends('layouts.vendor')

@section('title', 'Edit Payout Method')

@section('content')
    <div class="mb-8">
        <x-badge tone="brand">Bank transfer</x-badge>
        <h1 class="mt-4 text-2xl font-semibold text-neutral-950">Edit Payout Method</h1>
        <p class="mt-2 text-sm text-neutral-600">Sensitive destination fields stay blank unless you replace them.</p>
    </div>

    <form method="POST" action="{{ route('vendor.payout-methods.update', $method) }}" class="rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @include('vendor.payout-methods._form')
        <div class="mt-6 flex gap-3">
            <x-button type="submit">Update Method</x-button>
            <x-button :href="route('vendor.payout-methods.index')" variant="secondary">Cancel</x-button>
        </div>
    </form>
@endsection
