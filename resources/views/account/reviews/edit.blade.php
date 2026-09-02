@extends('layouts.app')

@section('title', 'Edit Review')

@section('content')
    <x-container class="py-10 sm:py-14">
        <div class="grid gap-8 lg:grid-cols-[18rem_1fr]">
            <x-account.nav active="reviews" />
            <section>
                <div class="mb-8">
                    <x-badge :tone="$review->status->badgeTone()">{{ $review->status->label() }}</x-badge>
                    <h1 class="mt-4 text-3xl font-semibold text-neutral-950">Edit Review</h1>
                    <p class="mt-2 text-sm text-neutral-600">{{ $review->product?->name ?? $review->orderItem->product_name }}</p>
                </div>

                @if (session('status'))
                    <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
                @endif
                @if ($review->moderation_reason)
                    <x-alert type="warning" class="mb-6">{{ $review->moderation_reason }}</x-alert>
                @endif

                <form method="POST" action="{{ route('account.reviews.update', $review) }}" class="space-y-5 rounded-lg border border-neutral-200 bg-white p-6 shadow-soft">
                    @csrf
                    @method('PUT')
                    @include('account.reviews._form')
                    <x-button type="submit">Resubmit Review</x-button>
                </form>
            </section>
        </div>
    </x-container>
@endsection
