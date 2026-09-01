@props([
    'products' => collect(),
    'wishlistedProductIds' => [],
    'title' => 'Featured Products',
    'description' => 'Handpicked products from trusted sellers',
    'viewAllUrl' => null,
])


@if ($products->isNotEmpty())

    <section
        {{ $attributes->class([
            'bg-white py-8 sm:py-10'
        ]) }}
        data-featured-products
    >

        <x-container>

            {{-- ========================================================= --}}
            {{-- SECTION HEADER --}}
            {{-- ========================================================= --}}
            <div
                class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
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
                            class="mt-1 text-xs leading-5 text-neutral-500 sm:text-sm"
                        >
                            {{ $description }}
                        </p>

                    @endif

                </div>


                {{-- Right --}}
                <div class="flex items-center gap-4">

                    @if ($viewAllUrl)

                        <a
                            href="{{ $viewAllUrl }}"
                            class="hidden items-center gap-2 text-xs font-semibold text-emerald-700 transition hover:text-emerald-800 sm:inline-flex"
                        >
                            View all products

                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                class="h-4 w-4"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M5 12h14M13 6l6 6-6 6"
                                />
                            </svg>

                        </a>

                    @endif


                    {{-- Slider Arrows --}}
                    <div class="hidden items-center gap-2 md:flex">

                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-neutral-200 bg-white text-neutral-600 transition hover:border-neutral-300 hover:bg-neutral-50 hover:text-neutral-950 disabled:cursor-not-allowed disabled:opacity-40"
                            data-featured-prev
                            aria-label="Previous products"
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
                                    d="M15 18l-6-6 6-6"
                                />
                            </svg>

                        </button>


                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-neutral-200 bg-white text-neutral-600 transition hover:border-neutral-300 hover:bg-neutral-50 hover:text-neutral-950 disabled:cursor-not-allowed disabled:opacity-40"
                            data-featured-next
                            aria-label="Next products"
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
                                    d="M9 6l6 6-6 6"
                                />
                            </svg>

                        </button>

                    </div>

                </div>

            </div>


            {{-- ========================================================= --}}
            {{-- PRODUCTS --}}
            {{-- ========================================================= --}}
            <div
                class="flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                data-featured-track
            >

                @foreach ($products as $index => $product)

                    @php
                        $badge = match ($index) {
                            0 => 'New',
                            1 => 'Bestseller',
                            2 => 'Sale',
                            5 => 'Low Stock',
                            default => null,
                        };
                    @endphp

                    <div class="w-[78%] shrink-0 snap-start sm:w-[44%] md:w-[31%] lg:w-[23.5%] xl:w-[19%]">

                        <x-storefront.product-card
                            :product="$product"
                            :is-wishlisted="in_array(
                                $product->id,
                                $wishlistedProductIds,
                                true
                            )"
                            :badge="$badge"
                        />

                    </div>

                @endforeach

            </div>


            {{-- ========================================================= --}}
            {{-- MOBILE VIEW ALL --}}
            {{-- ========================================================= --}}
            @if ($viewAllUrl)

                <div class="mt-5 sm:hidden">

                    <a
                        href="{{ $viewAllUrl }}"
                        class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-700"
                    >
                        View all products

                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            class="h-4 w-4"
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


        {{-- ============================================================= --}}
        {{-- COMPONENT JAVASCRIPT --}}
        {{-- ============================================================= --}}
        @once

            <script>
                document.addEventListener('DOMContentLoaded', () => {

                    const sections =
                        document.querySelectorAll('[data-featured-products]');

                    sections.forEach((section) => {

                        const track =
                            section.querySelector('[data-featured-track]');

                        const previousButton =
                            section.querySelector('[data-featured-prev]');

                        const nextButton =
                            section.querySelector('[data-featured-next]');


                        if (!track) {
                            return;
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Scroll Amount
                        |--------------------------------------------------------------------------
                        */

                        const getScrollAmount = () => {

                            const firstItem = track.firstElementChild;

                            if (!firstItem) {
                                return 300;
                            }

                            const itemWidth =
                                firstItem.getBoundingClientRect().width;

                            return itemWidth + 12;

                        };


                        /*
                        |--------------------------------------------------------------------------
                        | Previous
                        |--------------------------------------------------------------------------
                        */

                        previousButton?.addEventListener('click', () => {

                            track.scrollBy({
                                left: -getScrollAmount(),
                                behavior: 'smooth'
                            });

                        });


                        /*
                        |--------------------------------------------------------------------------
                        | Next
                        |--------------------------------------------------------------------------
                        */

                        nextButton?.addEventListener('click', () => {

                            track.scrollBy({
                                left: getScrollAmount(),
                                behavior: 'smooth'
                            });

                        });


                        /*
                        |--------------------------------------------------------------------------
                        | Update Arrow State
                        |--------------------------------------------------------------------------
                        */

                        const updateButtons = () => {

                            const maxScroll =
                                track.scrollWidth - track.clientWidth;

                            if (previousButton) {

                                previousButton.disabled =
                                    track.scrollLeft <= 5;

                            }


                            if (nextButton) {

                                nextButton.disabled =
                                    track.scrollLeft >= maxScroll - 5;

                            }

                        };


                        track.addEventListener(
                            'scroll',
                            updateButtons,
                            {
                                passive: true
                            }
                        );


                        window.addEventListener(
                            'resize',
                            updateButtons
                        );


                        updateButtons();

                    });

                });
            </script>

        @endonce

    </section>

@endif