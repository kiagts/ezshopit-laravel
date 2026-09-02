@props(['categories' => collect()])

@php
    $dashboardPath = auth()->check()
        ? auth()->user()->dashboardPath()
        : null;
@endphp

<header class="sticky top-0 z-40 border-b border-neutral-200 bg-white">

    {{-- ========================================================= --}}
    {{-- MAIN HEADER --}}
    {{-- ========================================================= --}}
    <div class="border-b border-neutral-100">

        <x-container class="flex min-h-16 flex-nowrap items-center gap-4 py-3">
            {{-- ========================================================= --}}
            {{-- MOBILE MENU BUTTON --}}
            {{-- ========================================================= --}}
            <button
                type="button"
                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-neutral-200 text-neutral-700 lg:hidden"
                data-mobile-menu-open
                aria-label="Open navigation"
            >
                <svg
                    aria-hidden="true"
                    class="h-5 w-5"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                >
                    <path
                        fill-rule="evenodd"
                        d="M3 5.5A.75.75 0 0 1 3.75 4.75h12.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 5.5Zm0 4.5a.75.75 0 0 1 .75-.75h12.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 10Zm0 4.5a.75.75 0 0 1 .75-.75h12.5a.75.75 0 0 1 0 1.5H3.75A.75.75 0 0 1 3 14.5Z"
                        clip-rule="evenodd"
                    />
                </svg>
            </button>

            {{-- ========================================================= --}}
            {{-- LEFT: LOGO --}}
            {{-- ========================================================= --}}
            <div class="flex shrink-0 items-center lg:w-52">
                <a
                    href="{{ route('home') }}"
                    class="text-lg font-semibold tracking-normal text-neutral-950"
                >
                    <img 
                        src="{{ Storage::url('frontstore/images/logo.png') }}"
                        alt="{{ config('marketplace.name') }}"
                    >
                </a>
            </div>

            {{-- ========================================================= --}}
            {{-- CENTER: SEARCH --}}
            {{-- ========================================================= --}}
            <div class="hidden min-w-0 flex-1 lg:block">

                <form
                    method="GET"
                    action="{{ route('storefront.search') }}"
                    role="search"
                    class="mx-auto w-full max-w-xl"
                    aria-label="Marketplace search"
                >
                    <div
                        class="flex h-11 items-center rounded-md border border-neutral-300 bg-neutral-50 px-3 transition focus-within:border-brand-700 focus-within:bg-white focus-within:ring-2 focus-within:ring-brand-100"
                    >
                        {{-- Search Icon --}}
                        <svg
                            aria-hidden="true"
                            class="h-5 w-5 shrink-0 text-neutral-400"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M9 3.5a5.5 5.5 0 1 0 3.473 9.765l2.631 2.631a.75.75 0 1 0 1.06-1.06l-2.63-2.632A5.5 5.5 0 0 0 9 3.5ZM5 9a4 4 0 1 1 8 0A4 4 0 0 1 5 9Z"
                                clip-rule="evenodd"
                            />
                        </svg>

                        {{-- Search Input --}}
                        <input
                            name="q"
                            value="{{ request('q') }}"
                            type="search"
                            placeholder="Search products, brands, and stores"
                            class="h-full min-w-0 flex-1 border-0 bg-transparent px-3 text-sm text-neutral-900 placeholder:text-neutral-400 focus:outline-none"
                            aria-label="Search marketplace"
                        >

                        {{-- Search Button --}}
                        <button
                            type="submit"
                            class="shrink-0 rounded-md bg-brand-700 px-5 py-2 text-sm font-medium text-white transition hover:bg-brand-800"
                        >
                            Search
                        </button>
                    </div>
                </form>
            </div>

            {{-- ========================================================= --}}
            {{-- RIGHT: HEADER ACTIONS --}}
            {{-- ========================================================= --}}
            <nav
                class="ml-auto flex shrink-0 items-center justify-end gap-4 sm:gap-5 lg:ml-0 lg:w-auto"
                aria-label="Account navigation"
            >
                {{-- ===================================================== --}}
                {{-- WISHLIST --}}
                {{-- ===================================================== --}}
                @guest
                    <a
                        href="{{ route('login') }}"
                        class="group relative flex min-w-14 shrink-0 flex-col items-center justify-center gap-1 text-neutral-700 transition hover:text-neutral-950"
                        aria-label="Wishlist"
                        title="Wishlist"
                    >
                        {{-- Icon --}}
                        <div class="relative">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.5"
                                class="h-5 w-5"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78Z"
                                />
                            </svg>
                        </div>

                        {{-- Text --}}
                        <span class="text-xs font-medium leading-none text-current">
                            Wishlist
                        </span>
                    </a>

                @elseif(auth()->user()->isCustomer())

                    <a
                        href="{{ route('account.wishlist.index') }}"
                        class="group relative flex min-w-14 shrink-0 flex-col items-center justify-center gap-1 text-neutral-700 transition hover:text-neutral-950"
                        aria-label="Wishlist"
                        title="Wishlist"
                    >
                        {{-- Icon + Count --}}
                        <div class="relative">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.5"
                                class="h-5 w-5"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78Z"
                                />
                            </svg>

                            @if (($wishlistCount ?? 0) > 0)
                                <span
                                    class="absolute -right-3 -top-2 flex h-4 min-w-4 items-center justify-center rounded-full bg-neutral-950 px-1 text-[9px] font-semibold leading-none text-white"
                                >{{ $wishlistCount }}</span>
                            @endif
                        </div>

                        {{-- Text --}}
                        <span class="text-xs font-medium leading-none text-current">
                            Wishlist
                        </span>
                    </a>

                @endif

                {{-- ===================================================== --}}
                {{-- CART --}}
                {{-- ===================================================== --}}
                @if (auth()->guest() || auth()->user()?->isCustomer())
                    <a
                        href="{{ route('cart.index') }}"
                        class="group relative flex min-w-12 shrink-0 flex-col items-center justify-center gap-1 text-neutral-700 transition hover:text-neutral-950"
                        aria-label="Cart"
                        title="Cart"
                    >
                        {{-- Icon --}}
                        <div class="relative">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.5"
                                class="h-5 w-5"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M7 8V6.75a5 5 0 0 1 10 0V8"
                                />

                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M4.25 8h15.5l-.8 11.1a2.1 2.1 0 0 1-2.1 1.95H7.15a2.1 2.1 0 0 1-2.1-1.95L4.25 8Z"
                                />
                            </svg>

                            {{-- Cart Count --}}
                            @if (($cartCount ?? 0) > 0)
                                <span
                                    class="absolute -right-3 -top-2 flex h-4 min-w-4 items-center justify-center rounded-full bg-neutral-950 px-1 text-[9px] font-semibold leading-none text-white"
                                >{{ $cartCount }}</span>
                            @endif
                        </div>

                        {{-- Text --}}
                        <span class="text-xs font-medium leading-none text-current">
                            Cart
                        </span>
                    </a>
                @endif

                {{-- ===================================================== --}}
                {{-- ACCOUNT --}}
                {{-- ===================================================== --}}
                <a
                    href="{{ auth()->check() ? $dashboardPath : route('login') }}"
                    class="group relative flex min-w-14 shrink-0 flex-col items-center justify-center gap-1 text-neutral-700 transition hover:text-neutral-950"
                    aria-label="Account"
                    title="Account"
                >
                    {{-- Account Icon --}}
                    <div class="relative">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.5"
                            class="h-5 w-5"
                            aria-hidden="true"
                        >
                            <circle
                                cx="12"
                                cy="7"
                                r="3.75"
                            />

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M4.75 20.5c0-4.15 3.1-7 7.25-7s7.25 2.85 7.25 7"
                            />
                        </svg>
                    </div>

                    {{-- Account Text --}}
                    <span class="text-xs font-medium leading-none text-current">
                        @guest
                            Account
                        @else
                           Hi, {{ strtok(auth()->user()->name, ' ') }}
                        @endguest
                    </span>
                </a>

            </nav>
        </x-container>


        {{-- ========================================================= --}}
        {{-- MOBILE SEARCH --}}
        {{-- ========================================================= --}}
        <x-container class="pb-3 lg:hidden">

            <form
                method="GET"
                action="{{ route('storefront.search') }}"
                role="search"
                aria-label="Mobile marketplace search"
            >
                <div
                    class="flex h-11 items-center rounded-md border border-neutral-300 bg-neutral-50 px-3"
                >

                    <svg
                        aria-hidden="true"
                        class="h-5 w-5 shrink-0 text-neutral-400"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M9 3.5a5.5 5.5 0 1 0 3.473 9.765l2.631 2.631a.75.75 0 1 0 1.06-1.06l-2.63-2.632A5.5 5.5 0 0 0 9 3.5ZM5 9a4 4 0 1 1 8 0A4 4 0 0 1 5 9Z"
                            clip-rule="evenodd"
                        />
                    </svg>

                    <input
                        name="q"
                        value="{{ request('q') }}"
                        type="search"
                        placeholder="Search marketplace"
                        class="h-full min-w-0 flex-1 border-0 bg-transparent px-3 text-sm text-neutral-900 placeholder:text-neutral-400 focus:outline-none"
                        aria-label="Search marketplace"
                    >

                </div>
            </form>

        </x-container>

    </div>

    {{-- ========================================================= --}}
    {{-- DESKTOP CATEGORY NAV --}}
    {{-- ========================================================= --}}
    <div class="hidden border-b border-neutral-100 lg:block">

        <x-container class="flex items-center gap-6 py-2 text-sm">
            {{-- Categories Trigger --}}
            <button
                type="button"
                class="inline-flex h-11 items-center gap-2 rounded-lg border border-neutral-300 bg-white px-4 font-medium text-neutral-900 transition hover:border-brand-700 hover:text-brand-700"
                data-desktop-menu-open
            >
                <svg
                    aria-hidden="true"
                    class="h-5 w-5 text-neutral-700"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M4 6h16M4 12h16M4 18h16"
                    />
                </svg>
                Categories
            </button>

            <span class="font-medium text-dark hover:text-dark">
                New Arrivals
            </span>

            <span class="font-medium text-dark hover:text-dark">
                Best Sellers
            </span>

            <span class="font-medium text-dark hover:text-dark-300">
                Deals
            </span>

            <span class="font-medium text-dark hover:text-dark">
                Brands
            </span>

            <a
                href="{{ route('vendor.apply') }}"
                class="ml-auto inline-flex items-center gap-2 text-sm font-medium text-dark transition hover:text-dark"
            >
                <svg
                    aria-hidden="true"
                    class="h-6 w-6 shrink-0"
                    viewBox="0 0 24 24"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <circle
                        cx="8.5"
                        cy="6.25"
                        r="2.75"
                        stroke="currentColor"
                        stroke-width="1.8"
                    />

                    <path
                        d="M3.5 16.75C3.85 13.65 5.75 12 8.5 12C10.15 12 11.45 12.55 12.4 13.45"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                    />

                    <circle
                        cx="16.75"
                        cy="15.75"
                        r="3.75"
                        stroke="currentColor"
                        stroke-width="1.8"
                    />

                    <path
                        d="M16.75 13.85V17.65"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                    />

                    <path
                        d="M14.85 15.75H18.65"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                    />
                </svg>

                <span class="leading-none">
                    Sell With {{ config('marketplace.name') }}
                </span>
            </a>

        </x-container>

    </div>

    {{-- ========================================================= --}}
    {{-- DESKTOP CATEGORY LEFTBAR --}}
    {{-- ========================================================= --}}
    <div
        class="fixed inset-0 z-50 invisible hidden lg:block"
        data-desktop-menu
    >

        {{-- Overlay --}}
        <div
            class="absolute inset-0 bg-neutral-950/45 opacity-0 transition-opacity duration-300 ease-out"
            data-desktop-menu-overlay
        ></div>

        {{-- Sidebar --}}
        <aside
            class="absolute left-0 top-0 flex h-full w-[470px] max-w-[92vw] -translate-x-full flex-col bg-white shadow-2xl transition-transform duration-300 ease-out will-change-transform"
            data-desktop-menu-panel
        >

            {{-- ================================================= --}}
            {{-- LEFTBAR HEADER --}}
            {{-- ================================================= --}}
            <div
                class="flex h-24 shrink-0 items-center justify-between border-b border-neutral-200 px-8"
            >

                <a
                    href="{{ route('home') }}"
                    class="text-2xl font-semibold tracking-tight text-neutral-950"
                >
                    <img
                        src="{{ Storage::url('frontstore/images/logo.png') }}"
                        alt="{{ config('marketplace.name') }}"
                        class="h-auto w-[150px] object-contain"
                    >
                </a>

                <button
                    type="button"
                    class="relative z-10 inline-flex h-11 w-11 shrink-0 items-center justify-center text-neutral-800 transition hover:bg-neutral-100 hover:text-neutral-950"
                    data-desktop-menu-close
                    aria-label="Close categories"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M6 6L18 18"
                        />

                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M18 6L6 18"
                        />
                    </svg>
                </button>

            </div>

            {{-- ================================================= --}}
            {{-- CATEGORY CONTENT --}}
            {{-- ================================================= --}}
            <div class="flex-1 overflow-y-auto px-8 py-7 [scrollbar-color:#d4d4d4_transparent] [scrollbar-width:thin]">

                <h2 class="mb-5 mt-0 text-xl font-semibold text-neutral-950">
                    Categories
                </h2>

                <div class="space-y-1">

                    @forelse ($categories as $category)

                        @if ($category->activeChildren->isNotEmpty())

                            <details class="group">

                                {{-- Parent Category --}}
                                <summary
                                    class="flex cursor-pointer list-none items-center justify-between rounded-lg px-3 py-3 text-[15px] font-medium text-neutral-900 transition hover:bg-neutral-50"
                                >

                                    <span class="min-w-0 truncate">
                                        {{ $category->name }}
                                    </span>

                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.7"
                                        class="h-4 w-4 shrink-0 text-neutral-500 transition-transform duration-200 group-open:rotate-90"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M9 5l7 7-7 7"
                                        />
                                    </svg>

                                </summary>

                                {{-- Child Categories --}}
                                <div class="pb-2 pl-5 pr-2 pt-1">

                                    <a
                                        href="{{ route('storefront.categories.show', $category) }}"
                                        class="block rounded-md px-3 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 hover:text-neutral-950"
                                    >
                                        View All {{ $category->name }}
                                    </a>

                                    @foreach ($category->activeChildren as $child)

                                        <a
                                            href="{{ route('storefront.categories.show', $child) }}"
                                            class="block rounded-md px-3 py-2 text-sm font-normal text-neutral-500 transition hover:bg-neutral-50 hover:text-neutral-950"
                                        >
                                            {{ $child->name }}
                                        </a>

                                    @endforeach

                                </div>

                            </details>

                        @else

                            {{-- Category Without Children --}}
                            <a
                                href="{{ route('storefront.categories.show', $category) }}"
                                class="flex items-center justify-between rounded-lg px-3 py-3 text-[15px] font-medium text-neutral-900 transition hover:bg-neutral-50"
                            >

                                <span class="min-w-0 truncate">
                                    {{ $category->name }}
                                </span>

                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                    class="h-4 w-4 shrink-0 text-neutral-500"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M9 5l7 7-7 7"
                                    />
                                </svg>

                            </a>

                        @endif

                    @empty

                        <p class="px-3 py-3 text-sm text-neutral-500">
                            Categories are being prepared.
                        </p>

                    @endforelse

                </div>

            </div>

        </aside>

    </div>

    {{-- ========================================================= --}}
    {{-- MOBILE MENU --}}
    {{-- ========================================================= --}}
    <div
        class="fixed inset-0 z-50 hidden bg-neutral-950/40 lg:hidden"
        data-mobile-menu
    >

        <div
            class="flex h-full w-[88%] max-w-sm flex-col bg-white shadow-xl"
        >

            {{-- Mobile Menu Header --}}
            <div
                class="flex h-16 shrink-0 items-center justify-between border-b border-neutral-200 px-4"
            >

                <a
                    href="{{ route('home') }}"
                    class="text-base font-semibold text-neutral-950"
                >
                    {{ config('marketplace.name') }}
                </a>

                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-neutral-200 text-neutral-700"
                    data-mobile-menu-close
                    aria-label="Close navigation"
                >
                    <svg
                        aria-hidden="true"
                        class="h-5 w-5"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                    >
                        <path
                            d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"
                        />
                    </svg>
                </button>

            </div>

            {{-- Mobile Nav --}}
            <nav
                class="flex-1 overflow-y-auto px-4 py-5"
                aria-label="Mobile navigation"
            >

                <div class="pt-5">

                    <div class="mb-3 px-3">

                        <p
                            class="text-xs font-semibold uppercase tracking-wider text-neutral-500"
                        >
                            Categories
                        </p>

                    </div>

                    <div class="space-y-1">

                        @forelse ($categories as $category)

                            @if ($category->activeChildren->isNotEmpty())

                                <details class="group">

                                    <summary
                                        class="flex cursor-pointer list-none items-center justify-between rounded-lg px-3 py-3 text-sm font-medium text-neutral-900 transition hover:bg-neutral-50"
                                    >

                                        <span>
                                            {{ $category->name }}
                                        </span>

                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.5"
                                            stroke="currentColor"
                                            class="h-4 w-4 text-neutral-400 transition-transform group-open:rotate-180"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M19.5 8.25L12 15.75 4.5 8.25"
                                            />
                                        </svg>

                                    </summary>

                                    <div class="ml-3 border-l border-neutral-200 py-1 pl-4">

                                        <a
                                            href="{{ route('storefront.categories.show', $category) }}"
                                            class="block rounded-md px-2 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 hover:text-neutral-950"
                                        >
                                            View All {{ $category->name }}
                                        </a>

                                        @foreach ($category->activeChildren as $child)

                                            <a
                                                href="{{ route('storefront.categories.show', $child) }}"
                                                class="block rounded-md px-2 py-2 text-sm text-neutral-500 transition hover:bg-neutral-50 hover:text-neutral-950"
                                            >
                                                {{ $child->name }}
                                            </a>

                                        @endforeach

                                    </div>

                                </details>

                            @else

                                <a
                                    href="{{ route('storefront.categories.show', $category) }}"
                                    class="flex items-center justify-between rounded-lg px-3 py-3 text-sm font-medium text-neutral-900 transition hover:bg-neutral-50"
                                >

                                    <span>
                                        {{ $category->name }}
                                    </span>

                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke-width="1.5"
                                        stroke="currentColor"
                                        class="h-4 w-4 text-neutral-400"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M8.25 4.5l7.5 7.5-7.5 7.5"
                                        />
                                    </svg>

                                </a>

                            @endif

                        @empty

                            <p class="px-3 py-3 text-sm text-neutral-500">
                                Categories are being prepared.
                            </p>

                        @endforelse

                    </div>

                </div>

            </nav>

        </div>

    </div>

