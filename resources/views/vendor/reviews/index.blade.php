@extends('layouts.vendor')

@section('title', 'Product Reviews')

@section('content')
    <div class="mb-8">
        <x-badge tone="brand">Reviews</x-badge>
        <h1 class="mt-4 text-2xl font-semibold text-neutral-950">Product Reviews</h1>
        <p class="mt-2 text-sm text-neutral-600">Read-only customer feedback for products in your catalog.</p>
    </div>

    <form method="GET" action="{{ route('vendor.reviews.index') }}" class="mb-6 grid gap-3 rounded-lg border border-neutral-200 bg-white p-4 shadow-sm md:grid-cols-5">
        <input name="search" value="{{ $filters['search'] }}" placeholder="Product" class="h-10 rounded-md border border-neutral-300 px-3 text-sm">
        <select name="status" class="h-10 rounded-md border border-neutral-300 px-3 text-sm">
            <option value="">All statuses</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->adminLabel() }}</option>
            @endforeach
        </select>
        <select name="rating" class="h-10 rounded-md border border-neutral-300 px-3 text-sm">
            <option value="">All ratings</option>
            @for ($rating = 5; $rating >= 1; $rating--)
                <option value="{{ $rating }}" @selected((string) $filters['rating'] === (string) $rating)>{{ $rating }} stars</option>
            @endfor
        </select>
        <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="h-10 rounded-md border border-neutral-300 px-3 text-sm">
        <div class="flex gap-2">
            <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="h-10 min-w-0 flex-1 rounded-md border border-neutral-300 px-3 text-sm">
            <x-button type="submit" variant="secondary">Filter</x-button>
        </div>
    </form>

    <section class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        <div class="divide-y divide-neutral-100">
            @forelse ($reviews as $review)
                <article class="grid gap-3 p-5 text-sm md:grid-cols-[1fr_8rem_7rem_8rem] md:items-center">
                    <div>
                        <p class="font-semibold text-neutral-950">{{ $review->product?->name ?? 'Product unavailable' }}</p>
                        <p class="mt-1 text-neutral-600">{{ $review->title ?: str($review->body)->limit(100) }}</p>
                        <p class="mt-1 text-xs text-neutral-500">{{ $review->reviewer_display_name }} · {{ $review->submitted_at->format('M j, Y') }}</p>
                    </div>
                    <x-star-rating :rating="$review->rating" />
                    <x-badge :tone="$review->status->badgeTone()">{{ $review->status->adminLabel() }}</x-badge>
                    <span class="text-xs font-medium text-neutral-500">Read-only</span>
                </article>
            @empty
                <x-empty-state title="No reviews" description="Verified customer reviews for your products will appear here." class="m-5" />
            @endforelse
        </div>
    </section>

    <div class="mt-6">{{ $reviews->links() }}</div>
@endsection
