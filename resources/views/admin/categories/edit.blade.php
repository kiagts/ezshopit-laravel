@extends('layouts.admin')

@section('title', 'Edit '.$category->name)

@section('content')
    <div class="mb-8">
        <a href="{{ route('admin.categories.index') }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">Back to categories</a>
        <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <x-badge :tone="$category->is_active ? 'success' : 'neutral'">{{ $category->is_active ? 'Active' : 'Inactive' }}</x-badge>
                <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Edit {{ $category->name }}</h1>
                <p class="mt-2 text-sm leading-6 text-neutral-600">Changing the name does not change the slug unless you edit the slug field.</p>
            </div>

            <form method="POST" action="{{ route('admin.categories.destroy', $category) }}">
                @csrf
                @method('DELETE')
                <x-button type="submit" variant="danger">Delete Category</x-button>
            </form>
        </div>
    </div>

    @error('category')
        <x-alert type="danger" class="mb-6">{{ $message }}</x-alert>
    @enderror

    @include('admin.categories._form', [
        'action' => route('admin.categories.update', $category),
        'method' => 'PUT',
        'submitLabel' => 'Save Changes',
    ])
@endsection
