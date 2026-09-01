@extends('layouts.vendor')

@section('title', 'Add Shipping Method')

@section('content')
    <div class="mb-8">
        <x-badge tone="brand">Delivery setup</x-badge>
        <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Add Shipping Method</h1>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-neutral-600">Create a flat-rate delivery option for checkout.</p>
    </div>

    <section class="rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
        @include('vendor.shipping-methods._form', [
            'action' => route('vendor.shipping-methods.store'),
            'method' => $method,
            'countries' => $countries,
            'submitLabel' => 'Create Method',
        ])
    </section>
@endsection
