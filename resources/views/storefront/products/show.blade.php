@extends('layouts.app')

@php
    $initialPrice = $initialVariant->storefrontPrice();
    $initialStockState = $initialVariant->publicStockState();

    $galleryPayload = $gallery
        ->map(fn ($image) => [
            'id' => $image->id,
            'url' => $image->url(),
            'alt' => $image->alt_text ?: $product->name,
            'is_variant' => $image->product_variant_id !== null,
        ])
        ->values();
    $vendorName = $product->vendor->display_name;

    $vendorInitial = str($vendorName)
        ->substr(0, 1)
        ->upper();

    $maxQuantity = max(
        1,
        (int) config('marketplace.cart.max_quantity_per_item', 20)
    );
@endphp


@section('title', $product->name)

@section(
    'description',
    str(
        $product->short_description
        ?: $product->description
        ?: $product->name.' sold by '.$product->vendor->display_name
    )->limit(155)
)


@push('head')

    <link
        rel="canonical"
        href="{{ route('storefront.products.show', $product) }}"
    >

    <meta
        property="og:title"
        content="{{ $product->name }}"
    >

    <meta
        property="og:description"
        content="{{ str($product->short_description ?: $product->description ?: $product->name)->limit(155) }}"
    >

    @if ($product->displayImage())
        <meta
            property="og:image"
            content="{{ $product->displayImage()->url() }}"
        >
    @endif

    @if ($product->approvedReviewCount() > 0)
        <script type="application/ld+json">
            {
                "@@context": "https://schema.org",
                "@type": "Product",
                "name": @json($product->name),
                "aggregateRating": {
                    "@type": "AggregateRating",
                    "ratingValue": "{{ number_format($product->averageRating(), 1, '.', '') }}",
                    "reviewCount": "{{ $product->approvedReviewCount() }}",
                    "bestRating": "5",
                    "worstRating": "1"
                }
            }
        </script>
    @endif

@endpush


