@extends('layouts.vendor')

@section('title', 'Create Product')

@section('content')
    <div class="mb-8">
        <x-badge tone="brand">New product</x-badge>
        <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Create Product</h1>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-neutral-600">Start with a draft. Complete products can be submitted for marketplace review from the edit screen.</p>
    </div>

    @include('vendor.products._form', [
        'action' => route('vendor.products.store'),
        'method' => 'POST',
        'submitLabel' => 'Save Draft',
    ])
@endsection
