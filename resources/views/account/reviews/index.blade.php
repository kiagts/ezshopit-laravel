@extends('account.layout')

@section('title', 'My Reviews')
@section('account_active', 'reviews')
@section('account_badge', 'Reviews')
@section('account_title', 'My Reviews')
@section('account_description', 'Only delivered purchases can be reviewed.')

@section('account_content')
    <div class="space-y-8">
        <section class="rounded-lg border border-neutral-200 bg-white shadow-soft">
            <div class="border-b border-neutral-100 p-5">
                <h2 class="font-semibold text-neutral-950">Awaiting Review</h2>
            </div>
            <div class="divide-y divide-neutral-100">
                @forelse ($awaitingReviewItems as $item)
                    <article class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-semibold text-neutral-950">{{ $item->product_name }}</p>
                            @if ($item->variant_description)
                                <p class="mt-1 text-sm text-neutral-600">{{ $item->variant_description }}</p>
                            @endif
                            <p class="mt-1 text-xs text-neutral-500">Delivered {{ $item->vendorOrder->delivered_at?->format('M j, Y') }}</p>
                        </div>
                        <x-button :href="route('account.order-items.reviews.create', $item)" variant="secondary">Write Review</x-button>
                    </article>
                @empty
                    <x-empty-state title="Nothing awaiting review" description="Delivered products you have not reviewed yet will appear here." class="m-5" />
                @endforelse
            </div>
        </section>

        <section class="rounded-lg border border-neutral-200 bg-white shadow-soft">
            <div class="border-b border-neutral-100 p-5">
                <h2 class="font-semibold text-neutral-950">Your Reviews</h2>
            </div>
            <div class="divide-y divide-neutral-100">
                @forelse ($reviews as $review)
                    <article class="grid gap-3 p-5 text-sm md:grid-cols-[1fr_8rem_7rem_8rem] md:items-center">
                        <div>
                            <p class="font-semibold text-neutral-950">{{ $review->product?->name ?? 'Product unavailable' }}</p>
                            <p class="mt-1 text-neutral-600">{{ $review->title ?: str($review->body)->limit(80) }}</p>
                        </div>
                        <x-star-rating :rating="$review->rating" />
                        <x-badge :tone="$review->status->badgeTone()">{{ $review->status->label() }}</x-badge>
                        <x-button :href="route('account.reviews.edit', $review)" variant="secondary" size="sm">Edit</x-button>
                    </article>
                @empty
                    <x-empty-state title="No reviews yet" description="Your submitted product reviews will appear here." class="m-5" />
                @endforelse
            </div>
        </section>

        <div>{{ $reviews->links() }}</div>
    </div>
@endsection
