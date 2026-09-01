@extends('layouts.admin')

@section('title', 'Create Category')

@section('content')
    <div class="mb-8">
        <a href="{{ route('admin.categories.index') }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">Back to categories</a>
        <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Create category</h1>
        <p class="mt-2 text-sm leading-6 text-neutral-600">Add a root category or nest it under an existing marketplace category.</p>
    </div>

    @include('admin.categories._form', [
        'action' => route('admin.categories.store'),
        'method' => 'POST',
        'submitLabel' => 'Create Category',
    ])
@endsection
