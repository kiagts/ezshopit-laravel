@extends('layouts.app')

@php
    use App\Support\Money;
@endphp

@section('title', 'Shopping Cart')

@section('description', 'Review your current marketplace cart.')

@section('content')

    <section class="bg-neutral-50">

        <x-container class="py-8 sm:py-10 lg:py-12">

            {{-- ========================================================= --}}
            {{-- PAGE HEADER --}}
            {{-- ========================================================= --}}

            <div
                class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"
            >

                <div>

                    <div
                        class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800"
                    >
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
                                d="M3.5 5h2l1.5 10h10.5l1.5-7H7"
                            />

                            <circle cx="9" cy="19" r="1.25" />
                            <circle cx="17" cy="19" r="1.25" />
                        </svg>

                        Cart
                    </div>


                    <h1
                        class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950 sm:text-4xl"
                    >
                        Shopping Cart
                    </h1>


                    <div
                        class="mt-2 flex items-center gap-2 text-sm text-neutral-500"
                    >

                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                            class="h-4 w-4"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3.5 5h2l1.5 10h10.5l1.5-7H7"
                            />

                            <circle cx="9" cy="19" r="1.25" />
                            <circle cx="17" cy="19" r="1.25" />
                        </svg>

                        <span>
                            {{ number_format($item_quantity) }} {{ str('item')->plural($item_quantity) }}
                            across {{ number_format($groups->count()) }} {{ str('seller')->plural($groups->count()) }}
                        </span>

                    </div>

                </div>


                {{-- ===================================================== --}}
                {{-- HEADER ACTIONS --}}
                {{-- ===================================================== --}}

                <div
                    class="flex flex-wrap items-center gap-3"
                >

                    <a
                        href="{{ route('home') }}"
                        class="inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-neutral-300 bg-white px-5 text-sm font-semibold text-neutral-800 shadow-sm transition hover:border-neutral-400 hover:bg-neutral-50"
                    >
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
                                d="M19 12H5M11 18l-6-6 6-6"
                            />
                        </svg>

                        Continue Shopping
                    </a>


                    @if ($line_count > 0)

                        <form
                            method="POST"
                            action="{{ route('cart.clear') }}"
                            onsubmit="return confirm('Clear all items from your cart?')"
                        >
                            @csrf
                            @method('DELETE')

                            <button
                                type="submit"
                                class="inline-flex h-11 items-center justify-center gap-2 px-2 text-sm font-semibold text-red-600 transition hover:text-red-700"
                            >
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
                                        d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13"
                                    />

                                    <path
                                        stroke-linecap="round"
                                        d="M10 11v5M14 11v5"
                                    />
                                </svg>

                                Clear Cart
                            </button>
                        </form>

                    @endif

                </div>

            </div>


            {{-- ========================================================= --}}
            {{-- EMPTY CART --}}
            {{-- ========================================================= --}}

            @if ($line_count === 0)

                <div
                    class="mt-8 rounded-2xl border border-dashed border-neutral-300 bg-white px-6 py-16 text-center shadow-sm"
                >

                    <div
                        class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-emerald-700"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                            class="h-8 w-8"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3.5 5h2l1.5 10h10.5l1.5-7H7"
                            />

                            <circle cx="9" cy="19" r="1.25" />
                            <circle cx="17" cy="19" r="1.25" />
                        </svg>
                    </div>


                    <h2
                        class="mt-5 text-2xl font-semibold text-neutral-950"
                    >
                        Your cart is empty.
                    </h2>

                    <p
                        class="mx-auto mt-2 max-w-md text-sm leading-6 text-neutral-500"
                    >
                        Browse products from trusted marketplace sellers and
                        add something you like.
                    </p>

                    <x-button
                        :href="route('home')"
                        class="mt-6"
                    >
                        Start Shopping
                    </x-button>

                </div>

            @else

                {{-- ===================================================== --}}
                {{-- BLOCKING ISSUES --}}
                {{-- ===================================================== --}}

                @if ($has_blocking_issues)

                    <div
                        class="mt-6 flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4"
                    >

                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            class="mt-0.5 h-5 w-5 shrink-0 text-amber-700"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 4 3.5 19h17L12 4Z"
                            />

                            <path
                                stroke-linecap="round"
                                d="M12 9v4M12 16.5h.01"
                            />
                        </svg>

                        <p
                            class="text-sm leading-6 text-amber-900"
                        >
                            Some cart items need attention before checkout.
                            Unavailable items are not included in the subtotal.
                        </p>

                    </div>

                @endif


                {{-- ===================================================== --}}
                {{-- CART LAYOUT --}}
                {{-- ===================================================== --}}

                <div
                    class="mt-7 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] xl:grid-cols-[minmax(0,1fr)_24rem]"
                >

                    {{-- ================================================= --}}
                    {{-- LEFT --}}
                    {{-- ================================================= --}}

                    <div class="min-w-0 space-y-5">

                        {{-- ============================================= --}}
                        {{-- SELLER GROUPS --}}
                        {{-- ============================================= --}}

                        @foreach ($groups as $group)

                            <section
                                class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm"
                            >

                                {{-- ===================================== --}}
                                {{-- SELLER HEADER --}}
                                {{-- ===================================== --}}

                                <div
                                    class="flex flex-wrap items-center justify-between gap-4 border-b border-neutral-200 px-5 py-4 sm:px-6"
                                >

                                    <div
                                        class="flex items-center gap-3"
                                    >

                                        <div
                                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-700"
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
                                                    d="M4 10h16M5 10l1-5h12l1 5M6 10v9h12v-9"
                                                />

                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M9 19v-5h6v5"
                                                />
                                            </svg>
                                        </div>


                                        <div>

                                            <p
                                                class="text-xs font-medium text-neutral-500"
                                            >
                                                Sold by
                                            </p>

                                            @if ($group['vendor'])

                                                <a
                                                    href="{{ route('storefront.stores.show', $group['vendor']) }}"
                                                    class="mt-0.5 inline-block text-base font-semibold text-neutral-950 transition hover:text-brand-700"
                                                >
                                                    <span class="sr-only">Sold by </span>
                                                    {{ $group['vendor']->display_name }}
                                                </a>

                                            @else

                                                <p
                                                    class="mt-0.5 font-semibold text-neutral-950"
                                                >
                                                    Unavailable seller
                                                </p>

                                            @endif

                                        </div>

                                    </div>


                                    <p
                                        class="text-sm font-semibold text-neutral-900"
                                    >
                                        {{ Money::format($group['subtotal']) }}
                                    </p>

                                </div>


                                {{-- ===================================== --}}
                                {{-- PRODUCTS --}}
                                {{-- ===================================== --}}

                                <div
                                    class="divide-y divide-neutral-100"
                                >

                                    @foreach ($group['items'] as $row)

                                        @php
                                            $item = $row['item'];
                                            $product = $row['product'];
                                            $image = $row['image'];
                                        @endphp


                                        <article
                                            class="p-5 sm:p-6"
                                        >

                                            <div
                                                class="grid gap-5 sm:grid-cols-[130px_minmax(0,1fr)]"
                                            >

                                                {{-- ===================== --}}
                                                {{-- IMAGE --}}
                                                {{-- ===================== --}}

                                                <div
                                                    class="h-[130px] w-[130px] overflow-hidden rounded-xl border border-neutral-100 bg-neutral-50"
                                                >

                                                    @if ($product && $image)

                                                        <a
                                                            href="{{ route('storefront.products.show', $product) }}"
                                                            aria-label="View {{ $product->name }}"
                                                            class="block h-full w-full"
                                                        >
                                                            <img
                                                                src="{{ Storage::url($image->path) }}"
                                                                alt="{{ $image->alt_text ?: $product->name }}"
                                                                class="h-full w-full object-contain p-3"
                                                            >
                                                        </a>

                                                    @elseif ($product)

                                                        <a
                                                            href="{{ route('storefront.products.show', $product) }}"
                                                            class="block h-full w-full"
                                                        >
                                                            <x-storefront.image-placeholder
                                                                :label="$product->name"
                                                                class="h-full w-full"
                                                            />
                                                        </a>

                                                    @else

                                                        <div
                                                            class="flex h-full items-center justify-center text-sm font-medium text-neutral-400"
                                                        >
                                                            Item
                                                        </div>

                                                    @endif

                                                </div>


                                                {{-- ===================== --}}
                                                {{-- PRODUCT INFO --}}
                                                {{-- ===================== --}}

                                                <div
                                                    class="grid min-w-0 gap-5 lg:grid-cols-[minmax(0,1fr)_130px_130px]"
                                                >

                                                    {{-- Details --}}
                                                    <div>

                                                        @if ($product)

                                                            <a
                                                                href="{{ route('storefront.products.show', $product) }}"
                                                                class="text-base font-semibold text-neutral-950 transition hover:text-brand-700"
                                                            >
                                                                {{ $product->name }}
                                                            </a>


                                                            @if ($row['variant_label'])

                                                                <p
                                                                    class="mt-1 text-sm text-neutral-600"
                                                                >
                                                                    {{ $row['variant_label'] }}
                                                                </p>

                                                            @endif


                                                            <p
                                                                class="mt-1 text-xs text-neutral-500"
                                                            >
                                                                SKU:
                                                                {{ $row['variant']->sku }}
                                                            </p>


                                                            {{-- Status --}}
                                                            @if ($row['status'] === 'valid')

                                                                <div
                                                                    class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700"
                                                                >
                                                                    <svg
                                                                        xmlns="http://www.w3.org/2000/svg"
                                                                        viewBox="0 0 24 24"
                                                                        fill="none"
                                                                        stroke="currentColor"
                                                                        stroke-width="2"
                                                                        class="h-4 w-4"
                                                                    >
                                                                        <circle
                                                                            cx="12"
                                                                            cy="12"
                                                                            r="9"
                                                                        />

                                                                        <path
                                                                            stroke-linecap="round"
                                                                            stroke-linejoin="round"
                                                                            d="m8 12 2.5 2.5L16 9"
                                                                        />
                                                                    </svg>

                                                                    In Stock
                                                                </div>

                                                            @elseif ($row['message'])

                                                                <p
                                                                    class="mt-3 text-sm font-medium text-amber-700"
                                                                >
                                                                    {{ $row['message'] }}
                                                                </p>

                                                            @endif


                                                            @if (
                                                                $row['message']
                                                                && $row['status'] === 'valid'
                                                            )

                                                                <p
                                                                    class="mt-2 text-xs text-neutral-500"
                                                                >
                                                                    {{ $row['message'] }}
                                                                </p>

                                                            @endif

                                                        @else

                                                            <h2
                                                                class="font-semibold text-neutral-950"
                                                            >
                                                                This item is no longer available.
                                                            </h2>

                                                            @if ($row['message'])

                                                                <p
                                                                    class="mt-2 text-sm text-amber-700"
                                                                >
                                                                    {{ $row['message'] }}
                                                                </p>

                                                            @endif

                                                        @endif

                                                    </div>


                                                    {{-- Unit Price --}}
                                                    <div>

                                                        <p
                                                            class="text-xs font-medium text-neutral-500"
                                                        >
                                                            Unit Price
                                                        </p>

                                                        @if ($row['unit_price'])

                                                            <div class="mt-2">
                                                                <x-storefront.price
                                                                    :price="$row['price']"
                                                                />
                                                            </div>

                                                        @else

                                                            <p
                                                                class="mt-2 text-sm font-medium text-neutral-500"
                                                            >
                                                                Price unavailable
                                                            </p>

                                                        @endif

                                                    </div>


                                                    {{-- Total --}}
                                                    <div>

                                                        <p
                                                            class="text-xs font-medium text-neutral-500"
                                                        >
                                                            Total
                                                        </p>

                                                        <p
                                                            class="mt-2 text-base font-semibold text-neutral-950"
                                                        >
                                                            {{ $row['line_total_formatted'] }}
                                                        </p>

                                                    </div>

                                                </div>

                                            </div>


                                            {{-- ================================= --}}
                                            {{-- ITEM FOOTER --}}
                                            {{-- ================================= --}}

                                            <div
                                                class="mt-5 flex flex-col gap-4 border-t border-neutral-100 pt-5 sm:ml-[150px] lg:flex-row lg:items-end lg:justify-between"
                                            >

                                                {{-- Quantity --}}
                                                <div>

                                                    @if (
                                                        in_array(
                                                            $row['status'],
                                                            ['valid', 'insufficient_stock'],
                                                            true
                                                        )
                                                    )

                                                        <form
                                                            method="POST"
                                                            action="{{ route('cart.items.update', $item) }}"
                                                            class="flex flex-wrap items-end gap-3"
                                                            data-cart-quantity-form
                                                        >
                                                            @csrf
                                                            @method('PATCH')


                                                            <div>

                                                                <label
                                                                    class="text-xs font-medium text-neutral-500"
                                                                >
                                                                    Quantity
                                                                </label>


                                                                <div
                                                                    class="mt-2 inline-flex h-10 overflow-hidden rounded-md border border-neutral-300 bg-white"
                                                                >

                                                                    <button
                                                                        type="button"
                                                                        class="flex w-10 items-center justify-center text-lg text-neutral-500 transition hover:bg-neutral-50 hover:text-neutral-950"
                                                                        data-cart-minus
                                                                        aria-label="Decrease quantity"
                                                                    >
                                                                        −
                                                                    </button>


                                                                    <input
                                                                        name="quantity"
                                                                        type="number"
                                                                        min="1"
                                                                        max="{{ $maxQuantity }}"
                                                                        value="{{ $item->quantity }}"
                                                                        class="w-12 border-x border-neutral-200 bg-white text-center text-sm font-semibold text-neutral-950 outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                                                        data-cart-quantity-input
                                                                    >


                                                                    <button
                                                                        type="button"
                                                                        class="flex w-10 items-center justify-center text-lg text-neutral-500 transition hover:bg-neutral-50 hover:text-neutral-950"
                                                                        data-cart-plus
                                                                        aria-label="Increase quantity"
                                                                    >
                                                                        +
                                                                    </button>

                                                                </div>

                                                            </div>


                                                            <button
                                                                type="submit"
                                                                class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-neutral-300 bg-white px-4 text-sm font-semibold text-neutral-800 transition hover:border-brand-600 hover:text-brand-700"
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
                                                                        d="M20 7v5h-5M4 17v-5h5"
                                                                    />

                                                                    <path
                                                                        stroke-linecap="round"
                                                                        d="M6.2 8A7 7 0 0 1 18 6l2 1M18 16a7 7 0 0 1-12 2l-2-1"
                                                                    />
                                                                </svg>

                                                                Update
                                                            </button>

                                                        </form>

                                                    @else

                                                        <p
                                                            class="text-sm text-neutral-500"
                                                        >
                                                            Quantity:
                                                            {{ $item->quantity }}
                                                        </p>

                                                    @endif

                                                </div>


                                                {{-- Remove --}}
                                                <form
                                                    method="POST"
                                                    action="{{ route('cart.items.destroy', $item) }}"
                                                >
                                                    @csrf
                                                    @method('DELETE')

                                                    <button
                                                        type="submit"
                                                        class="inline-flex items-center gap-2 text-sm font-semibold text-red-600 transition hover:text-red-700"
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
                                                                d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13"
                                                            />

                                                            <path
                                                                stroke-linecap="round"
                                                                d="M10 11v5M14 11v5"
                                                            />
                                                        </svg>

                                                        Remove
                                                    </button>
                                                </form>

                                            </div>

                                        </article>

                                    @endforeach

                                </div>

                            </section>

                        @endforeach


                        {{-- ============================================= --}}
                        {{-- TRUST STRIP --}}
                        {{-- ============================================= --}}

                        <div
                            class="grid overflow-hidden rounded-2xl border border-neutral-200 bg-white sm:grid-cols-2 xl:grid-cols-4"
                        >

                            @foreach ([
                                [
                                    'title' => 'Secure Checkout',
                                    'text' => 'Your data is protected',
                                    'icon' => 'shield',
                                ],
                                [
                                    'title' => 'Fast Delivery',
                                    'text' => 'Quick & reliable',
                                    'icon' => 'truck',
                                ],
                                [
                                    'title' => 'Easy Returns',
                                    'text' => 'Simple return process',
                                    'icon' => 'return',
                                ],
                                [
                                    'title' => 'Buyer Protection',
                                    'text' => 'Shop with confidence',
                                    'icon' => 'check',
                                ],
                            ] as $benefit)

                                <div
                                    class="flex items-center gap-3 border-b border-neutral-100 p-4 last:border-0 sm:[&:nth-child(odd)]:border-r xl:border-b-0 xl:border-r xl:last:border-r-0"
                                >

                                    <div
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-700"
                                    >

                                        @if ($benefit['icon'] === 'shield')

                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.7"
                                                class="h-5 w-5"
                                            >
                                                <path
                                                    stroke-linejoin="round"
                                                    d="M12 3 19 6v5c0 4.6-2.9 8.4-7 10-4.1-1.6-7-5.4-7-10V6l7-3Z"
                                                />

                                                <path
                                                    stroke-linecap="round"
                                                    d="m9 12 2 2 4-5"
                                                />
                                            </svg>

                                        @elseif ($benefit['icon'] === 'truck')

                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.7"
                                                class="h-5 w-5"
                                            >
                                                <path
                                                    stroke-linejoin="round"
                                                    d="M3 6h11v10H3zM14 9h3l4 4v3h-7"
                                                />

                                                <circle cx="7" cy="18" r="1.5" />
                                                <circle cx="18" cy="18" r="1.5" />
                                            </svg>

                                        @elseif ($benefit['icon'] === 'return')

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

                                        @else

                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.7"
                                                class="h-5 w-5"
                                            >
                                                <circle
                                                    cx="12"
                                                    cy="12"
                                                    r="9"
                                                />

                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="m8 12 2.5 2.5L16 9"
                                                />
                                            </svg>

                                        @endif

                                    </div>


                                    <div>

                                        <p
                                            class="text-sm font-semibold text-neutral-950"
                                        >
                                            {{ $benefit['title'] }}
                                        </p>

                                        <p
                                            class="mt-0.5 text-xs text-neutral-500"
                                        >
                                            {{ $benefit['text'] }}
                                        </p>

                                    </div>

                                </div>

                            @endforeach

                        </div>

                    </div>


                    {{-- ================================================= --}}
                    {{-- ORDER SUMMARY --}}
                    {{-- ================================================= --}}

                    <aside
                        class="h-fit rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm sm:p-6 lg:sticky lg:top-28"
                    >

                        <div
                            class="flex items-center gap-3"
                        >

                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50 text-emerald-700"
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
                                        d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"
                                    />

                                    <path
                                        stroke-linecap="round"
                                        d="M9 8h6M9 12h6"
                                    />
                                </svg>
                            </div>


                            <h2
                                class="text-xl font-semibold text-neutral-950"
                            >
                                Order Summary
                            </h2>

                        </div>

                        <p class="mt-3 text-xs leading-5 text-neutral-500">
                            Shipping and taxes are calculated at checkout.
                        </p>


                        <div
                            class="mt-6 space-y-4 text-sm"
                        >

                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <span class="text-neutral-600">
                                    Items Subtotal
                                </span>

                                <span
                                    class="font-semibold text-neutral-950"
                                >
                                    {{ $subtotal_formatted }}
                                </span>
                            </div>


                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <span class="text-neutral-600">
                                    Shipping
                                </span>

                                <span class="text-right text-neutral-500">
                                    Calculated at checkout
                                </span>
                            </div>


                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <span class="text-neutral-600">
                                    Estimated Tax
                                </span>

                                <span class="text-right text-neutral-500">
                                    Calculated at checkout
                                </span>
                            </div>

                        </div>


                        <div
                            class="my-5 border-t border-neutral-200"
                        ></div>


                        <div
                            class="flex items-center justify-between gap-4"
                        >

                            <span
                                class="text-base font-semibold text-neutral-950"
                            >
                                Estimated Total
                            </span>

                            <span
                                class="text-xl font-semibold text-emerald-800"
                            >
                                {{ $subtotal_formatted }}
                            </span>

                        </div>


                        {{-- ============================================= --}}
                        {{-- CHECKOUT --}}
                        {{-- ============================================= --}}

                        @if ($has_blocking_issues)

                            <div
                                class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900"
                            >
                                Resolve unavailable or stock-limited items
                                before checkout.
                            </div>

                        @else

                            <a
                                href="{{ route('checkout.index') }}"
                                class="mt-6 inline-flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-brand-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-800"
                            >

                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    class="h-5 w-5"
                                >
                                    <rect
                                        x="5"
                                        y="10"
                                        width="14"
                                        height="10"
                                        rx="2"
                                    />

                                    <path
                                        stroke-linecap="round"
                                        d="M8 10V8a4 4 0 0 1 8 0v2"
                                    />
                                </svg>

                                Proceed to Checkout

                            </a>

                        @endif

                        {{-- ============================================= --}}
                        {{-- SECURITY --}}
                        {{-- ============================================= --}}

                        <div
                            class="mt-6 flex gap-3 rounded-xl bg-emerald-50/70 p-4"
                        >

                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.7"
                                class="mt-0.5 h-5 w-5 shrink-0 text-emerald-700"
                            >
                                <path
                                    stroke-linejoin="round"
                                    d="M12 3 19 6v5c0 4.6-2.9 8.4-7 10-4.1-1.6-7-5.4-7-10V6l7-3Z"
                                />

                                <path
                                    stroke-linecap="round"
                                    d="m9 12 2 2 4-5"
                                />
                            </svg>


                            <div>

                                <p
                                    class="text-sm font-semibold text-neutral-900"
                                >
                                    Safe and secure payments
                                </p>

                                <p
                                    class="mt-1 text-xs leading-5 text-neutral-500"
                                >
                                    Your payment information is protected
                                    throughout checkout.
                                </p>

                            </div>

                        </div>

                    </aside>

                </div>

            @endif

        </x-container>

    </section>

