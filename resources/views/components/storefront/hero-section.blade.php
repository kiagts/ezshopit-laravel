@props([
    'featuredProducts' => [],
    'smallBannerSliders' => [],
    'eyebrow' => 'Trusted Marketplace',
    'title' => 'Shop smarter. Choose trusted.',
    'description' => 'Discover quality products from verified sellers, all in one cleaner marketplace.',
    'primaryCtaLabel' => 'Start Shopping',
    'primaryCtaUrl' => null,
    'secondaryCtaLabel' => 'Become a Seller',
    'secondaryCtaUrl' => null,
    'benefits' => ['Verified Sellers', 'Secure Checkout', 'Curated Products'],
])

@php
    $primaryCtaUrl ??= route('storefront.search');
    $secondaryCtaUrl ??= route('vendor.apply');
@endphp

<section class="bg-white py-6 sm:py-8">
    <x-container>
        <div class="overflow-hidden rounded-2xl bg-emerald-50 px-6 py-8 sm:px-8 lg:px-10" >

            <div class="grid gap-8 lg:grid-cols-[1fr_1fr] lg:items-center" >
                {{-- ===================================================== --}}
                {{-- LEFT --}}
                {{-- ===================================================== --}}
                <div class="max-w-xl py-4">
                    <span
                        class="inline-flex rounded-full bg-emerald-100 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700"
                    >
                        {{ $eyebrow }}
                    </span>

                    <h1
                        class="mt-5 text-4xl font-bold leading-[1.08] tracking-tight text-neutral-950 sm:text-5xl"
                    >
                        {{ $title }}
                    </h1>

                    <p
                        class="mt-5 max-w-lg text-sm leading-6 text-neutral-600 sm:text-base"
                    >
                        {{ $description }}
                    </p>

                    {{-- Buttons --}}
                    <div class="mt-7 flex flex-wrap gap-3">
                        <a
                            href="{{ $primaryCtaUrl }}"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-emerald-700 px-5 text-sm font-semibold text-white transition hover:bg-emerald-800"
                        >
                            {{ $primaryCtaLabel }}

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

                        <a
                            href="{{ $secondaryCtaUrl }}"
                            class="inline-flex h-11 items-center justify-center rounded-lg border border-emerald-700 bg-white px-5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50"
                        >
                            {{ $secondaryCtaLabel }}
                        </a>
                    </div>

                    {{-- Benefits --}}
                    <div class="mt-8 grid gap-4 border-t border-neutral-200 pt-5 sm:grid-cols-3" >
                        @foreach ($benefits as $benefit)
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold text-emerald-700">
                                    ✓
                                </span>

                                <span class="text-xs font-medium text-neutral-700">
                                    {{ $benefit }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- ===================================================== --}}
                {{-- RIGHT --}}
                {{-- ===================================================== --}}
                <div class="h-96">

                    <div class="grid h-full gap-3 sm:grid-cols-[1.08fr_0.92fr]">
                
                        {{-- ========================================================= --}}
                        {{-- LEFT: BIG SLIDER --}}
                        {{-- ========================================================= --}}
                        @if (!empty($smallBannerSliders[0]))
                
                            @php
                                $banners = $smallBannerSliders[0];
                            @endphp
                
                            <div
                                class="relative h-full min-h-[280px] overflow-hidden rounded-xl bg-neutral-100"
                                data-banner-slider
                                data-banner-size="large"
                                data-banner-autoplay="4000"
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
                
                
                                        {{-- Overlay --}}
                                        <div
                                            class="absolute inset-0 bg-gradient-to-t from-neutral-950/80 via-neutral-950/15 to-transparent"
                                        ></div>
                
                
                                        {{-- Content --}}
                                        <div
                                            class="absolute inset-x-0 bottom-0 z-10 p-5 sm:p-6"
                                        >
                                            <h3
                                                class="text-xl font-semibold leading-tight text-white sm:text-2xl"
                                            >
                                                {{ $banner['title'] }}
                                            </h3>
                
                                            <p
                                                class="mt-2 max-w-md text-sm leading-6 text-white/80"
                                            >
                                                {{ $banner['subtitle'] }}
                                            </p>
                                        </div>
                
                                    </a>
                
                                @endforeach
                
                
                                {{-- Dots --}}
                                @if (count($banners) > 1)
                
                                    <div
                                        class="absolute right-4 top-4 z-20 flex items-center gap-1.5"
                                        data-banner-dots
                                    >
                
                                        @foreach ($banners as $index => $banner)
                
                                            <button
                                                type="button"
                                                class="h-1.5 rounded-full transition-all duration-300 {{ $index === 0 ? 'w-6 bg-white' : 'w-2 bg-white/50' }}"
                                                data-banner-dot="{{ $index }}"
                                                aria-label="Go to slide {{ $index + 1 }}"
                                            ></button>
                
                                        @endforeach
                
                                    </div>
                
                                @endif
                
                            </div>
                
                        @endif
                
                
                        {{-- ========================================================= --}}
                        {{-- RIGHT: TWO SMALL SLIDERS --}}
                        {{-- ========================================================= --}}
                        <div class="grid h-full min-h-0 grid-rows-2 gap-3">
                
                            {{-- Top Right Slider --}}
                            @if (!empty($smallBannerSliders[1]))
                
                                @php
                                    $banners = $smallBannerSliders[1];
                                @endphp
                
                                <div
                                    class="relative min-h-0 overflow-hidden rounded-xl bg-neutral-100"
                                    data-banner-slider
                                    data-banner-size="small"
                                    data-banner-autoplay="4700"
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
                                                    class="mt-1 line-clamp-1 text-xs text-white/75"
                                                >
                                                    {{ $banner['subtitle'] }}
                                                </p>
                                            </div>
                
                                        </a>
                
                                    @endforeach
                
                
                                    @if (count($banners) > 1)
                
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
                
                                    @endif
                
                                </div>
                
                            @endif
                
                
                            {{-- Bottom Right Slider --}}
                            @if (!empty($smallBannerSliders[2]))
                
                                @php
                                    $banners = $smallBannerSliders[2];
                                @endphp
                
                                <div
                                    class="relative min-h-0 overflow-hidden rounded-xl bg-neutral-100"
                                    data-banner-slider
                                    data-banner-size="small"
                                    data-banner-autoplay="5400"
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
                                                    class="mt-1 line-clamp-1 text-xs text-white/75"
                                                >
                                                    {{ $banner['subtitle'] }}
                                                </p>
                                            </div>
                
                                        </a>
                
                                    @endforeach
                
                
                                    @if (count($banners) > 1)
                
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
                
                                    @endif
                
                                </div>
                
                            @endif
                
                        </div>
                
                    </div>
                
                </div>

            </div>
        </div>
    </x-container>
</section>
