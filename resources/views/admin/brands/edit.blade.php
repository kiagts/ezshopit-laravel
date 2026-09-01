@extends('layouts.admin')

@section('title', 'Edit '.$brand->name)

@section('content')
    <div class="mb-8">
        <a href="{{ route('admin.brands.index') }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">Back to brands</a>
        <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <x-badge :tone="$brand->is_active ? 'success' : 'neutral'">{{ $brand->is_active ? 'Active' : 'Inactive' }}</x-badge>
                <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Edit {{ $brand->name }}</h1>
                <p class="mt-2 text-sm leading-6 text-neutral-600">Changing the name does not change the slug unless you edit the slug field.</p>
            </div>

            <form method="POST" action="{{ route('admin.brands.destroy', $brand) }}">
                @csrf
                @method('DELETE')
                <x-button type="submit" variant="danger">Delete Brand</x-button>
            </form>
        </div>
    </div>

    @include('admin.brands._form', [
        'action' => route('admin.brands.update', $brand),
        'method' => 'PUT',
        'submitLabel' => 'Save Changes',
    ])
@endsection