@section('content')

    {{-- ========================================================= --}}
    {{-- PRODUCT HERO --}}
    {{-- ========================================================= --}}

    <section class="bg-white">

        <x-container class="py-5 sm:py-6">

            {{-- ================================================= --}}
            {{-- BREADCRUMB --}}
            {{-- ================================================= --}}

            <x-storefront.breadcrumb
                :items="$breadcrumbs->push($product)"
            />


            {{-- ================================================= --}}
            {{-- PRODUCT DETAIL --}}
            {{-- ================================================= --}}

            <div
                class="mt-5 grid gap-8 lg:grid-cols-[minmax(0,1.05fr)_minmax(360px,0.95fr)] xl:gap-10"
                data-product-detail
                data-initial-variant="{{ $initialVariant->id }}"
            >

                {{-- ================================================= --}}
                {{-- LEFT: PRODUCT GALLERY --}}
                {{-- ================================================= --}}

                <div>

                    <div
                        class="grid gap-3 sm:grid-cols-[72px_minmax(0,1fr)]"
                    >

                        {{-- ============================================= --}}
                        {{-- THUMBNAILS --}}
                        {{-- ============================================= --}}

                        <div
                            class="order-2 flex gap-2 overflow-x-auto pb-1 sm:order-1 sm:max-h-[520px] sm:flex-col sm:overflow-x-hidden sm:overflow-y-auto sm:pr-1"
                            data-thumbnail-strip
                        >

                            @foreach ($gallery as $image)

                                <button
                                    type="button"
                                    class="h-[68px] w-[68px] shrink-0 overflow-hidden rounded-lg border border-neutral-200 bg-white p-1.5 transition hover:border-brand-600 data-[selected=true]:border-brand-700 data-[selected=true]:ring-1 data-[selected=true]:ring-brand-700"
                                    data-gallery-thumbnail
                                    data-image-url="{{ $image->url() }}"
                                    data-image-alt="{{ $image->alt_text ?: $product->name }}"
                                    data-selected="{{ $loop->first ? 'true' : 'false' }}"
                                    aria-label="Show {{ $image->alt_text ?: $product->name }} image"
                                >

                                    <img
                                        src="{{ $image->url() }}"
                                        alt="{{ $image->alt_text ?: $product->name }}"
                                        class="h-full w-full object-contain"
                                    >

                                </button>

                            @endforeach

                        </div>


                        {{-- ============================================= --}}
                        {{-- MAIN IMAGE --}}
                        {{-- ============================================= --}}

                        <div
                            class="order-1 relative overflow-hidden rounded-xl bg-neutral-50 sm:order-2"
                        >

                            <div
                                class="flex min-h-[420px] items-center justify-center p-5 sm:min-h-[500px]"
                            >

                                @if ($gallery->isNotEmpty())

                                    <img
                                        src="{{ $gallery->first()->url() }}"
                                        alt="{{ $gallery->first()->alt_text ?: $product->name }}"
                                        class="max-h-[460px] w-full object-contain"
                                        data-main-image
                                    >

                                @else

                                    <x-storefront.image-placeholder
                                        :label="$product->name"
                                        class="aspect-square w-full"
                                    />

                                @endif

                            </div>


                            {{-- ========================================= --}}
                            {{-- WISHLIST --}}
                            {{-- ========================================= --}}

                            @if (auth()->guest())

                                <a
                                    href="{{ route('login', ['redirect' => url()->current()]) }}"
                                    class="absolute right-4 top-4 inline-flex h-10 w-10 items-center justify-center rounded-full border border-neutral-200 bg-white text-neutral-700 shadow-sm transition hover:border-brand-700 hover:text-brand-700"
                                    aria-label="Add to wishlist"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.7"
                                        class="h-5 w-5"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78Z"
                                        />
                                    </svg>
                                </a>

                            @elseif (auth()->user()?->isCustomer())

                                <form
                                    method="POST"
                                    action="{{ $isWishlisted
                                        ? route('wishlist.destroy', $product)
                                        : route('wishlist.store', $product) }}"
                                    class="absolute right-4 top-4"
                                >

                                    @csrf

                                    @if ($isWishlisted)
                                        @method('DELETE')
                                    @endif

                                    <button
                                        type="submit"
                                        class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-neutral-200 bg-white text-neutral-700 shadow-sm transition hover:border-brand-700 hover:text-brand-700"
                                        aria-label="{{ $isWishlisted ? 'Remove from wishlist' : 'Add to wishlist' }}"
                                    >

                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 24 24"
                                            fill="{{ $isWishlisted ? 'currentColor' : 'none' }}"
                                            stroke="currentColor"
                                            stroke-width="1.7"
                                            class="h-5 w-5"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78Z"
                                            />
                                        </svg>

                                        <span class="sr-only">
                                            {{ $isWishlisted ? 'Saved' : 'Save' }}
                                        </span>

                                    </button>

                                </form>

                            @endif

                        </div>

                    </div>


                    {{-- ================================================= --}}
                    {{-- PRODUCT BENEFITS --}}
                    {{-- ================================================= --}}

                    <div
                        class="mt-5 grid overflow-hidden rounded-xl border border-neutral-200 bg-white sm:grid-cols-2"
                    >

                        <div
                            class="flex items-center gap-3 border-b border-neutral-200 p-4 sm:border-b-0 sm:border-r"
                        >

                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                    class="h-5 w-5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M3 7h11v10H3zM14 10h3l4 4v3h-7z"
                                    />
                                    <circle cx="7" cy="18" r="1.5" />
                                    <circle cx="18" cy="18" r="1.5" />
                                </svg>
                            </div>

                            <div>

                                <p
                                    class="text-sm font-semibold text-neutral-950"
                                >
                                    Estimated Delivery
                                </p>

                                <p
                                    class="mt-1 text-xs text-neutral-500"
                                >
                                    Standard marketplace Shipping
                                </p>

                            </div>

                        </div>


                        <div
                            class="flex items-center gap-3 p-4"
                        >

                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                    class="h-5 w-5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M4 12a8 8 0 1 0 2.34-5.66L4 8.68"
                                    />

                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M4 4v4.68h4.68"
                                    />
                                </svg>
                            </div>

                            <div>

                                <p
                                    class="text-sm font-semibold text-neutral-950"
                                >
                                    Easy Returns
                                </p>

                                <p
                                    class="mt-1 text-xs text-neutral-500"
                                >
                                    Simple marketplace return process
                                </p>

                            </div>

                        </div>

                    </div>

                </div>


                {{-- ================================================= --}}
                {{-- RIGHT: PRODUCT PURCHASE AREA --}}
                {{-- ================================================= --}}

                <aside class="lg:pt-1">

                    <div>

                        {{-- ============================================= --}}
                        {{-- BRAND + SELLER --}}
                        {{-- ============================================= --}}

                        <div
                            class="flex flex-wrap items-center gap-2 text-sm"
                        >

                            @if ($product->brand)

                                <span class="text-neutral-500">
                                    {{ $product->brand->name }}
                                </span>

                                <span class="text-neutral-300">
                                    •
                                </span>

                            @endif

                            <a
                                href="{{ route('storefront.stores.show', $product->vendor) }}"
                                class="font-medium text-neutral-500 transition hover:text-brand-700"
                            >
                                <span class="sr-only">Sold by {{ $vendorName }}</span>
                                <span aria-hidden="true">{{ $vendorName }}</span>
                            </a>

                            <span
                                class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="currentColor"
                                    class="h-4 w-4"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M12 2.25a9.75 9.75 0 1 0 0 19.5 9.75 9.75 0 0 0 0-19.5Zm4.28 7.28a.75.75 0 0 0-1.06-1.06l-4.72 4.72-1.72-1.72a.75.75 0 1 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.06 0l5.25-5.25Z"
                                        clip-rule="evenodd"
                                    />
                                </svg>

                                Verified Seller
                            </span>

                        </div>


                        {{-- ============================================= --}}
                        {{-- TITLE --}}
                        {{-- ============================================= --}}

                        <h1
                            class="mt-3 text-3xl font-semibold leading-tight tracking-normal text-neutral-950 sm:text-4xl"
                        >
                            {{ $product->name }}
                        </h1>

                        @if ($product->approvedReviewCount() > 0)
                            <a href="#reviews" class="mt-3 inline-flex items-center gap-2 text-sm text-neutral-600 transition hover:text-brand-700">
                                <x-star-rating :rating="$product->averageRating()" :count="$product->approvedReviewCount()" />
                                <span>Read reviews</span>
                            </a>
                        @endif


                        {{-- ============================================= --}}
                        {{-- SHORT DESCRIPTION --}}
                        {{-- ============================================= --}}

                        @if ($product->short_description)

                            <p
                                class="mt-3 text-sm leading-6 text-neutral-600"
                            >
                                {{ $product->short_description }}
                            </p>

                        @endif


                        {{-- ============================================= --}}
                        {{-- PRICE --}}
                        {{-- ============================================= --}}

                        <div class="mt-6">

                            <div
                                data-selected-price
                                class="text-3xl font-semibold text-brand-800"
                            >
                                <x-storefront.price
                                    :price="$initialPrice"
                                    class="text-3xl"
                                />
                            </div>

                        </div>


                        {{-- ============================================= --}}
                        {{-- STOCK + SKU --}}
                        {{-- ============================================= --}}

                        <div
                            class="mt-4 flex flex-wrap items-center gap-4"
                        >

                            <div data-selected-stock>

                                @if ($initialStockState === 'in_stock')

                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700"
                                    >
                                        <span
                                            class="h-1.5 w-1.5 rounded-full bg-emerald-600"
                                        ></span>

                                        In Stock
                                    </span>

                                @else

                                    <x-storefront.stock-status
                                        :state="$initialStockState"
                                    />

                                @endif

                            </div>

                            <span
                                class="text-xs text-neutral-500"
                            >SKU: <span data-selected-sku>{{ $initialVariant->sku }}</span></span>

                        </div>


                        {{-- ============================================= --}}
                        {{-- VARIANT OPTIONS --}}
                        {{-- ============================================= --}}

                        @if ($product->isVariant() && $variantOptions->isNotEmpty())

                            <div
                                class="mt-6 space-y-5"
                                data-variant-selector
                            >

                                @foreach ($variantOptions as $option)

                                    <fieldset
                                        data-variant-attribute="{{ $option['code'] }}"
                                    >

                                        <legend
                                            class="text-sm font-semibold text-neutral-950"
                                        >
                                            {{ $option['name'] }}
                                        </legend>


                                        <div
                                            class="mt-3 flex flex-wrap gap-2"
                                        >

                                            @foreach ($option['values'] as $value)

                                                <button
                                                    type="button"
                                                    class="inline-flex min-h-10 items-center gap-2 rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm font-medium text-neutral-800 transition hover:border-neutral-500 disabled:cursor-not-allowed disabled:border-neutral-200 disabled:bg-neutral-50 disabled:text-neutral-400 data-[selected=true]:border-brand-700 data-[selected=true]:ring-1 data-[selected=true]:ring-brand-700"
                                                    data-variant-option
                                                    data-attribute-code="{{ $option['code'] }}"
                                                    data-value-code="{{ $value['code'] }}"
                                                    data-selected="false"
                                                    aria-pressed="false"
                                                >

                                                    @if (
                                                        ($value['hex'] ?? null)
                                                        && $option['code'] === 'color'
                                                    )

                                                        <span
                                                            class="h-6 w-6 rounded-full border border-neutral-300"
                                                            style="background-color: {{ $value['hex'] }}"
                                                        ></span>

                                                    @endif

                                                    <span>
                                                        {{ $value['label'] }}
                                                    </span>

                                                </button>

                                            @endforeach

                                        </div>

                                    </fieldset>

                                @endforeach

                            </div>

                        @endif


                        {{-- ============================================= --}}
                        {{-- PURCHASE FORM --}}
                        {{-- ============================================= --}}

                        <div
                            class="mt-6"
                            data-product-purchase-panel
                        >

                            @if (
                                auth()->guest()
                                || auth()->user()?->isCustomer()
                            )

                                <form
                                    method="POST"
                                    action="{{ route('cart.items.store') }}"
                                >

                                    @csrf

                                    <input
                                        type="hidden"
                                        name="product_variant_id"
                                        value="{{ $initialVariant->id }}"
                                        data-cart-variant-id
                                    >


                                    {{-- ================================= --}}
                                    {{-- QUANTITY --}}
                                    {{-- ================================= --}}

                                    <div>

                                        <label
                                            class="text-sm font-semibold text-neutral-950"
                                        >
                                            Quantity
                                        </label>


                                        <div
                                            class="mt-2 inline-flex h-11 overflow-hidden rounded-md border border-neutral-300 bg-white"
                                        >

                                            <button
                                                type="button"
                                                class="flex w-10 items-center justify-center text-lg text-neutral-600 transition hover:bg-neutral-50 hover:text-neutral-950"
                                                data-quantity-minus
                                                aria-label="Decrease quantity"
                                            >
                                                −
                                            </button>

                                            <input
                                                type="number"
                                                name="quantity"
                                                value="{{ old('quantity', 1) }}"
                                                min="1"
                                                max="{{ $maxQuantity }}"
                                                class="w-12 border-x border-neutral-200 bg-white text-center text-sm font-medium text-neutral-950 outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                                data-cart-quantity
                                            >

                                            <button
                                                type="button"
                                                class="flex w-10 items-center justify-center text-lg text-neutral-600 transition hover:bg-neutral-50 hover:text-neutral-950"
                                                data-quantity-plus
                                                aria-label="Increase quantity"
                                            >
                                                +
                                            </button>

                                        </div>

                                    </div>


                                    {{-- ================================= --}}
                                    {{-- CART BUTTON --}}
                                    {{-- ================================= --}}

                                    <button
                                        type="submit"
                                        class="mt-5 inline-flex h-12 w-full items-center justify-center gap-2 rounded-md bg-brand-700 px-6 text-sm font-semibold text-white transition hover:bg-brand-800 disabled:pointer-events-none disabled:opacity-50"
                                        data-cart-submit
                                        @disabled($initialStockState === 'out_of_stock')
                                    >

                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                            class="h-5 w-5"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M3 4h2l1.5 10h10.7l1.6-7H6"
                                            />

                                            <circle cx="9" cy="19" r="1" />
                                            <circle cx="17" cy="19" r="1" />
                                        </svg>

                                        {{ $initialStockState === 'out_of_stock'
                                            ? 'Out of Stock'
                                            : 'Add to Cart' }}

                                    </button>


                                    <p
                                        class="mt-3 text-xs leading-5 text-neutral-500"
                                        data-purchase-note
                                    >
                                        Select your preferred configuration
                                        and quantity.
                                    </p>


                                    @error('product_variant_id')

                                        <p
                                            class="mt-2 text-sm font-medium text-red-700"
                                        >
                                            {{ $message }}
                                        </p>

                                    @enderror


                                    @error('quantity')

                                        <p
                                            class="mt-2 text-sm font-medium text-red-700"
                                        >
                                            {{ $message }}
                                        </p>

                                    @enderror

                                </form>


                            @else

                                <div
                                    class="rounded-lg border border-neutral-200 bg-neutral-50 p-4"
                                >

                                    <p
                                        class="text-sm text-neutral-600"
                                    >
                                        Cart functionality is available for
                                        marketplace shoppers.
                                    </p>

                                </div>

                            @endif
                        </div>
                    </div>
                </aside>
            </div>


            {{-- ================================================= --}}
            {{-- VARIANT DATA --}}
            {{-- ================================================= --}}

            <script
                type="application/json"
                data-product-variants
            >
                @json($variantPayload)
            </script>

            <script
                type="application/json"
                data-product-fallback-gallery
            >
                @json($galleryPayload)
            </script>

        </x-container>

    </section>


    {{-- ========================================================= --}}
    {{-- PRODUCT INFORMATION + SELLER --}}
    {{-- ========================================================= --}}

    <section
        class="border-t border-neutral-100 bg-neutral-50 py-8"
    >

        <x-container>

            <div
                class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_300px]"
            >

                {{-- ================================================= --}}
                {{-- PRODUCT DETAILS --}}
                {{-- ================================================= --}}

                <div
                    class="overflow-hidden rounded-xl border border-neutral-200 bg-white"
                    data-product-tabs
                >

                    {{-- ============================================= --}}
                    {{-- TABS --}}
                    {{-- ============================================= --}}

                    <div
                        class="flex overflow-x-auto border-b border-neutral-200 px-5"
                    >

                        <button
                            type="button"
                            class="relative shrink-0 px-3 py-4 text-sm font-semibold text-brand-700 after:absolute after:bottom-0 after:left-0 after:right-0 after:h-0.5 after:bg-brand-700"
                            data-product-tab="about"
                            data-active="true"
                        >
                            About
                        </button>

                        <button
                            type="button"
                            class="relative shrink-0 px-3 py-4 text-sm font-medium text-neutral-500 transition hover:text-neutral-950"
                            data-product-tab="specifications"
                            data-active="false"
                        >
                            Specifications
                        </button>

                        <button
                            type="button"
                            class="relative shrink-0 px-3 py-4 text-sm font-medium text-neutral-500 transition hover:text-neutral-950"
                            data-product-tab="shipping"
                            data-active="false"
                        >
                            Shipping & Returns
                        </button>

                    </div>


                    {{-- ============================================= --}}
                    {{-- ABOUT --}}
                    {{-- ============================================= --}}

                    <div
                        class="p-6 sm:p-7"
                        data-product-tab-panel="about"
                    >

                        <h2
                            class="text-base font-semibold text-neutral-950"
                        >
                            About this product
                        </h2>

                        <div
                            class="mt-3 max-w-3xl whitespace-pre-line text-sm leading-7 text-neutral-600"
                        >
                            {{ $product->description
                                ?: $product->short_description
                                ?: 'Product details are being prepared.' }}
                        </div>

                    </div>


                    {{-- ============================================= --}}
                    {{-- SPECIFICATIONS --}}
                    {{-- ============================================= --}}

                    <div
                        class="hidden p-6 sm:p-7"
                        data-product-tab-panel="specifications"
                    >

                        <h2
                            class="text-base font-semibold text-neutral-950"
                        >
                            Product Specifications
                        </h2>


                        @if ($specifications->isNotEmpty())

                            <dl
                                class="mt-5 divide-y divide-neutral-100"
                            >

                                @foreach ($specifications as $specification)

                                    <div
                                        class="grid gap-1 py-3 sm:grid-cols-3 sm:gap-5"
                                    >

                                        <dt
                                            class="text-sm font-medium text-neutral-500"
                                        >
                                            {{ $specification['label'] }}
                                        </dt>

                                        <dd
                                            class="text-sm text-neutral-900 sm:col-span-2"
                                        >
                                            {{ $specification['value'] }}
                                        </dd>

                                    </div>

                                @endforeach

                            </dl>

                        @else

                            <p
                                class="mt-4 text-sm text-neutral-600"
                            >
                                Detailed specifications are being prepared.
                            </p>

                        @endif

                    </div>


                    {{-- ============================================= --}}
                    {{-- SHIPPING --}}
                    {{-- ============================================= --}}

                    <div
                        class="hidden p-6 sm:p-7"
                        data-product-tab-panel="shipping"
                    >

                        <div
                            class="grid gap-5 sm:grid-cols-2"
                        >

                            <div>

                                <h3
                                    class="text-sm font-semibold text-neutral-950"
                                >
                                    Shipping
                                </h3>

                                <p
                                    class="mt-2 text-sm leading-6 text-neutral-600"
                                >
                                    Delivery times and available Shipping
                                    methods are calculated according to your
                                    order and destination.
                                </p>

                            </div>


                            <div>

                                <h3
                                    class="text-sm font-semibold text-neutral-950"
                                >
                                    Returns
                                </h3>

                                <p
                                    class="mt-2 text-sm leading-6 text-neutral-600"
                                >
                                    Eligible products can be returned according
                                    to the marketplace return policy.
                                </p>

                            </div>

                        </div>

                    </div>

                </div>


                {{-- ================================================= --}}
                {{-- SIDEBAR --}}
                {{-- ================================================= --}}

                <div class="space-y-4">

                    {{-- ============================================= --}}
                    {{-- SELLER --}}
                    {{-- ============================================= --}}

                    <aside
                        class="rounded-xl border border-neutral-200 bg-white p-5"
                    >

                        <p
                            class="text-xs font-semibold uppercase tracking-wide text-neutral-500"
                        >
                            Sold by
                        </p>


                        <div
                            class="mt-4 flex items-center gap-3"
                        >

                            <div
                                class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-brand-50 text-base font-semibold text-brand-800"
                            >

                                @if ($product->vendor->logo_path)

                                    <img
                                        src="{{ Storage::url($product->vendor->logo_path) }}"
                                        alt="{{ $vendorName }} logo"
                                        class="h-full w-full object-cover"
                                    >

                                @else

                                    {{ $vendorInitial }}

                                @endif

                            </div>


                            <div class="min-w-0">

                                <div
                                    class="flex items-center gap-1.5"
                                >

                                    <h2
                                        class="truncate text-sm font-semibold text-neutral-950"
                                    >
                                        {{ $vendorName }}
                                    </h2>

                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        fill="currentColor"
                                        class="h-4 w-4 shrink-0 text-emerald-600"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M12 2.25a9.75 9.75 0 1 0 0 19.5 9.75 9.75 0 0 0 0-19.5Zm4.28 7.28a.75.75 0 0 0-1.06-1.06l-4.72 4.72-1.72-1.72a.75.75 0 1 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.06 0l5.25-5.25Z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>

                                </div>

                                <p
                                    class="mt-1 text-xs text-neutral-500"
                                >
                                    Verified marketplace seller
                                </p>

                            </div>

                        </div>


                        @if ($product->vendor->description)

                            <p
                                class="mt-4 line-clamp-3 text-xs leading-5 text-neutral-600"
                            >
                                {{ $product->vendor->description }}
                            </p>

                        @endif


                        <p
                            class="mt-4 text-xs text-neutral-500"
                        >
                            Member since
                            {{ $product->vendor->created_at->format('M Y') }}
                        </p>


                        <a
                            href="{{ route('storefront.stores.show', $product->vendor) }}"
                            class="mt-4 inline-flex h-10 w-full items-center justify-center gap-2 rounded-md border border-neutral-300 bg-white text-sm font-semibold text-brand-700 transition hover:border-brand-700 hover:bg-brand-50"
                        >
                            Visit Store

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
                                    d="M5 12h14M13 6l6 6-6 6"
                                />
                            </svg>

                        </a>

                    </aside>


                    {{-- ============================================= --}}
                    {{-- SHOP WITH CONFIDENCE --}}
                    {{-- ============================================= --}}

                    <aside
                        class="rounded-xl border border-neutral-200 bg-white p-5"
                    >

                        <h2
                            class="text-sm font-semibold text-brand-800"
                        >
                            Shop with confidence
                        </h2>


                        <div class="mt-5 space-y-5">

                            <div
                                class="flex gap-3"
                            >

                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.7"
                                        class="h-4 w-4"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M4 7h16v10H4zM8 11h8"
                                        />
                                    </svg>
                                </div>

                                <div>

                                    <p
                                        class="text-xs font-semibold text-neutral-900"
                                    >
                                        Secure Payments
                                    </p>

                                    <p
                                        class="mt-1 text-[11px] leading-4 text-neutral-500"
                                    >
                                        Your payment information is protected.
                                    </p>

                                </div>

                            </div>


                            <div
                                class="flex gap-3"
                            >

                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.7"
                                        class="h-4 w-4"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M12 3l7 3v5c0 4.6-2.9 8.4-7 10-4.1-1.6-7-5.4-7-10V6l7-3Z"
                                        />
                                    </svg>
                                </div>

                                <div>

                                    <p
                                        class="text-xs font-semibold text-neutral-900"
                                    >
                                        Buyer Protection
                                    </p>

                                    <p
                                        class="mt-1 text-[11px] leading-4 text-neutral-500"
                                    >
                                        Marketplace protection for eligible
                                        purchases.
                                    </p>

                                </div>

                            </div>


                            <div
                                class="flex gap-3"
                            >

                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.7"
                                        class="h-4 w-4"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="m5 12 4 4L19 6"
                                        />
                                    </svg>
                                </div>

                                <div>

                                    <p
                                        class="text-xs font-semibold text-neutral-900"
                                    >
                                        Verified Sellers
                                    </p>

                                    <p
                                        class="mt-1 text-[11px] leading-4 text-neutral-500"
                                    >
                                        Products are sold by approved marketplace
                                        sellers.
                                    </p>

                                </div>

                            </div>

                        </div>

                    </aside>

                </div>

            </div>

        </x-container>

    </section>

