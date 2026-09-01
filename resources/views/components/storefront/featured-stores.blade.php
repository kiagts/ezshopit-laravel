@props([
    'vendors' => collect(),
    'title' => 'Featured Stores',
    'description' => 'Shop from our trusted and verified sellers',
    'viewAllUrl' => null,
])


@if ($vendors->isNotEmpty())

    <section
        {{ $attributes->class([
            'bg-white py-8 sm:py-10'
        ]) }}
    >

        <x-container>

            {{-- ========================================================= --}}
            {{-- HEADER --}}
            {{-- ========================================================= --}}
            <div class="mb-5 flex items-end justify-between gap-4">

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


                @if ($viewAllUrl)

                    <a
                        href="{{ $viewAllUrl }}"
                        class="inline-flex shrink-0 items-center gap-2 text-xs font-semibold text-emerald-700 transition hover:text-emerald-800"
                    >
                        <span class="hidden sm:inline">
                            View all stores
                        </span>

                        <span class="sm:hidden">
                            View all
                        </span>

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

            </div>


            {{-- ========================================================= --}}
            {{-- STORE CARDS --}}
            {{-- ========================================================= --}}
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-6">

                @foreach ($vendors as $vendor)

                    <x-storefront.store-card
                        :vendor="$vendor"
                    />

                @endforeach

            </div>

        </x-container>

    </section>

@endif