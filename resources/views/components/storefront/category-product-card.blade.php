@props([
    'product' => [],
])

@php
    /*
    |--------------------------------------------------------------------------
    | Product Data
    |--------------------------------------------------------------------------
    */

    $name = data_get(
        $product,
        'name',
        'Featured Product'
    );

    $brand = data_get(
        $product,
        'brand'
    );

    $seller = data_get(
        $product,
        'seller'
    );

    $description = data_get(
        $product,
        'description'
    );

    $image = data_get(
        $product,
        'image'
    );

    $url = data_get(
        $product,
        'url',
        '#'
    );


    /*
    |--------------------------------------------------------------------------
    | Price
    |--------------------------------------------------------------------------
    */

    $startingAt = data_get(
        $product,
        'starting_at',
        false
    );

    $price = data_get(
        $product,
        'price'
    );

    $oldPrice = data_get(
        $product,
        'old_price'
    );

    $discount = data_get(
        $product,
        'discount'
    );


    /*
    |--------------------------------------------------------------------------
    | Badges
    |--------------------------------------------------------------------------
    */

    $badge = data_get(
        $product,
        'badge'
    );

    $stockLabel = data_get(
        $product,
        'stock_label'
    );
@endphp


<article
    {{ $attributes->class([
        'group flex h-full flex-col overflow-hidden rounded-2xl border border-neutral-200 bg-white transition duration-300 hover:border-neutral-300 hover:shadow-lg',
    ]) }}
>

    {{-- =============================================================
        MAIN PRODUCT AREA
    ============================================================== --}}

    <div
        class="relative flex flex-1 flex-col overflow-hidden bg-gradient-to-br from-white via-neutral-50 to-neutral-100 lg:grid lg:grid-cols-[0.82fr_1.18fr]"
    >

        {{-- =========================================================
            BADGES
        ========================================================== --}}

        <div
            class="absolute left-5 top-5 z-20 flex flex-col items-start gap-2"
        >

            {{-- Promotional Badge --}}
            @if ($badge)

                <span
                    class="inline-flex rounded-lg bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700"
                >
                    {{ $badge }}
                </span>

            @endif


            {{-- Stock --}}
            @if ($stockLabel)

                <span
                    class="inline-flex rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700"
                >
                    {{ $stockLabel }}
                </span>

            @endif

        </div>


        {{-- =========================================================
            WISHLIST DECORATIVE BUTTON
        ========================================================== --}}

        <button
            type="button"
            class="absolute right-5 top-5 z-20 flex h-11 w-11 items-center justify-center rounded-full border border-neutral-200 bg-white text-neutral-600 shadow-sm transition hover:border-neutral-300 hover:text-rose-500"
            aria-label="Save {{ $name }}"
        >

            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.7"
                class="h-5 w-5"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78Z"
                />
            </svg>

        </button>


        {{-- =========================================================
            PRODUCT INFORMATION
        ========================================================== --}}

        <div
            class="relative z-10 flex flex-col justify-end px-6 pb-8 pt-28 sm:px-8 lg:justify-center lg:px-8 lg:pb-10 lg:pt-28"
        >

            {{-- Brand + Seller --}}
            @if ($brand || $seller)

                <div
                    class="mb-3 flex flex-wrap items-center gap-2 text-xs font-medium text-neutral-500"
                >

                    @if ($brand)

                        <span>
                            {{ $brand }}
                        </span>

                    @endif


                    @if ($brand && $seller)

                        <span
                            class="text-neutral-300"
                            aria-hidden="true"
                        >
                            •
                        </span>

                    @endif


                    @if ($seller)

                        <span>
                            {{ $seller }}
                        </span>

                    @endif

                </div>

            @endif


            {{-- Product Name --}}
            <a
                href="{{ $url }}"
                class="block"
            >

                <h3
                    class="max-w-md text-2xl font-bold leading-tight tracking-tight text-neutral-950 transition group-hover:text-emerald-800 sm:text-3xl"
                >
                    {{ $name }}
                </h3>

            </a>


            {{-- Description --}}
            @if ($description)

                <p
                    class="mt-4 line-clamp-3 max-w-md text-sm leading-6 text-neutral-500"
                >
                    {{ $description }}
                </p>

            @endif


            {{-- =====================================================
                PRICE
            ====================================================== --}}

            <div class="mt-8">

                {{-- Starting At --}}
                <div class="h-5">

                    @if ($startingAt)

                        <span
                            class="text-xs font-medium text-neutral-500"
                        >
                            Starting at
                        </span>

                    @endif

                </div>


                {{-- Current Price --}}
                @if ($price)

                    <p
                        class="mt-1 text-3xl font-bold tracking-tight text-emerald-700 sm:text-[34px]"
                    >
                        {{ $price }}
                    </p>

                @endif


                {{-- Old Price + Discount --}}
                <div
                    class="mt-2 flex h-7 items-center gap-2"
                >

                    @if ($oldPrice)

                        <span
                            class="text-sm font-medium text-neutral-400 line-through"
                        >
                            {{ $oldPrice }}
                        </span>

                    @endif


                    @if ($discount)

                        <span
                            class="rounded-md bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-600"
                        >
                            {{ $discount }}
                        </span>

                    @endif

                </div>

            </div>


            {{-- =====================================================
                TRUST BADGE
            ====================================================== --}}

            <div class="mt-4">

                <span
                    class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700"
                >

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        class="h-4 w-4"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 3 4.5 6v5.25c0 4.65 3.18 8.55 7.5 9.75 4.32-1.2 7.5-5.1 7.5-9.75V6L12 3Z"
                        />

                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="m9.5 12 1.7 1.7 3.6-3.9"
                        />
                    </svg>

                    Verified marketplace seller

                </span>

            </div>


            {{-- =====================================================
                BUTTON
            ====================================================== --}}

            <div class="mt-6">

                <a
                    href="{{ $url }}"
                    class="inline-flex h-11 items-center justify-center gap-3 rounded-xl bg-neutral-950 px-6 text-sm font-semibold text-white transition hover:bg-neutral-800"
                >

                    View product

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        class="h-4 w-4"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M5 12h14M13 6l6 6-6 6"
                        />
                    </svg>

                </a>

            </div>

        </div>


        {{-- =========================================================
            PRODUCT IMAGE
        ========================================================== --}}

        <div
            class="relative flex min-h-[360px] items-center justify-center overflow-hidden px-8 pb-8 pt-4 sm:min-h-[430px] lg:min-h-[600px] lg:px-6 lg:pb-12 lg:pt-24"
        >

            <a
                href="{{ $url }}"
                class="flex h-full w-full items-center justify-center"
            >

                @if ($image)

                    <img
                        src="{{ $image }}"
                        alt="{{ $name }}"
                        class="max-h-[500px] max-w-full object-contain transition duration-500 group-hover:scale-[1.03]"
                    >

                @else

                    <x-storefront.image-placeholder
                        :label="$name"
                        class="h-full min-h-[340px] w-full"
                    />

                @endif

            </a>

        </div>

    </div>

</article>