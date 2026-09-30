@extends('layouts.vendor')

@section('title', 'Create Promotion')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-neutral-950">Create Promotion</h1>
        <p class="mt-1 text-sm text-neutral-600">Vendor-funded coupons apply only to eligible items from your store.</p>
    </div>

    @include('vendor.promotions._form', [
        'action' => route('vendor.promotions.store'),
        'method' => 'POST',
        'submitLabel' => 'Create Promotion',
    ])
@endsection
