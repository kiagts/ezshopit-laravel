@extends('account.layout')

@section('title', 'Saved Products')
@section('description', 'View your saved marketplace products.')
@section('account_active', 'wishlist')
@section('account_badge', 'Wishlist')
@section('account_title', 'Saved Products')
@section('account_description')
    {{ number_format($items->total()) }} {{ str('item')->plural($items->total()) }} saved for later.
@endsection

@section('account_content')
    @if ($items->total() === 0)
        <div class="rounded-lg border border-dashed border-neutral-300 bg-white p-10 text-center shadow-soft">
            <h2 class="text-xl font-semibold text-neutral-950">Your wishlist is empty.</h2>
            <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-neutral-600">Save products you love and come back to them anytime.</p>
            <x-button :href="route('home')" class="mt-6">Explore Products</x-button>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($items as $item)
                @php
                    $product = $item->product;
                    $isPublic = $product && in_array($product->id, $publicProductIds, true);
                @endphp

                @if ($isPublic)
                    <x-storefront.product-card :product="$product" :is-wishlisted="true" />
                @else
                    <article class="flex min-h-72 flex-col justify-between rounded-lg border border-dashed border-neutral-300 bg-white p-6 shadow-soft">
                        <div>
                            <div class="flex h-14 w-14 items-center justify-center rounded-md bg-neutral-100 text-neutral-500" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="h-6 w-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78Z" />
                                </svg>
                            </div>
                            <h2 class="mt-5 text-lg font-semibold text-neutral-950">This product is currently unavailable.</h2>
                            <p class="mt-2 text-sm leading-6 text-neutral-600">It remains saved here, but marketplace details are hidden until it becomes public again.</p>
                        </div>
                        <form method="POST" action="{{ route('wishlist.destroy', ['product' => $item->product_id]) }}" class="mt-6">
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" variant="secondary">Remove</x-button>
                        </form>
                    </article>
                @endif
            @endforeach
        </div>

        <div class="mt-8">
            {{ $items->links() }}
        </div>
    @endif
@endsection
