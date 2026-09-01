@extends('layouts.admin')

@section('title', 'Create Attribute Set')

@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <x-badge tone="brand">New set</x-badge>
            <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Create Attribute Set</h1>
            <p class="mt-2 text-sm leading-6 text-neutral-600">Build a reusable product-type configuration for future catalog forms.</p>
        </div>
        <a href="{{ route('admin.attribute-sets.index') }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">Back to attribute sets</a>
    </div>

    @include('admin.product-attribute-sets._form', [
        'attributeSet' => $attributeSet,
        'attributes' => $attributes,
        'assignments' => $assignments,
        'action' => route('admin.attribute-sets.store'),
        'method' => 'POST',
        'submitLabel' => 'Create Attribute Set',
    ])
@endsection
