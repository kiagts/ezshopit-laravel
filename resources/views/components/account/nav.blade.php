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
    $unreadNotificationsCount = $user->unreadNotifications()->count();
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
        class="bg-neutral-950 px-5 py-5"
    >
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
            {{-- NOTIFICATIONS --}}
            {{-- ================================================= --}}
            <a
                href="{{ route('account.notifications.index') }}"
                @class([
                    'group flex h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium transition',
                    'bg-emerald-50 text-emerald-800' => $active === 'notifications',
                    'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-950' => $active !== 'notifications',
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
                        d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"
                    />

                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M13.73 21a2 2 0 0 1-3.46 0"
                    />
                </svg>

                <span class="min-w-0 flex-1">
                    Notifications
                </span>

                @if ($unreadNotificationsCount > 0)
                    <span class="rounded-full bg-emerald-700 px-2 py-0.5 text-[11px] font-semibold text-white">
                        {{ $unreadNotificationsCount > 99 ? '99+' : $unreadNotificationsCount }}
                    </span>
                @endif
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

            <a
                href="{{ route('account.profile.edit') }}"
                @class([
                    'group flex h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium transition',
                    'bg-emerald-50 text-emerald-800' => $active === 'profile',
                    'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-950' => $active !== 'profile',
                ])
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="h-5 w-5 shrink-0" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 20.25a7.5 7.5 0 0 1 15 0" />
                </svg>

                <span>Profile</span>
            </a>

            <a
                href="{{ route('account.security.edit') }}"
                @class([
                    'group flex h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium transition',
                    'bg-emerald-50 text-emerald-800' => $active === 'security',
                    'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-950' => $active !== 'security',
                ])
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="h-5 w-5 shrink-0" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75 19.25 7v5.5c0 4.25-3 7.25-7.25 8.75-4.25-1.5-7.25-4.5-7.25-8.75V7L12 3.75Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 12.25 1.5 1.5 3.25-3.5" />
                </svg>

                <span>Security</span>
            </a>

            <a href="{{ route('account.wallet') }}" class="block rounded px-4 py-3 text-sm font-medium hover:bg-neutral-100">Wallet</a>
            <a href="{{ route('account.gift-cards.index') }}" class="block rounded px-4 py-3 text-sm font-medium hover:bg-neutral-100">Gift Cards</a>
            <a href="{{ route('account.returns.index') }}" class="block rounded px-4 py-3 text-sm font-medium hover:bg-neutral-100">Returns</a>
            <a href="{{ route('account.disputes.index') }}" class="block rounded px-4 py-3 text-sm font-medium hover:bg-neutral-100">Disputes</a>
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
