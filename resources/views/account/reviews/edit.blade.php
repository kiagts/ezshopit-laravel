@extends('account.layout')

@section('title', 'Edit Review')
@section('account_active', 'reviews')
@section('account_badge')
    {{ $review->status->label() }}
@endsection
@section('account_title', 'Edit Review')
@section('account_description')
    {{ $review->product?->name ?? $review->orderItem->product_name }}
@endsection

@section('account_content')
    @if ($review->moderation_reason)
        <x-alert type="warning" class="mb-6">{{ $review->moderation_reason }}</x-alert>
    @endif

    <form method="POST" action="{{ route('account.reviews.update', $review) }}" class="space-y-5 rounded-lg border border-neutral-200 bg-white p-6 shadow-soft">
        @csrf
        @method('PUT')
        @include('account.reviews._form')
        <x-button type="submit">Resubmit Review</x-button>
    </form>
@endsection
