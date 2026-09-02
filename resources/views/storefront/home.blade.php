@extends('layouts.app')

@section('title', 'Marketplace Home')
@section('description', 'Shop verified sellers, curated categories, and approved marketplace products.')
@php
    $promotionalBanners = [
        [
            'image' => 'promotional-banner-1.png',
            'title' => 'Marketplace Sale Promotion',
        ],
        [ 
            'image' => 'promotional-banner-2.png',
            'title' => 'New Arrivals Promotion',
        ],
        [
            'image' => 'promotional-banner-3.png',
            'title' => 'Marketplace Shopping Promotion',
        ],
    ];

    $mainPromotionBanner = [
        'eyebrow' => 'Shop Smarter',
        'title' => 'All in One Place',
        'description' => 'Everything you need, from trusted marketplace sellers.',
        'button_text' => 'Explore Now',
        'url' => route('storefront.search'),
        'image' => 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?auto=format&fit=crop&w=1400&q=85',
    ];

    $superDealsBanner = [
        'eyebrow' => 'Limited Time',
        'title' => 'Super Deals',
        'description' => 'Up to 50% Off',
        'button_text' => 'Shop Deals',
        'url' => route('storefront.search'),
        'image' => 'https://images.unsplash.com/photo-1607083206968-13611e3d76db?auto=format&fit=crop&w=900&q=85',
    ];

    $newArrivalsBanner = [
        'eyebrow' => 'Just In',
        'title' => 'New Arrivals',
        'description' => 'Check out the latest products.',
        'button_text' => 'Shop Now',
        'url' => route('storefront.search'),
        'image' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=900&q=85',
    ];


    // $featuredBrands = collect([
    //     (object) [
    //         'name' => 'Apple',
    //         'slug' => 'apple',
    //         'logo_path' => 'https://cdn.simpleicons.org/apple',
    //         'description' => 'Innovation, technology and products designed for everyday life.',
    //         'products_count' => 128,
    //     ],

    //     (object) [
    //         'name' => 'Samsung',
    //         'slug' => 'samsung',
    //         'logo_path' => 'https://cdn.simpleicons.org/samsung',
    //         'short_description' => 'Smartphones, electronics and innovative technology for modern living.',
    //         'products_count' => 96,
    //     ],

    //     (object) [
    //         'name' => 'Nike',
    //         'slug' => 'nike',
    //         'logo_path' => 'https://cdn.simpleicons.org/nike',
    //         'short_description' => 'Performance footwear, sportswear and essentials built for movement.',
    //         'products_count' => 84,
    //     ],

    //     (object) [
    //         'name' => 'Adidas',
    //         'slug' => 'adidas',
    //         'logo_path' => 'https://cdn.simpleicons.org/adidas',
    //         'short_description' => 'Sportswear, footwear and lifestyle products made for every day.',
    //         'products_count' => 72,
    //     ],

    //     (object) [
    //         'name' => 'Sony',
    //         'slug' => 'sony',
    //         'logo_path' => 'https://cdn.simpleicons.org/sony/000000',
    //         'short_description' => 'Entertainment, audio, gaming and electronics from a trusted brand.',
    //         'products_count' => 64,
    //     ],
    // ]);

    $smallBannerSliders = [

            // =========================================================
            // Slider 1 - Electronics
            // =========================================================
            [
                [
                    'title' => 'Latest Smartphones',
                    'subtitle' => 'Discover new phones from top brands',
                    'image' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=900&q=85',
                    'url' => route('storefront.search'),
                ],
                [
                    'title' => 'Smart Accessories',
                    'subtitle' => 'Upgrade your everyday technology',
                    'image' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=900&q=85',
                    'url' => route('storefront.search'),
                ],
                [
                    'title' => 'Audio Essentials',
                    'subtitle' => 'Headphones and speakers for every moment',
                    'image' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=900&q=85',
                    'url' => route('storefront.search'),
                ],
            ],



            // =========================================================
            // Slider 3 - Home & Living
            // =========================================================
            [
                [
                    'title' => 'Refresh Your Home',
                    'subtitle' => 'Beautiful essentials for modern spaces',
                    'image' => 'https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?auto=format&fit=crop&w=900&q=85',
                    'url' => route('storefront.search'),
                ],
                [
                    'title' => 'Modern Living',
                    'subtitle' => 'Simple pieces that transform your space',
                    'image' => 'https://images.unsplash.com/photo-1600210492486-724fe5c67fb0?auto=format&fit=crop&w=900&q=85',
                    'url' => route('storefront.search'),
                ],
                [
                    'title' => 'Kitchen Essentials',
                    'subtitle' => 'Everything you need for your kitchen',
                    'image' => 'https://images.unsplash.com/photo-1556911220-bff31c812dba?auto=format&fit=crop&w=900&q=85',
                    'url' => route('storefront.search'),
                ],
            ],


            // =========================================================
            // Slider 4 - Beauty & Lifestyle
            // =========================================================
            [
                [
                    'title' => 'Beauty Essentials',
                    'subtitle' => 'Everyday care for your daily routine',
                    'image' => 'https://images.unsplash.com/photo-1596462502278-27bfdc403348?auto=format&fit=crop&w=900&q=85',
                    'url' => route('storefront.search'),
                ],
                [
                    'title' => 'Skincare Favorites',
                    'subtitle' => 'Care your skin will love',
                    'image' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=900&q=85',
                    'url' => route('storefront.search'),
                ],
                [
                    'title' => 'Fragrance Collection',
                    'subtitle' => 'Find a scent made for you',
                    'image' => 'https://images.unsplash.com/photo-1541643600914-78b084683601?auto=format&fit=crop&w=900&q=85',
                    'url' => route('storefront.search'),
                ],
            ],
        ];

