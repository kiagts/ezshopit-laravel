@props([
    'banners' => [],
])

<section class="bg-white">
    <x-container class="py-4 sm:py-5">

        <div
            class="relative h-[160px] overflow-hidden rounded-xl bg-neutral-100 sm:h-[190px] lg:h-[220px]"
            data-banner-slider
            data-banner-autoplay="6000"
        >

            @foreach ($banners as $index => $banner)

                <div
                    class="absolute inset-0 transition-opacity duration-700 {{ $index === 0 ? 'opacity-100' : 'pointer-events-none opacity-0' }}"
                    data-banner-slide
                >
                    @if (!empty($banner['url']))
                        <a href="{{ $banner['url'] }}" class="block h-full" aria-label="{{ $banner['title'] }}">
                    @endif
                    <picture>
                        @if (!empty($banner['mobile_image']))
                            <source media="(max-width: 639px)" srcset="{{ $banner['mobile_image'] }}">
                        @endif
                    <img
                        src="{{ $banner['image'] }}"
                        alt="{{ $banner['image_alt'] ?? $banner['title'] ?? 'Banner' }}"
                        class="h-full w-full object-cover"
                    >
                    </picture>
                    @if (!empty($banner['url']))
                        </a>
                    @endif
                </div>

            @endforeach

            {{-- Dots --}}
            @if (count($banners) > 1)
                <div
                    class="absolute bottom-3 left-1/2 z-20 flex -translate-x-1/2 items-center gap-2"
                    data-banner-dots
                >
                    @foreach ($banners as $index => $banner)

                        <button
                            type="button"
                            class="h-1.5 rounded-full transition-all duration-300 {{ $index === 0 ? 'w-7 bg-white' : 'w-2.5 bg-white/60' }}"
                            data-banner-dot="{{ $index }}"
                            aria-label="Go to slide {{ $index + 1 }}"
                        ></button>

                    @endforeach
                </div>
            @endif

        </div>

    </x-container>
</section>
