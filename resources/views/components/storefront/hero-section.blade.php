@props([
    'featuredProducts' => [],
    'smallBannerSliders' => [],
])

<section class="bg-white py-6 sm:py-8">
    <x-container>
        <div class="overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-50 via-white to-neutral-100 px-6 py-8 sm:px-8 lg:px-10">

            <div class="grid gap-8 lg:grid-cols-[1fr_1fr] lg:items-center" >
                {{-- ===================================================== --}}
                {{-- LEFT --}}
                {{-- ===================================================== --}}
                <div class="max-w-xl py-4">
                    <span
                        class="inline-flex rounded-full bg-emerald-100 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700"
                    >
                        Trusted Marketplace
                    </span>

                    <h1
                        class="mt-5 text-4xl font-bold leading-[1.08] tracking-tight text-neutral-950 sm:text-5xl"
                    >
                        Shop smarter.

                        <span class="block">
                            Choose trusted.
                        </span>
                    </h1>

                    <p
                        class="mt-5 max-w-lg text-sm leading-6 text-neutral-600 sm:text-base"
                    >
                        Discover quality products from verified sellers,
                        all in one cleaner marketplace.
                    </p>

                    {{-- Buttons --}}
                    <div class="mt-7 flex flex-wrap gap-3">
                        <a
                            href="{{ route('storefront.search') }}"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-emerald-700 px-5 text-sm font-semibold text-white transition hover:bg-emerald-800"
                        >
                            Start Shopping

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
                            href="{{ route('vendor.apply') }}"
                            class="inline-flex h-11 items-center justify-center rounded-lg border border-emerald-700 bg-white px-5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50"
                        >
                            Become a Seller
                        </a>
                    </div>

                    {{-- Benefits --}}
                    <div class="mt-8 grid gap-4 border-t border-neutral-200 pt-5 sm:grid-cols-3" >
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold text-emerald-700">
                                ✓
                            </span>

                            <span class="text-xs font-medium text-neutral-700">
                                Verified Sellers
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold text-emerald-700">
                                ✓
                            </span>

                            <span class="text-xs font-medium text-neutral-700">
                                Secure Checkout
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold text-emerald-700">
                                ✓
                            </span>

                            <span class="text-xs font-medium text-neutral-700">
                                Curated Products
                            </span>
                        </div>
                    </div>
                </div>

                {{-- ===================================================== --}}
                {{-- RIGHT --}}
                {{-- ===================================================== --}}
                <div class="h-96">
                    <div class="grid h-full gap-3 sm:grid-cols-[1.08fr_0.92fr]" >
                        {{-- Main Product --}}
                        <div class="h-full">
                            @if ($featuredProducts->isNotEmpty())
                                @php
                                    $mainProduct = $featuredProducts->first();
                                @endphp
                                <x-storefront.hero-product-card
                                    :product="$mainProduct"
                                    :is-wishlisted="in_array(
                                        $mainProduct->id,
                                        $wishlistedProductIds ?? [],
                                        true
                                    )"
                                    variant="large"
                                    badge="Best Seller"
                                    class="h-full"
                                />
                            @else
                                <div
                                    class="flex h-full items-center justify-center rounded-xl border border-dashed border-neutral-300 bg-white p-6 text-center"
                                >
                                    <p class="text-sm text-neutral-500">
                                        Featured product will appear here.
                                    </p>
                                </div>
                            @endif
                        </div>

                        {{-- Right Two Products --}}
                        <div class="grid h-full min-h-0 grid-rows-2 gap-3">
                            @forelse ($featuredProducts->skip(1)->take(2) as $product)
                                <div class="min-h-0 overflow-hidden">
                                    <x-storefront.hero-product-card
                                        :product="$product"
                                        :is-wishlisted="in_array(
                                            $product->id,
                                            $wishlistedProductIds ?? [],
                                            true
                                        )"
                                        variant="small"
                                        class="h-full"
                                    />
                                </div>
                            @empty
                                <div class="rounded-xl border border-dashed border-neutral-300 bg-white"></div>
                                <div class="rounded-xl border border-dashed border-neutral-300 bg-white"></div>

                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-container>
</section>
