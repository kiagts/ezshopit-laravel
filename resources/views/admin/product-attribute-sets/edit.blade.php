@extends('layouts.admin')

@section('title', 'Edit Attribute Set')

@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <x-badge tone="brand">Edit set</x-badge>
            <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">{{ $attributeSet->name }}</h1>
            <p class="mt-2 text-sm leading-6 text-neutral-600">Configure the attributes future product forms should show for categories using this set.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.attribute-sets.index') }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">Back to attribute sets</a>
            <form method="POST" action="{{ route('admin.attribute-sets.destroy', $attributeSet) }}">
                @csrf
                @method('DELETE')
                <x-button type="submit" variant="danger" size="sm">Delete</x-button>
            </form>
        </div>
    </div>

    @error('attribute_set')
        <x-alert type="danger" class="mb-6">{{ $message }}</x-alert>
    @enderror

    @include('admin.product-attribute-sets._form', [
        'attributeSet' => $attributeSet,
        'attributes' => $attributes,
        'assignments' => $assignments,
        'action' => route('admin.attribute-sets.update', $attributeSet),
        'method' => 'PUT',
        'submitLabel' => 'Save Attribute Set',
    ])
@endsection
