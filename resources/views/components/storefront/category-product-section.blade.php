@props([
    'products' => collect(),
    'wishlistedProductIds' => [],
    'product' => null,
    'title' => 'Featured Products',
    'description' => 'Handpicked products from trusted sellers',
    'viewAllUrl' => null,
])

@php
    $featuredProduct = $product;


    /*
    |--------------------------------------------------------------------------
    | Six Small Products
    |--------------------------------------------------------------------------
    |
    | The featured demo product is separate from $products,
    | therefore we DO NOT skip the first real product.
    |
    | Desktop:
    |
    | Product 1 | Product 2 | Product 3
    | Product 4 | Product 5 | Product 6
    |
    */

    $smallProducts = $products
        ->take(6)
        ->values();
@endphp


@if ($featuredProduct)

    <section
        {{ $attributes->class([
            'bg-white py-8 sm:py-10',
        ]) }}
    >

        <x-container>

            {{-- =========================================================
                SECTION HEADER
            ========================================================== --}}

            <div
                class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
            >

                {{-- Left --}}
                <div>

                    <h2
                        class="text-xl font-bold tracking-tight text-neutral-950 sm:text-2xl"
                    >
                        {{ $title }}
                    </h2>


                    @if ($description)

                        <p
                            class="mt-1 max-w-2xl text-xs leading-5 text-neutral-500 sm:text-sm"
                        >
                            {{ $description }}
                        </p>

                    @endif

                </div>


                {{-- View All --}}
                @if ($viewAllUrl)

                    <a
                        href="{{ $viewAllUrl }}"
                        class="hidden shrink-0 items-center gap-2 text-sm font-semibold text-emerald-700 transition hover:text-emerald-800 sm:inline-flex"
                    >

                        <span>
                            View all products
                        </span>

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

                @endif

            </div>


            {{-- =========================================================
                PRODUCT LAYOUT
            ========================================================== --}}

            <div
                class="grid items-stretch gap-4 xl:grid-cols-[minmax(0,1.45fr)_minmax(0,1.3fr)]"
            >

                {{-- =====================================================
                    LARGE FEATURED CARD
                ====================================================== --}}

                <div class="min-w-0">

                    <x-storefront.category-product-card
                        :product="$featuredProduct"
                    />

                </div>


                {{-- =====================================================
                    SIX STANDARD PRODUCT CARDS
                ====================================================== --}}

                @if ($smallProducts->isNotEmpty())

                    <div
                        class="grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-3"
                    >

                        @foreach ($smallProducts as $index => $smallProduct)


                            <div class="min-w-0">

                                <x-storefront.product-card
                                    :product="$smallProduct"
                                    :is-wishlisted="in_array(
                                        $smallProduct->id,
                                        $wishlistedProductIds,
                                        true
                                    )"
                                    :badge="$smallProduct->is_featured ? 'Featured' : null"
                                    class="h-full"
                                />

                            </div>

                        @endforeach

                    </div>

                @endif

            </div>


            {{-- =========================================================
                MOBILE VIEW ALL
            ========================================================== --}}

            @if ($viewAllUrl)

                <div class="mt-6 sm:hidden">

                    <a
                        href="{{ $viewAllUrl }}"
                        class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-700"
                    >

                        <span>
                            View all products
                        </span>

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

            @endif

        </x-container>

    </section>

@endif
