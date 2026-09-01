@props([
    'product',
    'isWishlisted' => false,
    'variant' => 'large',
    'badge' => null,
])

@php
    use Illuminate\Support\Facades\Storage;

    $href = route('storefront.products.show', $product);

    $image = $product->images?->first();

    $price = $product->price ?? $product->activeVariant?->price ?? null;

    $canShowWishlist = ! auth()->user()?->isVendor()
        && ! auth()->user()?->isAdmin();

    $guestWishlistUrl = route('login');

    $isLarge = $variant === 'large';
@endphp


<article
    {{ $attributes->class([
        'group relative overflow-hidden rounded-xl border border-neutral-200 bg-white transition hover:border-neutral-300 hover:shadow-md',
        'flex h-full flex-col',
    ]) }}
>

    {{-- ========================================================= --}}
    {{-- IMAGE --}}
    {{-- ========================================================= --}}
    <div
        class="relative shrink-0 overflow-hidden bg-neutral-50
            {{ $isLarge ? 'h-56' : 'h-28' }}"
    >

        <a
            href="{{ $href }}"
            class="block h-full w-full"
            aria-label="View {{ $product->name }}"
        >

            @if ($image)

                <img
                    src="{{ Storage::url($image->path) }}"
                    alt="{{ $image->alt_text ?: $product->name }}"
                    class="h-full w-full object-contain p-4 transition duration-300 group-hover:scale-105"
                >

            @else

                <x-storefront.image-placeholder
                    :label="$product->name"
                    class="h-full w-full"
                />

            @endif

        </a>


        {{-- Badge --}}
        @if ($badge)

            <span
                class="absolute left-3 top-3 rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-semibold text-emerald-700"
            >
                {{ $badge }}
            </span>

        @endif


        {{-- Wishlist --}}
        @if ($canShowWishlist && auth()->guest())

            <a
                href="{{ $guestWishlistUrl }}"
                class="absolute right-3 top-3 inline-flex h-8 w-8 items-center justify-center rounded-full bg-white text-neutral-600 shadow-sm ring-1 ring-neutral-200 transition hover:text-red-500"
                aria-label="Sign in to save to wishlist"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    class="h-4 w-4"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 000-7.78z"
                    />
                </svg>
            </a>

        @elseif ($canShowWishlist)

            <form
                method="POST"
                action="{{ $isWishlisted
                    ? route('wishlist.destroy', $product)
                    : route('wishlist.store', $product) }}"
                class="absolute right-3 top-3"
            >
                @csrf

                @if ($isWishlisted)
                    @method('DELETE')
                @endif

                <button
                    type="submit"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-white shadow-sm ring-1 ring-neutral-200 transition
                    {{ $isWishlisted
                        ? 'text-red-500'
                        : 'text-neutral-600 hover:text-red-500'
                    }}"
                    aria-label="{{ $isWishlisted ? 'Remove from wishlist' : 'Save to wishlist' }}"
                >

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="{{ $isWishlisted ? 'currentColor' : 'none' }}"
                        stroke="currentColor"
                        stroke-width="1.8"
                        class="h-4 w-4"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 000-7.78z"
                        />
                    </svg>

                </button>

            </form>

        @endif

    </div>


    {{-- ========================================================= --}}
    {{-- CONTENT --}}
    {{-- ========================================================= --}}
    <div
        class="flex min-h-0 flex-1 flex-col
        {{ $isLarge ? 'p-4' : 'p-3' }}"
    >

        {{-- Vendor --}}
        @if ($product->vendor)

            <p class="truncate text-[10px] font-medium text-neutral-400">
                {{ $product->vendor?->display_name }}
            </p>

        @endif


        {{-- Name --}}
        <a
            href="{{ $href }}"
            class="mt-1 line-clamp-2 font-semibold text-neutral-950 hover:text-emerald-700
            {{ $isLarge ? 'text-sm leading-5' : 'text-xs leading-4' }}"
        >
            {{ $product->name }}
        </a>


        {{-- Price --}}
        <div class="mt-auto flex items-center justify-between gap-2 pt-3">

            <div class="min-w-0 text-xs">
                <x-storefront.price :price="$price" />
            </div>


            <a
                href="{{ $href }}"
                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-700 text-white transition hover:bg-emerald-800"
                aria-label="View {{ $product->name }}"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    class="h-4 w-4"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M3 3h2l1.6 9.4a2 2 0 002 1.6h7.8a2 2 0 002-1.6L20 6H6"
                    />
                    <circle cx="9" cy="20" r="1" />
                    <circle cx="17" cy="20" r="1" />
                </svg>
            </a>

        </div>

    </div>

</article>