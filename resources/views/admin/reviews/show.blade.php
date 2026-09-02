@php
    use App\Enums\ProductReviewStatus;
@endphp

@extends('layouts.admin')

@section('title', 'Review #'.$review->id)

@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <x-badge :tone="$review->status->badgeTone()">{{ $review->status->adminLabel() }}</x-badge>
            <h1 class="mt-4 text-2xl font-semibold text-neutral-950">Product Review</h1>
            <p class="mt-2 text-sm text-neutral-600">{{ $review->product?->name ?? 'Product unavailable' }} · {{ $review->product?->vendor?->display_name }}</p>
        </div>
        <x-button :href="route('admin.reviews.index')" variant="secondary">Back to Reviews</x-button>
    </div>

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif
    @if ($errors->any())
        <x-alert type="danger" class="mb-6">{{ $errors->first() }}</x-alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <x-card>
            <x-star-rating :rating="$review->rating" size="md" />
            <h2 class="mt-4 text-lg font-semibold text-neutral-950">{{ $review->title ?: 'Untitled review' }}</h2>
            <p class="mt-3 whitespace-pre-line text-sm leading-7 text-neutral-700">{{ $review->body }}</p>
            <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="text-neutral-500">Customer</dt><dd class="mt-1 font-medium">{{ $review->reviewer_display_name }}</dd></div>
                <div><dt class="text-neutral-500">Verified purchase</dt><dd class="mt-1 font-medium">{{ $review->verified_purchase ? 'Yes' : 'No' }}</dd></div>
                <div><dt class="text-neutral-500">Purchased variant</dt><dd class="mt-1 font-medium">{{ $review->variant_snapshot ?: '-' }}</dd></div>
                <div><dt class="text-neutral-500">Submitted</dt><dd class="mt-1 font-medium">{{ $review->submitted_at->format('M j, Y g:i A') }}</dd></div>
            </dl>
        </x-card>

        <aside class="space-y-6">
            <x-card>
                <h2 class="text-base font-semibold text-neutral-950">Moderation</h2>
                <div class="mt-4 space-y-3 text-sm text-neutral-600">
                    <p>Approved: {{ $review->approved_at?->format('M j, Y g:i A') ?? '-' }}</p>
                    <p>Rejected: {{ $review->rejected_at?->format('M j, Y g:i A') ?? '-' }}</p>
                    @if ($review->moderation_reason)
                        <p>Reason: {{ $review->moderation_reason }}</p>
                    @endif
                </div>
            </x-card>

            <x-card>
                <h2 class="text-base font-semibold text-neutral-950">Actions</h2>
                <div class="mt-4 space-y-3">
                    @if ($review->status !== ProductReviewStatus::Approved)
                        <form method="POST" action="{{ route('admin.reviews.approve', $review) }}">
                            @csrf
                            <x-button type="submit" class="w-full">Approve</x-button>
                        </form>
                    @endif
                    @if ($review->status !== ProductReviewStatus::Rejected)
                        <form method="POST" action="{{ route('admin.reviews.reject', $review) }}" class="space-y-3">
                            @csrf
                            <x-textarea name="reason" label="Reject reason" placeholder="Customer-safe moderation feedback.">{{ old('reason') }}</x-textarea>
                            <x-button type="submit" variant="danger" class="w-full">Reject</x-button>
                        </form>
                    @endif
                </div>
            </x-card>
        </aside>
    </div>
@endsection
