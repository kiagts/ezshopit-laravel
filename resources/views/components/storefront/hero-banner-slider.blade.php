@props([
    'heroBanners' => [],
    'smallBannerSliders' => [],
])

{{-- ========================================================= --}}
{{-- HERO BANNER AREA --}}
{{-- ========================================================= --}}
<section class="border-b border-neutral-200 bg-white">

    <x-container class="py-5 sm:py-6">

        <div class="grid gap-3 lg:grid-cols-[1.45fr_0.85fr]">

            {{-- ========================================================= --}}
            {{-- LEFT LARGE SLIDER --}}
            {{-- ========================================================= --}}
            <div
                class="relative h-[420px] overflow-hidden rounded-xl bg-neutral-100 lg:h-[520px]"
                data-banner-slider
                data-banner-autoplay="5000"
            >

                @foreach ($heroBanners as $index => $banner)

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
                            class="absolute inset-0 bg-gradient-to-r from-neutral-950/80 via-neutral-950/45 to-neutral-950/5"
                        ></div>


                        {{-- Content --}}
                        <div
                            class="relative z-10 flex h-full max-w-2xl flex-col justify-end p-7 pb-16 sm:p-10 sm:pb-16 lg:p-12 lg:pb-16"
                        >

                            <p
                                class="text-xs font-semibold uppercase tracking-[0.18em] text-white/80"
                            >
                                {{ $banner['eyebrow'] }}
                            </p>


                            <h1
                                class="mt-3 max-w-xl text-3xl font-semibold leading-tight tracking-normal text-white sm:text-4xl lg:text-5xl"
                            >
                                {{ $banner['title'] }}
                            </h1>


                            <p
                                class="mt-4 max-w-lg text-sm leading-6 text-white/80 sm:text-base"
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


                {{-- ===================================================== --}}
                {{-- DOTS --}}
                {{-- ===================================================== --}}
                <div
                    class="absolute bottom-6 left-7 z-20 flex items-center gap-2 sm:left-10 lg:left-12"
                    data-banner-dots
                >

                    @foreach ($heroBanners as $index => $banner)

                        <button
                            type="button"
                            class="h-1.5 rounded-full transition-all duration-300 {{ $index === 0 ? 'w-8 bg-white' : 'w-3 bg-white/50' }}"
                            data-banner-dot="{{ $index }}"
                            aria-label="Go to slide {{ $index + 1 }}"
                        ></button>

                    @endforeach

                </div>


                {{-- ===================================================== --}}
                {{-- ARROWS --}}
                {{-- ===================================================== --}}
                <div
                    class="absolute bottom-5 right-5 z-20 flex items-center gap-2"
                >

                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/90 text-neutral-950 shadow-sm backdrop-blur transition hover:bg-white"
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


                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/90 text-neutral-950 shadow-sm backdrop-blur transition hover:bg-white"
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

                </div>

            </div>


            {{-- ========================================================= --}}
            {{-- RIGHT FOUR SMALL BANNER SLIDERS --}}
            {{-- ========================================================= --}}
            <div class="grid grid-cols-2 gap-3 lg:h-[520px] lg:grid-rows-2">

                @foreach ($smallBannerSliders as $sliderIndex => $banners)

                    <div
                        class="relative min-h-[200px] overflow-hidden rounded-xl bg-neutral-100 lg:h-full lg:min-h-0"
                        data-banner-slider
                        data-banner-size="small"
                        data-banner-autoplay="{{ 4000 + ($sliderIndex * 700) }}"
                    >

                        @foreach ($banners as $index => $banner)

                            <a
                                href="{{ $banner['url'] }}"
                                class="absolute inset-0 block transition-opacity duration-700 {{ $index === 0 ? 'opacity-100' : 'pointer-events-none opacity-0' }}"
                                data-banner-slide
                            >

                                <img
                                    src="{{ $banner['image'] }}"
                                    alt="{{ $banner['title'] }}"
                                    class="absolute inset-0 h-full w-full object-cover transition duration-500 hover:scale-105"
                                >


                                <div
                                    class="absolute inset-0 bg-gradient-to-t from-neutral-950/75 via-neutral-950/10 to-transparent"
                                ></div>


                                <div
                                    class="absolute inset-x-0 bottom-0 z-10 p-4"
                                >

                                    <h3
                                        class="text-sm font-semibold leading-5 text-white sm:text-base"
                                    >
                                        {{ $banner['title'] }}
                                    </h3>


                                    <p
                                        class="mt-1 text-xs text-white/75"
                                    >
                                        {{ $banner['subtitle'] }}
                                    </p>

                                </div>

                            </a>

                        @endforeach


                        {{-- Small Slider Dots --}}
                        <div
                            class="absolute right-3 top-3 z-20 flex items-center gap-1.5"
                            data-banner-dots
                        >

                            @foreach ($banners as $index => $banner)

                                <button
                                    type="button"
                                    class="h-1.5 rounded-full transition-all duration-300 {{ $index === 0 ? 'w-5 bg-white' : 'w-2 bg-white/50' }}"
                                    data-banner-dot="{{ $index }}"
                                    aria-label="Go to slide {{ $index + 1 }}"
                                ></button>

                            @endforeach

                        </div>

                    </div>

                @endforeach

            </div>

        </div>

    </x-container>

</section>