@endsection


@pushOnce('scripts')

    <script>
        document.addEventListener('DOMContentLoaded', () => {

            /*
            |--------------------------------------------------------------------------
            | Cart Quantity Controls
            |--------------------------------------------------------------------------
            */

            const quantityForms = document.querySelectorAll(
                '[data-cart-quantity-form]'
            );

            quantityForms.forEach((form) => {

                const input = form.querySelector(
                    '[data-cart-quantity-input]'
                );

                const minus = form.querySelector(
                    '[data-cart-minus]'
                );

                const plus = form.querySelector(
                    '[data-cart-plus]'
                );


                if (!input) {
                    return;
                }


                /*
                |--------------------------------------------------------------------------
                | Normalize
                |--------------------------------------------------------------------------
                */

                const normalize = (value) => {

                    const minimum =
                        Number(input.min || 1);

                    const maximum =
                        Number(input.max || 20);

                    return Math.max(
                        minimum,
                        Math.min(
                            maximum,
                            Number(value || minimum)
                        )
                    );

                };


                /*
                |--------------------------------------------------------------------------
                | Decrease
                |--------------------------------------------------------------------------
                */

                minus?.addEventListener(
                    'click',
                    () => {

                        input.value = normalize(
                            Number(input.value || 1) - 1
                        );

                    }
                );


                /*
                |--------------------------------------------------------------------------
                | Increase
                |--------------------------------------------------------------------------
                */

                plus?.addEventListener(
                    'click',
                    () => {

                        input.value = normalize(
                            Number(input.value || 1) + 1
                        );

                    }
                );


                /*
                |--------------------------------------------------------------------------
                | Manual Input
                |--------------------------------------------------------------------------
                */

                input.addEventListener(
                    'change',
                    () => {

                        input.value = normalize(
                            input.value
                        );

                    }
                );

            });

        });
    </script>

@endPushOnce