@endphp

@section('content')

    <x-storefront.hero-section :smallBannerSliders="$smallBannerSliders" />

    <x-storefront.category-section
        :categories="$homepageCategories"
        title="Shop by Category"
    />

    <x-storefront.promotion-grid
        :main-banner="$mainPromotionBanner"
        :top-banner="$superDealsBanner"
        :bottom-banner="$newArrivalsBanner"
    />

    <x-storefront.featured-products
        :products="$featuredProducts"
        :wishlisted-product-ids="$wishlistedProductIds ?? []"
        title="Featured Products"
        description="Handpicked products from trusted sellers"
    />
    <x-storefront.featured-brands :brands="$featuredBrands" />
    @if ($featuredVendors->isNotEmpty())
        <x-storefront.featured-stores
            :vendors="$featuredVendors"
        />
    @endif

    <x-storefront.promotional-banner-slider :banners="$promotionalBanners" />

    @if ($newArrivals->isNotEmpty())
        <x-storefront.featured-products
        :products="$newArrivals"
            :wishlisted-product-ids="$wishlistedProductIds ?? []"
            title="New Arrivals"
            description="Recently published approved products, shown without search or category-listing behavior for now."
        />
    @endif

    <section class="bg-white py-8 sm:py-10">
        <x-container>
            <div class="rounded-xl border border-neutral-200 bg-white">
                <div class="grid md:grid-cols-2 lg:grid-cols-4">

                    {{-- Verified Sellers --}}
                    <div class="relative flex items-start gap-4 px-5 py-6">
                        {{-- Divider --}}
                        <span
                            class="absolute bottom-4 right-0 top-4 hidden w-px bg-neutral-200 md:block"
                            aria-hidden="true"
                        ></span>

                        <div class="flex h-12 w-12 shrink-0 items-center justify-center text-emerald-700">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                class="h-10 w-10"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 3l7 3v5c0 4.6-2.9 8.3-7 10-4.1-1.7-7-5.4-7-10V6l7-3z"
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M9 12l2 2 4-4"
                                />
                            </svg>
                        </div>

                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-neutral-950">
                                Verified Sellers
                            </h3>

                            <p class="mt-1 text-xs leading-5 text-neutral-500">
                                Every seller is reviewed and approved for quality and reliability.
                            </p>
                        </div>
                    </div>

                    {{-- Curated Products --}}
                    <div class="relative flex items-start gap-4 px-5 py-6">
                        {{-- Divider --}}
                        <span
                            class="absolute bottom-4 right-0 top-4 hidden w-px bg-neutral-200 lg:block"
                            aria-hidden="true"
                        ></span>

                        <div class="flex h-12 w-12 shrink-0 items-center justify-center text-emerald-700">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                class="h-10 w-10"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 3l7 3v5c0 4.6-2.9 8.3-7 10-4.1-1.7-7-5.4-7-10V6l7-3z"
                                />

                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 8.5l1 2 2.2.3-1.6 1.6.4 2.2-2-1.1-2 1.1.4-2.2-1.6-1.6 2.2-.3 1-2z"
                                />
                            </svg>
                        </div>

                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-neutral-950">
                                Curated Products
                            </h3>

                            <p class="mt-1 text-xs leading-5 text-neutral-500">
                                We handpick quality products from trusted brands and sellers.
                            </p>
                        </div>
                    </div>

                    {{-- Transparent Pricing --}}
                    <div class="relative flex items-start gap-4 px-5 py-6">

                        {{-- Divider --}}
                        <span
                            class="absolute bottom-4 right-0 top-4 hidden w-px bg-neutral-200 md:block"
                            aria-hidden="true"
                        ></span>

                        <div class="flex h-12 w-12 shrink-0 items-center justify-center text-emerald-700">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                class="h-10 w-10"
                            >
                                <circle
                                    cx="12"
                                    cy="12"
                                    r="8"
                                />

                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M14.5 8.5c-.7-.6-1.5-.9-2.5-.9-1.4 0-2.5.8-2.5 1.9 0 1 .7 1.5 2.5 2 1.8.5 2.5 1 2.5 2 0 1.1-1.1 1.9-2.5 1.9-1 0-2-.3-2.8-1"
                                />

                                <path
                                    stroke-linecap="round"
                                    d="M12 6.5v11"
                                />
                            </svg>
                        </div>

                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-neutral-950">
                                Transparent Pricing
                            </h3>

                            <p class="mt-1 text-xs leading-5 text-neutral-500">
                                No hidden fees or surprises. What you see is what you pay.
                            </p>
                        </div>
                    </div>

                    {{-- Real-time Inventory --}}
                    <div class="relative flex items-start gap-4 px-5 py-6">

                        <div class="flex h-12 w-12 shrink-0 items-center justify-center text-emerald-700">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                class="h-10 w-10"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M3 7h11v9H3z"
                                />

                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M14 10h4l3 3v3h-7z"
                                />

                                <circle cx="7" cy="17" r="1.5" />
                                <circle cx="17" cy="17" r="1.5" />

                                <path
                                    stroke-linecap="round"
                                    d="M6 10h3"
                                />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-neutral-950">
                                Real-time Inventory
                            </h3>

                            <p class="mt-1 text-xs leading-5 text-neutral-500">
                                Accurate stock updates so you know what is actually available.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </x-container>
    </section>

    <x-storefront.ready-to-grow
        :products="$newArrivals"
            :wishlisted-product-ids="$wishlistedProductIds ?? []"
            title="New Arrivals"
            description="Recently published approved products, shown without search or category-listing behavior for now."
    />

@endsection
