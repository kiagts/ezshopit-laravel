@props([
    'title' => 'Ready to grow your business?',
    'description' => 'Join a marketplace built for trusted sellers. Reach more customers, manage your store, and grow your brand with '.config('marketplace.name').'.',
    'ctaLabel' => 'Become a Seller',
    'ctaUrl' => null,
    'backgroundImage' => null,
])

@php
    $ctaUrl ??= route('vendor.apply');
    $backgroundImage ??= Storage::url('demo/banner/ready-to-grow.jpg');
@endphp

 <section class="bg-white py-6 sm:py-8">
        <x-container>
            <div
                class="relative min-h-[180px] overflow-hidden rounded-2xl bg-cover bg-center bg-no-repeat sm:min-h-[190px] lg:min-h-[210px]"
                style="background-image: url('{{ $backgroundImage }}');"
            >
                {{-- Dark green overlay --}}
                <div class="absolute inset-0 bg-emerald-950/85"></div>

                {{-- Gradient overlay --}}
                <div
                    class="absolute inset-0 bg-gradient-to-r from-emerald-950 via-emerald-950/80 to-emerald-900/30"
                ></div>

                {{-- Content --}}
                <div
                    class="relative z-10 flex min-h-[180px] items-center px-6 py-6 sm:min-h-[190px] sm:px-8 lg:min-h-[210px] lg:px-10"
                >
                    <div
                        class="flex w-full flex-col gap-5 md:flex-row md:items-center md:justify-between"
                    >
                        {{-- Left: Text --}}
                        <div class="max-w-xl">
                            <h2
                                class="text-2xl font-semibold leading-tight text-white sm:text-3xl"
                            >
                                {{ $title }}
                            </h2>

                            <p
                                class="mt-2 max-w-lg text-sm leading-6 text-white/80 sm:text-base"
                            >
                                {{ $description }}
                            </p>
                        </div>

                        {{-- Right: CTA --}}
                        <div class="shrink-0">
                            <a
                                href="{{ $ctaUrl }}"
                                class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-white px-6 text-sm font-semibold text-emerald-800 shadow-sm transition hover:bg-neutral-100"
                            >
                                {{ $ctaLabel }}

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
                    </div>
                </div>
            </div>
        </x-container>
    </section>
