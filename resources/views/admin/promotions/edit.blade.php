@extends('layouts.admin')

@section('title', 'Edit Promotion')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-neutral-950">Edit Promotion</h1>
        <p class="mt-1 text-sm text-neutral-600">{{ $promotion->code }}</p>
    </div>

    @include('admin.promotions._form', [
        'action' => route('admin.promotions.update', $promotion),
        'method' => 'PUT',
        'submitLabel' => 'Update Promotion',
    ])
@endsection
