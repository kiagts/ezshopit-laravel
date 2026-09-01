@extends('layouts.admin')

@section('title', 'Edit Product Attribute')

@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <x-badge tone="brand">Edit attribute</x-badge>
            <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">{{ $attribute->name }}</h1>
            <p class="mt-2 text-sm leading-6 text-neutral-600">Codes stay stable unless changed intentionally. Future product usage may restrict type and input changes.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.attributes.index') }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">Back to attributes</a>
            <form method="POST" action="{{ route('admin.attributes.destroy', $attribute) }}">
                @csrf
                @method('DELETE')
                <x-button type="submit" variant="danger" size="sm">Delete</x-button>
            </form>
        </div>
    </div>

    @if ($errors->has('attribute'))
        <x-alert type="danger" class="mb-6">{{ $errors->first('attribute') }}</x-alert>
    @endif

    @include('admin.product-attributes._form', [
        'attribute' => $attribute,
        'types' => $types,
        'inputTypes' => $inputTypes,
        'action' => route('admin.attributes.update', $attribute),
        'method' => 'PUT',
        'submitLabel' => 'Save Attribute',
    ])

    <div class="mt-10">
        @include('admin.product-attributes._values', ['attribute' => $attribute])
    </div>
@endsection
