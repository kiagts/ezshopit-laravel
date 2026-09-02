@extends('layouts.admin')

@section('title', 'Product Reviews')

@section('content')
    <div class="mb-8">
        <x-badge tone="brand">Moderation</x-badge>
        <h1 class="mt-4 text-2xl font-semibold text-neutral-950">Product Reviews</h1>
        <p class="mt-2 text-sm text-neutral-600">Moderate verified customer reviews before they become public.</p>
    </div>

    <form method="GET" action="{{ route('admin.reviews.index') }}" class="mb-6 grid gap-3 rounded-lg border border-neutral-200 bg-white p-4 shadow-sm md:grid-cols-6">
        <input name="search" value="{{ $filters['search'] }}" placeholder="Product or review" class="h-10 rounded-md border border-neutral-300 px-3 text-sm">
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
        <select name="vendor_id" class="h-10 rounded-md border border-neutral-300 px-3 text-sm">
            <option value="">All vendors</option>
            @foreach ($vendors as $vendor)
                <option value="{{ $vendor->id }}" @selected((string) $filters['vendor_id'] === (string) $vendor->id)>{{ $vendor->display_name }}</option>
            @endforeach
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
                <article class="grid gap-3 p-5 text-sm md:grid-cols-[1fr_9rem_8rem_8rem] md:items-center">
                    <div>
                        <a href="{{ route('admin.reviews.show', $review) }}" class="font-semibold text-brand-700 hover:text-brand-900">{{ $review->product?->name ?? 'Product unavailable' }}</a>
                        <p class="mt-1 text-neutral-600">{{ $review->product?->vendor?->display_name }} · {{ $review->reviewer_display_name }}</p>
                    </div>
                    <x-star-rating :rating="$review->rating" />
                    <x-badge :tone="$review->status->badgeTone()">{{ $review->status->adminLabel() }}</x-badge>
                    <x-button :href="route('admin.reviews.show', $review)" variant="secondary" size="sm">Review</x-button>
                </article>
            @empty
                <x-empty-state title="No reviews" description="Customer reviews will appear here for moderation." class="m-5" />
            @endforelse
        </div>
    </section>

    <div class="mt-6">{{ $reviews->links() }}</div>
@endsection
