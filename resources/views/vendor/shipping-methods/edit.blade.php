@extends('layouts.vendor')

@section('title', 'Edit Shipping Method')

@section('content')
    <div class="mb-8">
        <x-badge tone="brand">Delivery setup</x-badge>
        <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Edit Shipping Method</h1>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-neutral-600">Update this delivery option and its country coverage.</p>
    </div>

    <section class="rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
        @include('vendor.shipping-methods._form', [
            'action' => route('vendor.shipping-methods.update', $method),
            'methodOverride' => 'PUT',
            'method' => $method,
            'countries' => $countries,
            'submitLabel' => 'Update Method',
        ])
    </section>
@endsection
