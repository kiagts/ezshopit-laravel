@extends('layouts.app')

@section('title', 'Write Review')

@section('content')
    <x-container class="py-10 sm:py-14">
        <div class="grid gap-8 lg:grid-cols-[18rem_1fr]">
            <x-account.nav active="reviews" />
            <section>
                <div class="mb-8">
                    <x-badge tone="success">Verified Purchase</x-badge>
                    <h1 class="mt-4 text-3xl font-semibold text-neutral-950">Write a Review</h1>
                    <p class="mt-2 text-sm text-neutral-600">{{ $orderItem->product_name }}{{ $orderItem->variant_description ? ' · '.$orderItem->variant_description : '' }}</p>
                </div>

                <form method="POST" action="{{ route('account.order-items.reviews.store', $orderItem) }}" class="space-y-5 rounded-lg border border-neutral-200 bg-white p-6 shadow-soft">
                    @csrf
                    @include('account.reviews._form')
                    <x-button type="submit">Submit Review</x-button>
                </form>
            </section>
        </div>
    </x-container>
@endsection
