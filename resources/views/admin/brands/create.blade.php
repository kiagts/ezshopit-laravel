@extends('layouts.admin')

@section('title', 'Create Brand')

@section('content')
    <div class="mb-8">
        <a href="{{ route('admin.brands.index') }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">Back to brands</a>
        <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Create brand</h1>
        <p class="mt-2 text-sm leading-6 text-neutral-600">Add a global marketplace brand for future product assignment and filtering.</p>
    </div>

    @include('admin.brands._form', [
        'action' => route('admin.brands.store'),
        'method' => 'POST',
        'submitLabel' => 'Create Brand',
    ])
@endsection
