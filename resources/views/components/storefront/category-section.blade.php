@props([
    'categories' => collect(),
    'title' => 'Shop by Category',
    'viewAllUrl' => null,
])

@php
    $tones = [
        'violet',
        'green',
        'emerald',
        'pink',
        'sky',
        'violet',
        'blue',
        'rose',
    ];
@endphp


<section
    {{ $attributes->class([
        'bg-white py-8 sm:py-10'
    ]) }}
    data-category-section
>

    <x-container>

        {{-- ========================================================= --}}
        {{-- HEADER --}}
        {{-- ========================================================= --}}
        <div class="mb-5 flex items-center justify-between gap-4">

            <h2
                class="text-xl font-bold tracking-tight text-neutral-950 sm:text-2xl"
            >
                {{ $title }}
            </h2>


            @if ($viewAllUrl)

                <a
                    href="{{ $viewAllUrl }}"
                    class="inline-flex items-center gap-2 text-xs font-semibold text-emerald-700 transition hover:text-emerald-800"
                >
                    <span class="hidden sm:inline">
                        View all categories
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
        {{-- CATEGORY TRACK --}}
        {{-- ========================================================= --}}
        @if ($categories->isNotEmpty())
            <div
                class="flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                data-category-track
            >

                @foreach ($categories as $index => $category)

                    @php
                        $tone = $tones[$index % count($tones)];
                    @endphp

                    <div
                        class="w-[76%] shrink-0 snap-start sm:w-[43%] md:w-[31%] lg:w-[23.5%] xl:w-[15.8%]"
                    >

                        <x-storefront.category-card
                            :category="$category"
                            :href="route('storefront.categories.show', $category)"
                            :tone="$tone"
                        />

                    </div>

                @endforeach

            </div>
        @else
            <x-empty-state
                title="Categories are being prepared."
                description="Fresh departments will appear here as the marketplace catalog grows."
            />
        @endif

    </x-container>

</section>
