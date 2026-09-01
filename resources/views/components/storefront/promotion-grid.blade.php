@props([
    'mainBanner' => [],
    'topBanner' => [],
    'bottomBanner' => [],
])

<section class="bg-white py-6 sm:py-8">
    <x-container>

        <div class="grid gap-3 lg:grid-cols-[1.15fr_0.85fr]">

            {{-- ========================================================= --}}
            {{-- LEFT LARGE BANNER --}}
            {{-- ========================================================= --}}
            <a
                href="{{ $mainBanner['url'] ?? '#' }}"
                class="group relative min-h-[300px] overflow-hidden rounded-2xl bg-emerald-950 sm:min-h-[340px] lg:min-h-[380px]"
            >
                {{-- Background Image --}}
                @if (!empty($mainBanner['image']))
                    <img
                        src="{{ $mainBanner['image'] }}"
                        alt="{{ $mainBanner['title'] ?? 'Promotion' }}"
                        class="absolute inset-0 h-full w-full object-cover transition duration-700 group-hover:scale-[1.03]"
                    >
                @endif

                {{-- Overlay --}}
                <div
                    class="absolute inset-0 bg-gradient-to-r from-emerald-950/95 via-emerald-950/75 to-emerald-950/10"
                ></div>

                {{-- Content --}}
                <div
                    class="relative z-10 flex h-full min-h-[300px] max-w-md flex-col justify-center p-7 sm:min-h-[340px] sm:p-9 lg:min-h-[380px] lg:p-10"
                >
                    @if (!empty($mainBanner['eyebrow']))
                        <span
                            class="mb-3 text-xs font-semibold uppercase tracking-[0.15em] text-emerald-200"
                        >
                            {{ $mainBanner['eyebrow'] }}
                        </span>
                    @endif

                    <h2
                        class="text-3xl font-semibold leading-tight text-white sm:text-4xl"
                    >
                        {{ $mainBanner['title'] ?? 'All in One Place' }}
                    </h2>

                    @if (!empty($mainBanner['description']))
                        <p
                            class="mt-3 max-w-sm text-sm leading-6 text-white/80 sm:text-base"
                        >
                            {{ $mainBanner['description'] }}
                        </p>
                    @endif

                    <div class="mt-6">
                        <span
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-white/40 bg-white/10 px-5 text-sm font-semibold text-white backdrop-blur-sm transition group-hover:bg-white group-hover:text-emerald-950"
                        >
                            {{ $mainBanner['button_text'] ?? 'Explore Now' }}

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
                    </div>
                </div>
            </a>


            {{-- ========================================================= --}}
            {{-- RIGHT BANNERS --}}
            {{-- ========================================================= --}}
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">

                {{-- ===================================================== --}}
                {{-- SUPER DEALS --}}
                {{-- ===================================================== --}}
                <a
                    href="{{ $topBanner['url'] ?? '#' }}"
                    class="group relative min-h-[180px] overflow-hidden rounded-2xl bg-[#fff7ed]"
                >
                    {{-- Image --}}
                    @if (!empty($topBanner['image']))
                        <img
                            src="{{ $topBanner['image'] }}"
                            alt="{{ $topBanner['title'] ?? 'Super Deals' }}"
                            class="absolute bottom-0 right-0 h-full w-[52%] object-contain object-right transition duration-500 group-hover:scale-105"
                        >
                    @endif

                    {{-- Content --}}
                    <div
                        class="relative z-10 flex min-h-[180px] max-w-[55%] flex-col justify-center p-6 sm:p-7"
                    >
                        @if (!empty($topBanner['eyebrow']))
                            <span
                                class="text-xs font-semibold uppercase tracking-wide text-orange-600"
                            >
                                {{ $topBanner['eyebrow'] }}
                            </span>
                        @endif

                        <h3
                            class="mt-1 text-2xl font-semibold leading-tight text-orange-600"
                        >
                            {{ $topBanner['title'] ?? 'Super Deals' }}
                        </h3>

                        @if (!empty($topBanner['description']))
                            <p
                                class="mt-1 text-lg font-medium text-neutral-800"
                            >
                                {{ $topBanner['description'] }}
                            </p>
                        @endif

                        <div class="mt-4">
                            <span
                                class="inline-flex items-center gap-1.5 text-sm font-semibold text-orange-600"
                            >
                                {{ $topBanner['button_text'] ?? 'Shop Deals' }}

                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    class="h-4 w-4 transition-transform group-hover:translate-x-1"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M5 12h14M13 6l6 6-6 6"
                                    />
                                </svg>
                            </span>
                        </div>
                    </div>
                </a>


                {{-- ===================================================== --}}
                {{-- NEW ARRIVALS --}}
                {{-- ===================================================== --}}
                <a
                    href="{{ $bottomBanner['url'] ?? '#' }}"
                    class="group relative min-h-[180px] overflow-hidden rounded-2xl bg-emerald-50"
                >
                    {{-- Image --}}
                    @if (!empty($bottomBanner['image']))
                        <img
                            src="{{ $bottomBanner['image'] }}"
                            alt="{{ $bottomBanner['title'] ?? 'New Arrivals' }}"
                            class="absolute bottom-0 right-0 h-full w-[52%] object-contain object-right transition duration-500 group-hover:scale-105"
                        >
                    @endif

                    {{-- Content --}}
                    <div
                        class="relative z-10 flex min-h-[180px] max-w-[55%] flex-col justify-center p-6 sm:p-7"
                    >
                        @if (!empty($bottomBanner['eyebrow']))
                            <span
                                class="text-xs font-semibold uppercase tracking-wide text-emerald-700"
                            >
                                {{ $bottomBanner['eyebrow'] }}
                            </span>
                        @endif

                        <h3
                            class="mt-1 text-2xl font-semibold leading-tight text-neutral-950"
                        >
                            {{ $bottomBanner['title'] ?? 'New Arrivals' }}
                        </h3>

                        @if (!empty($bottomBanner['description']))
                            <p
                                class="mt-2 text-sm leading-5 text-neutral-600"
                            >
                                {{ $bottomBanner['description'] }}
                            </p>
                        @endif

                        <div class="mt-4">
                            <span
                                class="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-700"
                            >
                                {{ $bottomBanner['button_text'] ?? 'Shop Now' }}

                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    class="h-4 w-4 transition-transform group-hover:translate-x-1"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M5 12h14M13 6l6 6-6 6"
                                    />
                                </svg>
                            </span>
                        </div>
                    </div>
                </a>

            </div>

        </div>

    </x-container>
</section>