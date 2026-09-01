@extends('layouts.app')

@section('title', 'Saved Products')
@section('description', 'View your saved marketplace products.')

@section('content')
    <x-container class="py-10 sm:py-14">
        <div class="grid gap-8 lg:grid-cols-[18rem_1fr]">
            <x-account.nav active="wishlist" />

            <section>
                <div class="mb-8">
                    <x-badge tone="brand">Wishlist</x-badge>
                    <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Saved Products</h1>
                    <p class="mt-2 text-sm leading-6 text-neutral-600">
                        {{ number_format($items->total()) }} {{ str('item')->plural($items->total()) }} saved for later.
                    </p>
                </div>

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
                                        <div class="flex h-14 w-14 items-center justify-center rounded-md bg-neutral-100 text-xl text-neutral-500" aria-hidden="true">♡</div>
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
            </section>
        </div>
    </x-container>
@endsection