<section class="bg-white py-8">
    <x-container>

        <div
            class="overflow-hidden rounded-xl border border-neutral-200 bg-white"
        >

            {{-- ========================================================= --}}
            {{-- HEADER --}}
            {{-- ========================================================= --}}
            <div
                class="border-b border-neutral-200 px-5 py-4 sm:px-6"
            >
                <h2
                    class="text-xl font-semibold text-neutral-950"
                >
                    Customer Reviews
                </h2>
            </div>


            {{-- ========================================================= --}}
            {{-- CONTENT --}}
            {{-- ========================================================= --}}
            <div
                class="grid gap-0 lg:grid-cols-[300px_minmax(0,1fr)]"
            >

                {{-- ===================================================== --}}
                {{-- LEFT: REVIEW SUMMARY --}}
                {{-- ===================================================== --}}
                <div
                    class="border-b border-neutral-200 p-6 lg:border-b-0 lg:border-r"
                >

                    <div
                        class="flex items-end gap-2"
                    >
                        <span
                            class="text-5xl font-semibold leading-none text-neutral-950"
                        >
                            4.8
                        </span>

                        <span
                            class="pb-1 text-sm text-neutral-500"
                        >
                            / 5
                        </span>
                    </div>


                    {{-- Stars --}}
                    <div
                        class="mt-3 flex items-center gap-1 text-amber-400"
                    >
                        @for ($i = 0; $i < 5; $i++)
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="currentColor"
                                class="h-5 w-5"
                                aria-hidden="true"
                            >
                                <path
                                    d="M12 2.75l2.82 5.72 6.31.92-4.57 4.45 1.08 6.28L12 17.15l-5.64 2.97 1.08-6.28-4.57-4.45 6.31-.92L12 2.75Z"
                                />
                            </svg>
                        @endfor
                    </div>


                    <p
                        class="mt-2 text-sm text-neutral-500"
                    >
                        Based on 124 Reviews
                    </p>


                    {{-- ================================================= --}}
                    {{-- RATING BREAKDOWN --}}
                    {{-- ================================================= --}}
                    <div
                        class="mt-6 space-y-3"
                    >

                        {{-- 5 Stars --}}
                        <div
                            class="grid grid-cols-[32px_1fr_38px] items-center gap-2"
                        >
                            <span class="text-xs text-neutral-600">
                                5 ★
                            </span>

                            <div
                                class="h-2 overflow-hidden rounded-full bg-neutral-100"
                            >
                                <div
                                    class="h-full w-[82%] rounded-full bg-emerald-700"
                                ></div>
                            </div>

                            <span
                                class="text-right text-xs text-neutral-500"
                            >
                                82%
                            </span>
                        </div>


                        {{-- 4 Stars --}}
                        <div
                            class="grid grid-cols-[32px_1fr_38px] items-center gap-2"
                        >
                            <span class="text-xs text-neutral-600">
                                4 ★
                            </span>

                            <div
                                class="h-2 overflow-hidden rounded-full bg-neutral-100"
                            >
                                <div
                                    class="h-full w-[12%] rounded-full bg-emerald-700"
                                ></div>
                            </div>

                            <span
                                class="text-right text-xs text-neutral-500"
                            >
                                12%
                            </span>
                        </div>


                        {{-- 3 Stars --}}
                        <div
                            class="grid grid-cols-[32px_1fr_38px] items-center gap-2"
                        >
                            <span class="text-xs text-neutral-600">
                                3 ★
                            </span>

                            <div
                                class="h-2 overflow-hidden rounded-full bg-neutral-100"
                            >
                                <div
                                    class="h-full w-[4%] rounded-full bg-emerald-700"
                                ></div>
                            </div>

                            <span
                                class="text-right text-xs text-neutral-500"
                            >
                                4%
                            </span>
                        </div>


                        {{-- 2 Stars --}}
                        <div
                            class="grid grid-cols-[32px_1fr_38px] items-center gap-2"
                        >
                            <span class="text-xs text-neutral-600">
                                2 ★
                            </span>

                            <div
                                class="h-2 overflow-hidden rounded-full bg-neutral-100"
                            >
                                <div
                                    class="h-full w-[1%] rounded-full bg-emerald-700"
                                ></div>
                            </div>

                            <span
                                class="text-right text-xs text-neutral-500"
                            >
                                1%
                            </span>
                        </div>


                        {{-- 1 Star --}}
                        <div
                            class="grid grid-cols-[32px_1fr_38px] items-center gap-2"
                        >
                            <span class="text-xs text-neutral-600">
                                1 ★
                            </span>

                            <div
                                class="h-2 overflow-hidden rounded-full bg-neutral-100"
                            >
                                <div
                                    class="h-full w-[1%] rounded-full bg-emerald-700"
                                ></div>
                            </div>

                            <span
                                class="text-right text-xs text-neutral-500"
                            >
                                1%
                            </span>
                        </div>

                    </div>


                    {{-- Write Review --}}
                    <a
                        href="#"
                        class="mt-6 inline-flex h-10 items-center justify-center rounded-md border border-neutral-300 bg-white px-4 text-sm font-semibold text-neutral-800 transition hover:border-brand-700 hover:text-brand-700"
                    >
                        Write a Review
                    </a>

                </div>


                {{-- ===================================================== --}}
                {{-- RIGHT: REVIEWS --}}
                {{-- ===================================================== --}}
                <div>

                    {{-- ================================================= --}}
                    {{-- SORT --}}
                    {{-- ================================================= --}}
                    <div
                        class="flex flex-wrap items-center gap-x-6 gap-y-2 border-b border-neutral-200 px-5 py-4 sm:px-6"
                    >
                        <button
                            type="button"
                            class="text-sm font-semibold text-neutral-950"
                        >
                            Most Recent
                        </button>

                        <button
                            type="button"
                            class="text-sm font-medium text-neutral-500 transition hover:text-neutral-950"
                        >
                            Most Helpful
                        </button>

                        <button
                            type="button"
                            class="text-sm font-medium text-neutral-500 transition hover:text-neutral-950"
                        >
                            Highest Rating
                        </button>

                        <button
                            type="button"
                            class="text-sm font-medium text-neutral-500 transition hover:text-neutral-950"
                        >
                            Lowest Rating
                        </button>
                    </div>


                    {{-- ================================================= --}}
                    {{-- REVIEW ITEM --}}
                    {{-- ================================================= --}}
                    <article
                        class="p-5 sm:p-6"
                    >

                        <div
                            class="flex items-start gap-4"
                        >

                            {{-- Avatar --}}
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-sm font-semibold text-emerald-800"
                            >
                                S
                            </div>


                            <div class="min-w-0 flex-1">

                                {{-- User --}}
                                <div
                                    class="flex flex-wrap items-center gap-2"
                                >
                                    <p
                                        class="text-sm font-semibold text-neutral-950"
                                    >
                                        Sarah M.
                                    </p>

                                    <span
                                        class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 24 24"
                                            fill="currentColor"
                                            class="h-4 w-4"
                                            aria-hidden="true"
                                        >
                                            <path
                                                fill-rule="evenodd"
                                                d="M12 2.25a9.75 9.75 0 1 0 0 19.5 9.75 9.75 0 0 0 0-19.5Zm4.28 7.28a.75.75 0 0 0-1.06-1.06l-4.72 4.72-1.72-1.72a.75.75 0 1 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.06 0l5.25-5.25Z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>

                                        Verified Purchase
                                    </span>
                                </div>


                                {{-- Rating --}}
                                <div
                                    class="mt-2 flex items-center gap-1 text-amber-400"
                                >
                                    @for ($i = 0; $i < 5; $i++)
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 24 24"
                                            fill="currentColor"
                                            class="h-4 w-4"
                                            aria-hidden="true"
                                        >
                                            <path
                                                d="M12 2.75l2.82 5.72 6.31.92-4.57 4.45 1.08 6.28L12 17.15l-5.64 2.97 1.08-6.28-4.57-4.45 6.31-.92L12 2.75Z"
                                            />
                                        </svg>
                                    @endfor
                                </div>


                                {{-- Review Text --}}
                                <p
                                    class="mt-3 max-w-3xl text-sm leading-6 text-neutral-600"
                                >
                                    Great quality and exactly as described.
                                    The material feels premium and the product
                                    looks even better in person. Highly recommended!
                                </p>


                                {{-- Footer --}}
                                <div
                                    class="mt-4 flex flex-wrap items-center justify-between gap-3"
                                >
                                    <span
                                        class="text-xs text-neutral-400"
                                    >
                                        2 days ago
                                    </span>

                                    <button
                                        type="button"
                                        class="inline-flex h-9 items-center gap-2 rounded-md border border-neutral-200 bg-white px-3 text-xs font-medium text-neutral-600 transition hover:border-brand-700 hover:text-brand-700"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.7"
                                            class="h-4 w-4"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M7 10v10M3 10h4l3-7a2 2 0 0 1 2 2v3h5.5A2.5 2.5 0 0 1 20 10.5l-1 7A2.5 2.5 0 0 1 16.5 20H7"
                                            />
                                        </svg>

                                        Helpful (12)
                                    </button>
                                </div>

                            </div>

                        </div>

                    </article>


                    {{-- ================================================= --}}
                    {{-- SECOND REVIEW --}}
                    {{-- ================================================= --}}
                    <article
                        class="border-t border-neutral-100 p-5 sm:p-6"
                    >

                        <div
                            class="flex items-start gap-4"
                        >

                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-neutral-100 text-sm font-semibold text-neutral-700"
                            >
                                A
                            </div>


                            <div class="min-w-0 flex-1">

                                <div
                                    class="flex flex-wrap items-center gap-2"
                                >
                                    <p
                                        class="text-sm font-semibold text-neutral-950"
                                    >
                                        Ahmed R.
                                    </p>

                                    <span
                                        class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700"
                                    >
                                        Verified Purchase
                                    </span>
                                </div>


                                <div
                                    class="mt-2 flex items-center gap-1 text-amber-400"
                                >
                                    @for ($i = 0; $i < 4; $i++)
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 24 24"
                                            fill="currentColor"
                                            class="h-4 w-4"
                                        >
                                            <path
                                                d="M12 2.75l2.82 5.72 6.31.92-4.57 4.45 1.08 6.28L12 17.15l-5.64 2.97 1.08-6.28-4.57-4.45 6.31-.92L12 2.75Z"
                                            />
                                        </svg>
                                    @endfor

                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.4"
                                        class="h-4 w-4 text-neutral-300"
                                    >
                                        <path
                                            d="M12 2.75l2.82 5.72 6.31.92-4.57 4.45 1.08 6.28L12 17.15l-5.64 2.97 1.08-6.28-4.57-4.45 6.31-.92L12 2.75Z"
                                        />
                                    </svg>
                                </div>


                                <p
                                    class="mt-3 max-w-3xl text-sm leading-6 text-neutral-600"
                                >
                                    Good product overall. Packaging was secure
                                    and delivery was smooth.
                                </p>


                                <div
                                    class="mt-4 flex flex-wrap items-center justify-between gap-3"
                                >
                                    <span
                                        class="text-xs text-neutral-400"
                                    >
                                        1 week ago
                                    </span>

                                    <button
                                        type="button"
                                        class="inline-flex h-9 items-center gap-2 rounded-md border border-neutral-200 bg-white px-3 text-xs font-medium text-neutral-600 transition hover:border-brand-700 hover:text-brand-700"
                                    >
                                        Helpful (4)
                                    </button>
                                </div>

                            </div>

                        </div>

                    </article>

                </div>

            </div>

        </div>

    </x-container>