</header>


@pushOnce('scripts')

<script>
    document.addEventListener('DOMContentLoaded', () => {

        /*
        |--------------------------------------------------------------------------
        | Mobile Menu
        |--------------------------------------------------------------------------
        */

        const mobileMenu = document.querySelector('[data-mobile-menu]');
        const mobileOpen = document.querySelector('[data-mobile-menu-open]');
        const mobileClose = document.querySelector('[data-mobile-menu-close]');

        const openMobileMenu = () => {

            if (!mobileMenu) {
                return;
            }

            mobileMenu.classList.remove('hidden');

            document.body.classList.add('overflow-hidden');

        };

        const closeMobileMenu = () => {

            if (!mobileMenu) {
                return;
            }

            mobileMenu.classList.add('hidden');

            document.body.classList.remove('overflow-hidden');

        };

        mobileOpen?.addEventListener('click', openMobileMenu);

        mobileClose?.addEventListener('click', closeMobileMenu);

        mobileMenu?.addEventListener('click', (event) => {

            if (event.target === mobileMenu) {
                closeMobileMenu();
            }

        });


        /*
        |--------------------------------------------------------------------------
        | Desktop Category Menu
        |--------------------------------------------------------------------------
        */

        const desktopMenu = document.querySelector('[data-desktop-menu]');
        const desktopPanel = document.querySelector('[data-desktop-menu-panel]');
        const desktopOverlay = document.querySelector('[data-desktop-menu-overlay]');
        const desktopOpen = document.querySelector('[data-desktop-menu-open]');
        const desktopClose = document.querySelector('[data-desktop-menu-close]');

        let desktopMenuClosingTimer = null;


        /*
        |--------------------------------------------------------------------------
        | Lock Desktop Page Scroll Without Layout Shift
        |--------------------------------------------------------------------------
        */

        const lockDesktopPageScroll = () => {

            const scrollbarWidth =
                window.innerWidth - document.documentElement.clientWidth;

            if (scrollbarWidth > 0) {
                document.body.style.paddingRight = `${scrollbarWidth}px`;
            }

            document.body.classList.add('overflow-hidden');

        };


        /*
        |--------------------------------------------------------------------------
        | Unlock Desktop Page Scroll
        |--------------------------------------------------------------------------
        */

        const unlockDesktopPageScroll = () => {

            document.body.classList.remove('overflow-hidden');

            document.body.style.paddingRight = '';

        };


        /*
        |--------------------------------------------------------------------------
        | Open Desktop Menu
        |--------------------------------------------------------------------------
        */

        const openDesktopMenu = () => {

            if (!desktopMenu || !desktopPanel || !desktopOverlay) {
                return;
            }

            if (desktopMenuClosingTimer) {

                clearTimeout(desktopMenuClosingTimer);

                desktopMenuClosingTimer = null;

            }

            /*
             * Lock scrolling while keeping the exact
             * same website width.
             */
            lockDesktopPageScroll();

            /*
             * Make the drawer wrapper available.
             */
            desktopMenu.classList.remove('invisible');

            /*
             * Wait until the browser paints the initial
             * translated position before starting animation.
             */
            requestAnimationFrame(() => {

                requestAnimationFrame(() => {

                    desktopPanel.classList.remove('-translate-x-full');

                    desktopOverlay.classList.remove('opacity-0');

                });

            });

        };


        /*
        |--------------------------------------------------------------------------
        | Close Desktop Menu
        |--------------------------------------------------------------------------
        */

        const closeDesktopMenu = () => {

            if (!desktopMenu || !desktopPanel || !desktopOverlay) {
                return;
            }

            desktopPanel.classList.add('-translate-x-full');

            desktopOverlay.classList.add('opacity-0');

            desktopMenuClosingTimer = setTimeout(() => {

                desktopMenu.classList.add('invisible');

                unlockDesktopPageScroll();

                desktopMenuClosingTimer = null;

            }, 300);

        };


        desktopOpen?.addEventListener('click', openDesktopMenu);

        desktopClose?.addEventListener('click', closeDesktopMenu);

        desktopOverlay?.addEventListener('click', closeDesktopMenu);


        /*
        |--------------------------------------------------------------------------
        | Escape Key
        |--------------------------------------------------------------------------
        */

        document.addEventListener('keydown', (event) => {

            if (event.key !== 'Escape') {
                return;
            }

            closeMobileMenu();

            closeDesktopMenu();

        });

    });
</script>

@endPushOnce
