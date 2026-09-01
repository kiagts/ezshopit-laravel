@props([
    'product',
    'isWishlisted' => false,
    'showVendor' => true,
    'badge' => null,
])

@php
    use Illuminate\Support\Facades\Storage;

    $href = route('storefront.products.show', $product);

    $image = $product->images?->first();

    $price = $product->price ?? $product->activeVariant?->sale_price ?? null;

    $canShowWishlist = ! auth()->user()?->isVendor()
        && ! auth()->user()?->isAdmin();

    $guestWishlistUrl = route('login');

    $rating = $product->rating ?? null;
    $reviewCount = $product->review_count ?? null;

    /*
    |--------------------------------------------------------------------------
    | Badge
    |--------------------------------------------------------------------------
    |
    | We normalize the Blade prop into a local variable so it always exists.
    |
    */

    $productBadge = $badge ?? null;
@endphp

<article
    {{ $attributes->class([
        'group relative flex h-full min-w-0 flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white transition duration-200 hover:border-neutral-300 hover:shadow-md'
    ]) }}
>

    {{-- ========================================================= --}}
    {{-- PRODUCT IMAGE --}}
    {{-- ========================================================= --}}
    <div class="relative aspect-square overflow-hidden bg-neutral-50">

        <a
            href="{{ $href }}"
            class="block h-full w-full"
            aria-label="View {{ $product->name }}"
        >

            @if ($image)

                <img
                    src="{{ Storage::url($image->path) }}"
                    alt="{{ $image->alt_text ?: $product->name }}"
                    class="h-full w-full object-contain p-4 transition duration-300 group-hover:scale-105 sm:p-5"
                >

            @else

                <x-storefront.image-placeholder
                    :label="$product->name"
                    class="h-full w-full"
                />

            @endif

        </a>


        {{-- ===================================================== --}}
        {{-- BADGE --}}
        {{-- ===================================================== --}}
        @if (!empty($productBadge))

            @php
                $badgeClasses = match (strtolower($productBadge)) {
                    'new' => 'bg-emerald-50 text-emerald-700',
                    'bestseller' => 'bg-amber-50 text-amber-700',
                    'sale' => 'bg-rose-50 text-rose-700',
                    'low stock' => 'bg-orange-50 text-orange-700',
                    default => 'bg-neutral-100 text-neutral-700',
                };
            @endphp

            <span
                class="absolute left-3 top-3 inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $badgeClasses }}"
            >
                {{ $productBadge }}
            </span>

        @endif


        {{-- ===================================================== --}}
        {{-- WISHLIST --}}
        {{-- ===================================================== --}}
        @if ($canShowWishlist && auth()->guest())

            <a
                href="{{ $guestWishlistUrl }}"
                class="absolute right-3 top-3 inline-flex h-8 w-8 items-center justify-center rounded-full bg-white text-neutral-600 shadow-sm ring-1 ring-neutral-200 transition hover:text-red-500 hover:ring-neutral-300"
                aria-label="Sign in to save to wishlist"
                title="Sign in to save to wishlist"
            >

                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    class="h-[18px] w-[18px]"
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
                    class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-white shadow-sm ring-1 ring-neutral-200 transition hover:ring-neutral-300 {{ $isWishlisted ? 'text-red-500' : 'text-neutral-600 hover:text-red-500' }}"
                    aria-label="{{ $isWishlisted ? 'Remove from wishlist' : 'Save to wishlist' }}"
                    title="{{ $isWishlisted ? 'Remove from wishlist' : 'Save to wishlist' }}"
                >

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="{{ $isWishlisted ? 'currentColor' : 'none' }}"
                        stroke="currentColor"
                        stroke-width="1.8"
                        class="h-[18px] w-[18px]"
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
    {{-- PRODUCT INFO --}}
    {{-- ========================================================= --}}
    <div class="flex flex-1 flex-col px-4 pb-4 pt-3">

        {{-- Vendor --}}
        @if ($showVendor && $product->vendor)

            <p class="truncate text-[11px] font-medium text-neutral-400">
                {{ $product->vendor?->display_name }}
            </p>

        @endif


        {{-- Product Name --}}
        <a
            href="{{ $href }}"
            class="mt-1 line-clamp-2 min-h-[40px] text-sm font-semibold leading-5 text-neutral-900 transition hover:text-brand-700"
        >
            {{ $product->name }}
        </a>


        {{-- Rating --}}
        @if ($rating)

            <div class="mt-2 flex items-center gap-1.5">

                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                    class="h-4 w-4 text-amber-400"
                >
                    <path
                        d="M12 2.5l2.91 5.9 6.51.95-4.71 4.59 1.11 6.48L12 17.36l-5.82 3.06 1.11-6.48-4.71-4.59 6.51-.95L12 2.5z"
                    />
                </svg>

                <span class="text-xs font-semibold text-neutral-700">
                    {{ number_format($rating, 1) }}
                </span>

                @if ($reviewCount)

                    <span class="text-[11px] text-neutral-400">
                        ({{ number_format($reviewCount) }})
                    </span>

                @endif

            </div>

        @endif


        {{-- ===================================================== --}}
        {{-- PRICE / ACTION --}}
        {{-- ===================================================== --}}
        <div class="mt-auto flex items-end justify-between gap-3 pt-4">

            <div class="min-w-0">
                <x-storefront.price :price="$price" />
            </div>

            <a
                href="{{ $href }}"
                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-700 text-white transition hover:bg-emerald-800"
                aria-label="View {{ $product->name }}"
            >

                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    class="h-[17px] w-[17px]"
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