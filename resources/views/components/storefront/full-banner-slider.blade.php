@props([
    'banners' => [],
])

{{-- ========================================================= --}}
{{-- FULL WIDTH BANNER SLIDER --}}
{{-- ========================================================= --}}
<section class="bg-white">

    <x-container class="py-5 sm:py-6">

        <div
            class="relative h-[280px] overflow-hidden rounded-xl bg-neutral-100 sm:h-[340px] lg:h-[400px]"
            data-banner-slider
            data-banner-autoplay="6000"
        >

            @foreach ($banners as $index => $banner)

                <div
                    class="absolute inset-0 transition-opacity duration-700 {{ $index === 0 ? 'opacity-100' : 'pointer-events-none opacity-0' }}"
                    data-banner-slide
                >

                    <img
                        src="{{ $banner['image'] }}"
                        alt="{{ $banner['title'] }}"
                        class="absolute inset-0 h-full w-full object-cover"
                    >


                    {{-- Overlay --}}
                    <div
                        class="absolute inset-0 bg-gradient-to-r from-neutral-950/80 via-neutral-950/40 to-transparent"
                    ></div>


                    {{-- Content --}}
                    <div
                        class="relative z-10 flex h-full max-w-2xl flex-col justify-center px-8 py-12 sm:px-12 lg:px-16"
                    >

                        <p
                            class="text-xs font-semibold uppercase tracking-[0.18em] text-white/75"
                        >
                            {{ $banner['eyebrow'] }}
                        </p>


                        <h2
                            class="mt-3 text-3xl font-semibold leading-tight text-white sm:text-4xl lg:text-5xl"
                        >
                            {{ $banner['title'] }}
                        </h2>


                        <p
                            class="mt-4 max-w-xl text-sm leading-6 text-white/80 sm:text-base"
                        >
                            {{ $banner['description'] }}
                        </p>


                        <div class="mt-6">

                            <a
                                href="{{ $banner['button_url'] }}"
                                class="inline-flex h-11 items-center justify-center rounded-lg bg-white px-6 text-sm font-semibold text-neutral-950 transition hover:bg-neutral-100"
                            >
                                {{ $banner['button_text'] }}

                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    class="ml-2 h-4 w-4"
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

                </div>

            @endforeach


            {{-- Previous --}}
            <button
                type="button"
                class="absolute left-4 top-1/2 z-20 hidden h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-neutral-950 shadow-sm backdrop-blur transition hover:bg-white sm:inline-flex"
                data-banner-prev
                aria-label="Previous banner"
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


            {{-- Next --}}
            <button
                type="button"
                class="absolute right-4 top-1/2 z-20 hidden h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-neutral-950 shadow-sm backdrop-blur transition hover:bg-white sm:inline-flex"
                data-banner-next
                aria-label="Next banner"
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


            {{-- Dots --}}
            <div
                class="absolute bottom-5 left-1/2 z-20 flex -translate-x-1/2 items-center gap-2"
                data-banner-dots
            >

                @foreach ($banners as $index => $banner)

                    <button
                        type="button"
                        class="h-1.5 rounded-full transition-all duration-300 {{ $index === 0 ? 'w-8 bg-white' : 'w-3 bg-white/50' }}"
                        data-banner-dot="{{ $index }}"
                        aria-label="Go to slide {{ $index + 1 }}"
                    ></button>

                @endforeach

            </div>

        </div>

    </x-container>

</section>
