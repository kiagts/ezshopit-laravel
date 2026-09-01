@extends('layouts.admin')

@section('title', 'Create Product Attribute')

@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <x-badge tone="brand">New attribute</x-badge>
            <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Create Product Attribute</h1>
            <p class="mt-2 text-sm leading-6 text-neutral-600">Prepare a reusable marketplace attribute for future product setup.</p>
        </div>
        <a href="{{ route('admin.attributes.index') }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">Back to attributes</a>
    </div>

    @include('admin.product-attributes._form', [
        'attribute' => $attribute,
        'types' => $types,
        'inputTypes' => $inputTypes,
        'action' => route('admin.attributes.store'),
        'method' => 'POST',
        'submitLabel' => 'Create Attribute',
    ])
@endsection
