@props([
    'brands' => [],
    'viewAllUrl' => null,
    'title' => 'Top Brands You Love',
    'description' => 'Discover original products from the world’s leading brands, all in one place.',
])

<section class="bg-white py-8 sm:py-10">

    <x-container>

        {{-- ========================================================= --}}
        {{-- HEADER --}}
        {{-- ========================================================= --}}
        <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">

            <div>
                <div
                    class="inline-flex items-center gap-2 rounded-lg bg-violet-50 px-3 py-2 text-xs font-semibold uppercase tracking-[0.12em] text-violet-700"
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
                            d="m4 7 3.5 4L12 5l4.5 6L20 7l-1.5 10h-13L4 7Z"
                        />
                    </svg>

                    Trusted by millions
                </div>

                <h2
                    class="mt-4 text-3xl font-semibold tracking-tight text-neutral-950 sm:text-4xl"
                >
                    {{ $title }}
                </h2>

                <p
                    class="mt-3 max-w-2xl text-sm leading-6 text-neutral-500 sm:text-base"
                >
                    {{ $description }}
                </p>
            </div>


            @if ($viewAllUrl)
                <a
                    href="{{ $viewAllUrl }}"
                    class="inline-flex shrink-0 items-center gap-2 text-sm font-semibold text-violet-700 transition hover:text-violet-900"
                >
                    Explore all brands

                    <span
                        class="flex h-7 w-7 items-center justify-center rounded-full border border-violet-300"
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
                                d="M5 12h14M13 6l6 6-6 6"
                            />
                        </svg>
                    </span>
                </a>
            @endif

        </div>


        {{-- ========================================================= --}}
        {{-- BRAND GRID --}}
        {{-- ========================================================= --}}
        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">

            @forelse ($brands as $brand)

                @php
                    $brandName = $brand->name ?? 'Brand';

                    $brandInitial = str($brandName)
                        ->substr(0, 1)
                        ->upper();

                    $brandLogo = $brand->logo_path ?? null;

                    $productCount = $brand->products_count ?? null;

                    $brandDescription =
                        $brand->short_description
                        ?? $brand->description
                        ?? 'Discover products from this trusted brand.';

                    $brandUrl =
                        isset($brand->slug)
                            ? url('/brand/' . $brand->slug)
                            : '#';

                    $tone = match ($loop->index % 5) {
                        0 => [
                            'card' => 'bg-gradient-to-b from-violet-50 to-white',
                            'pill' => 'bg-violet-100 text-violet-700',
                            'link' => 'text-violet-700',
                            'accent' => 'bg-violet-600',
                            'blob' => 'bg-violet-100',
                        ],
                        1 => [
                            'card' => 'bg-gradient-to-b from-emerald-50 to-white',
                            'pill' => 'bg-emerald-100 text-emerald-700',
                            'link' => 'text-emerald-700',
                            'accent' => 'bg-emerald-600',
                            'blob' => 'bg-emerald-100',
                        ],
                        2 => [
                            'card' => 'bg-gradient-to-b from-amber-50 to-white',
                            'pill' => 'bg-amber-100 text-amber-700',
                            'link' => 'text-amber-700',
                            'accent' => 'bg-amber-500',
                            'blob' => 'bg-amber-100',
                        ],
                        3 => [
                            'card' => 'bg-gradient-to-b from-rose-50 to-white',
                            'pill' => 'bg-rose-100 text-rose-700',
                            'link' => 'text-rose-700',
                            'accent' => 'bg-rose-500',
                            'blob' => 'bg-rose-100',
                        ],
                        default => [
                            'card' => 'bg-gradient-to-b from-sky-50 to-white',
                            'pill' => 'bg-sky-100 text-sky-700',
                            'link' => 'text-sky-700',
                            'accent' => 'bg-sky-500',
                            'blob' => 'bg-sky-100',
                        ],
                    };
                @endphp


                <article
                    class="group relative overflow-hidden rounded-2xl border border-neutral-200 {{ $tone['card'] }} p-5 pb-6 transition duration-300 hover:-translate-y-1 hover:shadow-lg"
                >

                    {{-- Decorative Background Shape --}}
                    <div
                        class="pointer-events-none absolute -right-10 top-12 h-28 w-28 rounded-full {{ $tone['blob'] }} opacity-60 blur-[1px]"
                        aria-hidden="true"
                    ></div>


                    {{-- Logo --}}
                    <div
                        class="relative z-10 flex h-28 w-28 items-center justify-center rounded-full border border-white/80 bg-white shadow-sm"
                    >

                        @if ($brandLogo)

                            <img
                                src="{{ $brandLogo }}"
                                alt="{{ $brandName }}"
                                class="h-full w-full object-contain p-6"
                                loading="lazy"
                            >

                        @else

                            <span
                                class="text-3xl font-semibold text-neutral-900"
                            >
                                {{ $brandInitial }}
                            </span>

                        @endif

                    </div>


                    {{-- Brand Name --}}
                    <h3
                        class="relative z-10 mt-6 text-xl font-semibold text-neutral-950"
                    >
                        {{ $brandName }}
                    </h3>


                    {{-- Description --}}
                    <p
                        class="relative z-10 mt-2 min-h-[48px] line-clamp-2 text-sm leading-6 text-neutral-500"
                    >
                        {{ $brandDescription }}
                    </p>


                    {{-- Product Count --}}
                    @if ($productCount !== null)

                        <div
                            class="relative z-10 mt-5 inline-flex items-center gap-2 rounded-full px-3 py-2 text-sm font-medium {{ $tone['pill'] }}"
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
                                    stroke-linejoin="round"
                                    d="m4 7 8-4 8 4-8 4-8-4Zm0 0v10l8 4 8-4V7M12 11v10"
                                />
                            </svg>

                            <span>
                                {{ number_format($productCount) }}+
                                {{ str('Product')->plural($productCount) }}
                            </span>
                        </div>

                    @endif


                    {{-- CTA --}}
                    <div class="relative z-10 mt-6">

                        <a
                            href="{{ $brandUrl }}"
                            class="inline-flex items-center gap-2 text-sm font-semibold {{ $tone['link'] }} transition"
                        >
                            Shop Now

                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                class="h-4 w-4 transition-transform group-hover:translate-x-1"
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


                    {{-- Bottom Accent --}}
                    <span
                        class="absolute bottom-0 left-0 h-1 w-full {{ $tone['accent'] }}"
                        aria-hidden="true"
                    ></span>

                </article>

            @empty

                <div
                    class="col-span-full rounded-2xl border border-dashed border-neutral-300 bg-neutral-50 p-8 text-center"
                >
                    <p class="text-sm text-neutral-500">
                        Featured brands will appear here.
                    </p>
                </div>

            @endforelse

        </div>

    </x-container>

</section>