@extends('account.layout')

@section('title', 'Write Review')
@section('account_active', 'reviews')
@section('account_badge', 'Verified Purchase')
@section('account_title', 'Write a Review')
@section('account_description')
    {{ $orderItem->product_name }}{{ $orderItem->variant_description ? ' · '.$orderItem->variant_description : '' }}
@endsection

@section('account_content')
    <form method="POST" action="{{ route('account.order-items.reviews.store', $orderItem) }}" class="space-y-5 rounded-lg border border-neutral-200 bg-white p-6 shadow-soft">
        @csrf
        @include('account.reviews._form')
        <x-button type="submit">Submit Review</x-button>
    </form>
@endsection
