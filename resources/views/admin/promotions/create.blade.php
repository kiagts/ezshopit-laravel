@extends('layouts.admin')

@section('title', 'Create Promotion')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-neutral-950">Create Promotion</h1>
        <p class="mt-1 text-sm text-neutral-600">Create a single-use coupon foundation for cart and checkout.</p>
    </div>

    @include('admin.promotions._form', [
        'action' => route('admin.promotions.store'),
        'method' => 'POST',
        'submitLabel' => 'Create Promotion',
    ])
@endsection