</section>

    {{-- ========================================================= --}}
    {{-- CUSTOMER REVIEWS --}}
    {{-- ========================================================= --}}

    <section id="reviews" class="bg-neutral-50 py-10 sm:py-12">
        <x-container>
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <x-badge tone="brand">Customer Reviews</x-badge>
                    <h2 class="mt-4 text-2xl font-semibold text-neutral-950">Verified Product Reviews</h2>
                    <p class="mt-2 text-sm text-neutral-600">Only approved reviews from delivered purchases appear here.</p>
                </div>
                <form method="GET" action="{{ route('storefront.products.show', $product) }}#reviews">
                    <select name="review_sort" class="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm" onchange="this.form.submit()">
                        <option value="newest" @selected($reviewSort === 'newest')>Newest</option>
                        <option value="highest" @selected($reviewSort === 'highest')>Highest Rating</option>
                        <option value="lowest" @selected($reviewSort === 'lowest')>Lowest Rating</option>
                    </select>
                </form>
            </div>

            <div class="grid gap-6 lg:grid-cols-[20rem_1fr]">
                <aside class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                    @if ($product->approvedReviewCount() > 0)
                        <div class="text-center">
                            <p class="text-4xl font-semibold text-neutral-950">{{ number_format($product->averageRating(), 1) }}</p>
                            <x-star-rating :rating="$product->averageRating()" class="mt-2 justify-center" />
                            <p class="mt-2 text-sm text-neutral-500">{{ number_format($product->approvedReviewCount()) }} approved reviews</p>
                        </div>
                    @else
                        <p class="text-sm text-neutral-600">No approved reviews yet.</p>
                    @endif

                    <div class="mt-6 space-y-3">
                        @foreach ($ratingDistribution as $stars => $row)
                            <div class="grid grid-cols-[3.5rem_1fr_3rem] items-center gap-3 text-xs">
                                <span class="font-medium text-neutral-700">{{ $stars }} stars</span>
                                <div class="h-2 overflow-hidden rounded-full bg-neutral-100">
                                    <div class="h-full rounded-full bg-amber-400" style="width: {{ $row['percentage'] }}%"></div>
                                </div>
                                <span class="text-right text-neutral-500">{{ $row['count'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </aside>

                <div class="space-y-4">
                    @forelse ($reviews as $review)
                        <article class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-star-rating :rating="$review->rating" />
                                        @if ($review->verified_purchase)
                                            <x-badge tone="success">Verified Purchase</x-badge>
                                        @endif
                                    </div>
                                    <h3 class="mt-3 text-base font-semibold text-neutral-950">{{ $review->title ?: 'Customer review' }}</h3>
                                </div>
                                <p class="text-xs text-neutral-500">{{ $review->reviewer_display_name }} · {{ $review->approved_at?->format('M j, Y') ?? $review->submitted_at->format('M j, Y') }}</p>
                            </div>
                            @if ($review->variant_snapshot)
                                <p class="mt-2 text-xs font-medium text-neutral-500">Purchased: {{ $review->variant_snapshot }}</p>
                            @endif
                            <p class="mt-3 whitespace-pre-line text-sm leading-7 text-neutral-700">{{ $review->body }}</p>
                        </article>
                    @empty
                        <x-empty-state title="No reviews yet" description="Approved verified-purchase reviews will appear here." />
                    @endforelse

                    <div>{{ $reviews->links() }}</div>
                </div>
            </div>
        </x-container>
    </section>

    {{-- ========================================================= --}}
    {{-- RELATED PRODUCTS --}}
    {{-- ========================================================= --}}

    @if ($relatedProducts->isNotEmpty())

        <section
            class="bg-white py-9"
        >

            <x-container>

                <div
                    class="flex items-end justify-between gap-5"
                >

                    <div>

                        <h2
                            class="text-xl font-semibold text-neutral-950"
                        >
                            You may also like
                        </h2>

                        <p
                            class="mt-1 text-sm text-neutral-500"
                        >
                            Similar products you may be interested in.
                        </p>

                    </div>

                </div>


                <div
                    class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6"
                >

                    @foreach ($relatedProducts as $relatedProduct)

                        <x-storefront.product-card
                            :product="$relatedProduct"
                            :is-wishlisted="in_array(
                                $relatedProduct->id,
                                $wishlistedProductIds ?? [],
                                true
                            )"
                        />

                    @endforeach

                </div>

            </x-container>

        </section>

    @endif

@endsection


@pushOnce('scripts')

    <script>
        document.addEventListener('DOMContentLoaded', () => {

            /*
            |--------------------------------------------------------------------------
            | Product Detail
            |--------------------------------------------------------------------------
            */

            const root = document.querySelector(
                '[data-product-detail]'
            );

            if (!root) {
                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Payloads
            |--------------------------------------------------------------------------
            */

            const variants = JSON.parse(
                document.querySelector(
                    '[data-product-variants]'
                )?.textContent || '[]'
            );

            const fallbackGallery = JSON.parse(
                document.querySelector(
                    '[data-product-fallback-gallery]'
                )?.textContent || '[]'
            );


            /*
            |--------------------------------------------------------------------------
            | Elements
            |--------------------------------------------------------------------------
            */

            const mainImage =
                root.querySelector('[data-main-image]');

            const strip =
                root.querySelector('[data-thumbnail-strip]');

            const price =
                root.querySelector('[data-selected-price]');

            const sku =
                root.querySelector('[data-selected-sku]');

            const stock =
                root.querySelector('[data-selected-stock]');

            const note =
                root.querySelector('[data-purchase-note]');

            const cartVariantId =
                root.querySelector('[data-cart-variant-id]');

            const cartSubmit =
                root.querySelector('[data-cart-submit]');

            const cartQuantity =
                root.querySelector('[data-cart-quantity]');

            const quantityMinus =
                root.querySelector('[data-quantity-minus]');

            const quantityPlus =
                root.querySelector('[data-quantity-plus]');

            const buttons = [
                ...root.querySelectorAll(
                    '[data-variant-option]'
                )
            ];

            const attributeCodes = [
                ...root.querySelectorAll(
                    '[data-variant-attribute]'
                )
            ].map(
                (item) => item.dataset.variantAttribute
            );


            /*
            |--------------------------------------------------------------------------
            | State
            |--------------------------------------------------------------------------
            */

            const selected = {};

            const initial =
                variants.find(
                    (variant) =>
                        String(variant.id) ===
                        root.dataset.initialVariant
                )
                || variants[0];


            /*
            |--------------------------------------------------------------------------
            | Stock HTML
            |--------------------------------------------------------------------------
            */

            function stockHtml(state) {

                const classes = {
                    in_stock:
                        'bg-emerald-50 text-emerald-700',
                    low_stock:
                        'bg-amber-50 text-amber-700',
                    out_of_stock:
                        'bg-neutral-100 text-neutral-600',
                };

                const label =
                    state === 'in_stock'
                        ? 'In Stock'
                        : state === 'low_stock'
                            ? 'Low Stock'
                            : 'Out of Stock';

                return `
                    <span
                        class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-semibold ${classes[state] || classes.out_of_stock}"
                    >
                        <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                        ${label}
                    </span>
                `;

            }


            /*
            |--------------------------------------------------------------------------
            | Variant Compatibility
            |--------------------------------------------------------------------------
            */

            function isCompatible(code, value) {

                const trial = {
                    ...selected,
                    [code]: value,
                };

                return variants.some(
                    (variant) =>
                        Object.entries(trial).every(
                            ([key, selectedValue]) =>
                                variant.attributes[key] ===
                                selectedValue
                        )
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Resolve Variant
            |--------------------------------------------------------------------------
            */

            function resolvedVariant() {

                if (!attributeCodes.length) {
                    return initial;
                }

                return variants.find(
                    (variant) =>
                        attributeCodes.every(
                            (code) =>
                                variant.attributes[code] ===
                                selected[code]
                        )
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Gallery
            |--------------------------------------------------------------------------
            */

            function setGallery(images) {

                const gallery =
                    images?.length
                        ? images
                        : fallbackGallery;

                if (!gallery.length || !strip) {
                    return;
                }


                if (mainImage) {

                    mainImage.src =
                        gallery[0].url;

                    mainImage.alt =
                        gallery[0].alt;

                }


                strip.replaceChildren(
                    ...gallery.map(
                        (image, index) => {

                            const button =
                                document.createElement(
                                    'button'
                                );

                            const img =
                                document.createElement(
                                    'img'
                                );


                            button.type =
                                'button';

                            button.className =
                                'h-[68px] w-[68px] shrink-0 overflow-hidden rounded-lg border border-neutral-200 bg-white p-1.5 transition hover:border-brand-600 data-[selected=true]:border-brand-700 data-[selected=true]:ring-1 data-[selected=true]:ring-brand-700';

                            button.dataset.galleryThumbnail =
                                '';

                            button.dataset.imageUrl =
                                image.url;

                            button.dataset.imageAlt =
                                image.alt;

                            button.dataset.selected =
                                index === 0
                                    ? 'true'
                                    : 'false';

                            button.setAttribute(
                                'aria-label',
                                `Show ${image.alt} image`
                            );


                            img.src =
                                image.url;

                            img.alt =
                                image.alt;

                            img.className =
                                'h-full w-full object-contain';


                            button.append(img);

                            return button;

                        }
                    )
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Thumbnail Events
            |--------------------------------------------------------------------------
            */

            function bindThumbnails() {

                root.querySelectorAll(
                    '[data-gallery-thumbnail]'
                ).forEach(
                    (button) => {

                        button.addEventListener(
                            'click',
                            () => {

                                if (mainImage) {

                                    mainImage.src =
                                        button.dataset.imageUrl;

                                    mainImage.alt =
                                        button.dataset.imageAlt;

                                }


                                root.querySelectorAll(
                                    '[data-gallery-thumbnail]'
                                ).forEach(
                                    (item) =>
                                        item.dataset.selected =
                                            'false'
                                );


                                button.dataset.selected =
                                    'true';

                            }
                        );

                    }
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Render Variant
            |--------------------------------------------------------------------------
            */

            function render() {

                buttons.forEach(
                    (button) => {

                        const code =
                            button.dataset.attributeCode;

                        const value =
                            button.dataset.valueCode;

                        const active =
                            selected[code] === value;

                        const compatible =
                            active
                            || isCompatible(
                                code,
                                value
                            );


                        button.dataset.selected =
                            active
                                ? 'true'
                                : 'false';

                        button.setAttribute(
                            'aria-pressed',
                            active
                                ? 'true'
                                : 'false'
                        );

                        button.disabled =
                            !compatible;

                    }
                );


                const variant =
                    resolvedVariant();


                if (!variant) {

                    if (note) {

                        note.textContent =
                            'Choose an available configuration.';

                    }

                    if (cartSubmit) {

                        cartSubmit.disabled =
                            true;

                        cartSubmit.textContent =
                            'Choose Options';

                    }

                    return;

                }


                if (price) {

                    price.innerHTML =
                        variant.price_html;

                }


                if (sku) {

                    sku.textContent =
                        variant.sku;

                }


                if (stock) {

                    stock.innerHTML =
                        stockHtml(
                            variant.stock_state
                        );

                }


                if (cartVariantId) {

                    cartVariantId.value =
                        variant.id;

                }


                if (cartQuantity) {

                    const max =
                        Number(
                            cartQuantity.max || 20
                        );

                    const current =
                        Number(
                            cartQuantity.value || 1
                        );

                    cartQuantity.value =
                        Math.max(
                            1,
                            Math.min(
                                current,
                                max
                            )
                        );

                }


                if (cartSubmit) {

                    cartSubmit.disabled =
                        variant.stock_state ===
                        'out_of_stock';

                    cartSubmit.innerHTML =
                        variant.stock_state ===
                        'out_of_stock'
                            ? 'Out of Stock'
                            : `
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    class="h-5 w-5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M3 4h2l1.5 10h10.7l1.6-7H6"
                                    ></path>
                                    <circle cx="9" cy="19" r="1"></circle>
                                    <circle cx="17" cy="19" r="1"></circle>
                                </svg>

                                Add to Cart
                            `;

                }


                if (note) {

                    note.textContent =
                        variant.stock_state ===
                        'out_of_stock'
                            ? 'This variation is currently unavailable.'
                            : 'This variation is available and ready to add to your cart.';

                }


                setGallery(
                    variant.images
                );

                bindThumbnails();

            }


            /*
            |--------------------------------------------------------------------------
            | Initial Variant
            |--------------------------------------------------------------------------
            */

            if (initial) {

                Object.assign(
                    selected,
                    initial.attributes
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Variant Buttons
            |--------------------------------------------------------------------------
            */

            buttons.forEach(
                (button) => {

                    button.addEventListener(
                        'click',
                        () => {

                            selected[
                                button.dataset.attributeCode
                            ] =
                                button.dataset.valueCode;

                            render();

                        }
                    );

                }
            );


            /*
            |--------------------------------------------------------------------------
            | Quantity Controls
            |--------------------------------------------------------------------------
            */

            quantityMinus?.addEventListener(
                'click',
                () => {

                    if (!cartQuantity) {
                        return;
                    }

                    const minimum =
                        Number(
                            cartQuantity.min || 1
                        );

                    const value =
                        Number(
                            cartQuantity.value || 1
                        );

                    cartQuantity.value =
                        Math.max(
                            minimum,
                            value - 1
                        );

                }
            );


            quantityPlus?.addEventListener(
                'click',
                () => {

                    if (!cartQuantity) {
                        return;
                    }

                    const maximum =
                        Number(
                            cartQuantity.max || 20
                        );

                    const value =
                        Number(
                            cartQuantity.value || 1
                        );

                    cartQuantity.value =
                        Math.min(
                            maximum,
                            value + 1
                        );

                }
            );


            /*
            |--------------------------------------------------------------------------
            | Product Detail Tabs
            |--------------------------------------------------------------------------
            */

            const tabsRoot =
                document.querySelector(
                    '[data-product-tabs]'
                );

            if (tabsRoot) {

                const tabs = [
                    ...tabsRoot.querySelectorAll(
                        '[data-product-tab]'
                    )
                ];

                const panels = [
                    ...tabsRoot.querySelectorAll(
                        '[data-product-tab-panel]'
                    )
                ];


                tabs.forEach(
                    (tab) => {

                        tab.addEventListener(
                            'click',
                            () => {

                                const target =
                                    tab.dataset.productTab;


                                tabs.forEach(
                                    (item) => {

                                        const active =
                                            item === tab;


                                        item.dataset.active =
                                            active
                                                ? 'true'
                                                : 'false';


                                        item.classList.toggle(
                                            'text-brand-700',
                                            active
                                        );

                                        item.classList.toggle(
                                            'font-semibold',
                                            active
                                        );

                                        item.classList.toggle(
                                            'text-neutral-500',
                                            !active
                                        );

                                        item.classList.toggle(
                                            'font-medium',
                                            !active
                                        );


                                        if (active) {

                                            item.classList.add(
                                                'after:absolute',
                                                'after:bottom-0',
                                                'after:left-0',
                                                'after:right-0',
                                                'after:h-0.5',
                                                'after:bg-brand-700'
                                            );

                                        } else {

                                            item.classList.remove(
                                                'after:absolute',
                                                'after:bottom-0',
                                                'after:left-0',
                                                'after:right-0',
                                                'after:h-0.5',
                                                'after:bg-brand-700'
                                            );

                                        }

                                    }
                                );


                                panels.forEach(
                                    (panel) => {

                                        panel.classList.toggle(
                                            'hidden',
                                            panel.dataset
                                                .productTabPanel
                                            !== target
                                        );

                                    }
                                );

                            }
                        );

                    }
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Initialize
            |--------------------------------------------------------------------------
            */

            bindThumbnails();

            render();

        });
    </script>

@endPushOnce
