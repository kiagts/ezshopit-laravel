@php
    $currentRating = (int) old('rating', $review->rating ?? 5);
@endphp

<div>
    <span class="mb-2 block text-sm font-medium text-neutral-800">Rating</span>
    <div class="flex flex-wrap gap-2">
        @for ($rating = 5; $rating >= 1; $rating--)
            <label class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm font-medium text-neutral-700 has-[:checked]:border-brand-700 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-800">
                <input type="radio" name="rating" value="{{ $rating }}" @checked($currentRating === $rating) class="sr-only">
                <x-star-rating :rating="$rating" />
                <span>{{ $rating }}</span>
            </label>
        @endfor
    </div>
    @error('rating')
        <span class="mt-2 block text-sm text-red-700">{{ $message }}</span>
    @enderror
</div>

<x-input label="Title" name="title" value="{{ old('title', $review->title ?? '') }}" placeholder="Short summary" />
<x-textarea label="Review" name="body" placeholder="Share what stood out after receiving the product.">{{ old('body', $review->body ?? '') }}</x-textarea>
