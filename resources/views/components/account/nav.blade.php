@props([
    'active' => 'account',
])

@php
    $user = auth()->user();

    $initials = collect(explode(' ', trim($user->name ?? '')))
        ->filter()
        ->take(2)
        ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
        ->implode('');
@endphp

<aside
    {{ $attributes->class([
        'overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm',
    ]) }}
>
    {{-- ========================================================= --}}
    {{-- CUSTOMER PROFILE --}}
    {{-- ========================================================= --}}
    <div
        class="relative overflow-hidden bg-gradient-to-br from-emerald-950 via-emerald-900 to-emerald-700 px-5 py-5"
    >
        {{-- Decorative Background --}}
        <div
            class="absolute -right-8 -top-10 h-28 w-28 rounded-full bg-white/5"
            aria-hidden="true"
        ></div>

        <div
            class="absolute -bottom-12 right-10 h-24 w-24 rounded-full bg-emerald-400/10"
            aria-hidden="true"
        ></div>

        <div class="relative z-10 flex items-center gap-3.5">

            {{-- Avatar --}}
            <div
                class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full border-2 border-white/70 bg-white text-base font-semibold text-emerald-900 shadow-sm"
            >
                {{ $initials ?: 'U' }}
            </div>

            {{-- Customer Info --}}
            <div class="min-w-0">

                <p
                    class="truncate text-base font-semibold text-white"
                >
                    {{ $user->name }}
                </p>

                <p
                    class="mt-1 truncate text-xs text-white/75"
                    title="{{ $user->email }}"
                >
                    {{ $user->email }}
                </p>

            </div>

        </div>
    </div>


    {{-- ========================================================= --}}
    {{-- NAVIGATION --}}
    {{-- ========================================================= --}}
    <div class="p-4">

        <p
            class="px-2 pb-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-neutral-400"
        >
            Account
        </p>

        <nav
            class="space-y-1"
            aria-label="Account navigation"
        >

            {{-- ================================================= --}}
            {{-- ACCOUNT --}}
            {{-- ================================================= --}}
            <a
                href="{{ route('account.dashboard') }}"
                @class([
                    'group flex h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium transition',
                    'bg-emerald-50 text-emerald-800' => $active === 'account',
                    'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-950' => $active !== 'account',
                ])
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.7"
                    class="h-5 w-5 shrink-0"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M3.75 10.5 12 3.75l8.25 6.75"
                    />

                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M5.25 9.5v10.25h13.5V9.5"
                    />

                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M9.25 19.75v-6h5.5v6"
                    />
                </svg>

                <span>
                    Account
                </span>
            </a>


            {{-- ================================================= --}}
            {{-- ADDRESSES --}}
            {{-- ================================================= --}}
            <a
                href="{{ route('account.addresses.index') }}"
                @class([
                    'group flex h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium transition',
                    'bg-emerald-50 text-emerald-800' => $active === 'addresses',
                    'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-950' => $active !== 'addresses',
                ])
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.7"
                    class="h-5 w-5 shrink-0"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"
                    />

                    <circle
                        cx="12"
                        cy="10"
                        r="2.5"
                    />
                </svg>

                <span>
                    Addresses
                </span>
            </a>


            {{-- ================================================= --}}
            {{-- ORDERS --}}
            {{-- ================================================= --}}
            <a
                href="{{ route('account.orders.index') }}"
                @class([
                    'group flex h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium transition',
                    'bg-emerald-50 text-emerald-800' => $active === 'orders',
                    'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-950' => $active !== 'orders',
                ])
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.7"
                    class="h-5 w-5 shrink-0"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M6.75 7.5h10.5l1 12H5.75l1-12Z"
                    />

                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M9 8V6a3 3 0 0 1 6 0v2"
                    />
                </svg>

                <span>
                    Orders
                </span>
            </a>


            {{-- ================================================= --}}
            {{-- REVIEWS --}}
            {{-- ================================================= --}}
            <a
                href="{{ route('account.reviews.index') }}"
                @class([
                    'group flex h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium transition',
                    'bg-emerald-50 text-emerald-800' => $active === 'reviews',
                    'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-950' => $active !== 'reviews',
                ])
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.7"
                    class="h-5 w-5 shrink-0"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 3.75l2.45 4.96 5.48.8-3.96 3.86.94 5.45L12 16.24l-4.9 2.58.93-5.45-3.96-3.86 5.48-.8L12 3.75Z"
                    />
                </svg>

                <span>
                    Reviews
                </span>
            </a>


            {{-- ================================================= --}}
            {{-- WISHLIST --}}
            {{-- ================================================= --}}
            <a
                href="{{ route('account.wishlist.index') }}"
                @class([
                    'group flex h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium transition',
                    'bg-emerald-50 text-emerald-800' => $active === 'wishlist',
                    'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-950' => $active !== 'wishlist',
                ])
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.7"
                    class="h-5 w-5 shrink-0"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78Z"
                    />
                </svg>

                <span>
                    Wishlist
                </span>
            </a>

        </nav>


        {{-- ========================================================= --}}
        {{-- DIVIDER --}}
        {{-- ========================================================= --}}
        <div class="my-4 border-t border-neutral-100"></div>


        {{-- ========================================================= --}}
        {{-- LOGOUT --}}
        {{-- ========================================================= --}}
        <form
            method="POST"
            action="{{ route('logout') }}"
        >
            @csrf

            <button
                type="submit"
                class="group flex h-11 w-full items-center gap-3 rounded-lg px-3 text-left text-sm font-medium text-neutral-600 transition hover:bg-red-50 hover:text-red-700"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.7"
                    class="h-5 w-5 shrink-0"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M14.25 8.75V5.5A1.75 1.75 0 0 0 12.5 3.75h-6A1.75 1.75 0 0 0 4.75 5.5v13A1.75 1.75 0 0 0 6.5 20.25h6a1.75 1.75 0 0 0 1.75-1.75v-3.25"
                    />

                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M10 12h9.25M16 8.75 19.25 12 16 15.25"
                    />
                </svg>

                <span>
                    Logout
                </span>
            </button>
        </form>

    </div>
</aside>
