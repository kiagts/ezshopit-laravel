@extends('layouts.app')

@section('title', 'Account')

@section('content')

    <section class="bg-neutral-50">
        <x-container class="py-8 sm:py-10 lg:py-12">

            <div
                class="grid gap-6 lg:grid-cols-[18rem_minmax(0,1fr)] lg:items-start"
            >

                {{-- ===================================================== --}}
                {{-- ACCOUNT SIDEBAR --}}
                {{-- ===================================================== --}}
                <x-account.nav active="account" />


                {{-- ===================================================== --}}
                {{-- MAIN DASHBOARD --}}
                {{-- ===================================================== --}}
                <section class="min-w-0">

                    {{-- ================================================= --}}
                    {{-- WELCOME --}}
                    {{-- ================================================= --}}
                    <div
                        class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
                    >

                        <div>
                            <h1
                                class="text-3xl font-semibold tracking-normal text-neutral-950"
                            >
                                Hi, {{ strtok($user->name, ' ') }} 👋
                            </h1>

                            <p
                                class="mt-2 text-sm leading-6 text-neutral-600"
                            >
                                Welcome back. Here’s what’s happening with your account.
                            </p>
                        </div>

                    </div>


                    {{-- ================================================= --}}
                    {{-- QUICK SUMMARY --}}
                    {{-- ================================================= --}}
                    <div
                        class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
                    >

                        {{-- Orders --}}
                        <a
                            href="{{ route('account.orders.index') }}"
                            class="group rounded-xl border border-neutral-200 bg-white p-5 shadow-sm transition hover:border-brand-200 hover:shadow-md"
                        >
                            <div
                                class="flex items-start justify-between gap-4"
                            >
                                <div
                                    class="flex items-start gap-3"
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
                                    </div>

                                    <div>
                                        <p
                                            class="text-sm font-medium text-neutral-500"
                                        >
                                            Orders
                                        </p>

                                        <p
                                            class="mt-1 text-2xl font-semibold text-neutral-950"
                                        >
                                            {{ $ordersCount ?? 0 }}
                                        </p>

                                        <p
                                            class="mt-1 text-xs text-neutral-500"
                                        >
                                            View your recent orders
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-neutral-200 text-neutral-500 transition group-hover:border-brand-300 group-hover:text-brand-700"
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
                                            d="M5 12h14M13 6l6 6-6 6"
                                        />
                                    </svg>
                                </div>
                            </div>
                        </a>


                        {{-- Wishlist --}}
                        <a
                            href="{{ route('account.wishlist.index') }}"
                            class="group rounded-xl border border-neutral-200 bg-white p-5 shadow-sm transition hover:border-brand-200 hover:shadow-md"
                        >
                            <div
                                class="flex items-start justify-between gap-4"
                            >
                                <div
                                    class="flex items-start gap-3"
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
                                            aria-hidden="true"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78Z"
                                            />
                                        </svg>
                                    </div>

                                    <div>
                                        <p
                                            class="text-sm font-medium text-neutral-500"
                                        >
                                            Wishlist
                                        </p>

                                        <p
                                            class="mt-1 text-2xl font-semibold text-neutral-950"
                                        >
                                            {{ $wishlistCount ?? 0 }}
                                        </p>

                                        <p
                                            class="mt-1 text-xs text-neutral-500"
                                        >
                                            Items saved for later
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-neutral-200 text-neutral-500 transition group-hover:border-brand-300 group-hover:text-brand-700"
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
                                            d="M5 12h14M13 6l6 6-6 6"
                                        />
                                    </svg>
                                </div>
                            </div>
                        </a>


                        {{-- Addresses --}}
                        <a
                            href="{{ route('account.addresses.index') }}"
                            class="group rounded-xl border border-neutral-200 bg-white p-5 shadow-sm transition hover:border-brand-200 hover:shadow-md sm:col-span-2 xl:col-span-1"
                        >
                            <div
                                class="flex items-start justify-between gap-4"
                            >
                                <div
                                    class="flex items-start gap-3"
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
                                    </div>

                                    <div>
                                        <p
                                            class="text-sm font-medium text-neutral-500"
                                        >
                                            Saved Addresses
                                        </p>

                                        <p
                                            class="mt-1 text-2xl font-semibold text-neutral-950"
                                        >
                                            {{ $addressesCount ?? 0 }}
                                        </p>

                                        <p
                                            class="mt-1 text-xs text-neutral-500"
                                        >
                                            Manage your addresses
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-neutral-200 text-neutral-500 transition group-hover:border-brand-300 group-hover:text-brand-700"
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
                                            d="M5 12h14M13 6l6 6-6 6"
                                        />
                                    </svg>
                                </div>
                            </div>
                        </a>

                    </div>


                    {{-- ================================================= --}}
                    {{-- RECENT ORDER --}}
                    {{-- ================================================= --}}
                    <div
                        class="mt-5 rounded-xl border border-neutral-200 bg-white p-5 shadow-sm sm:p-6"
                    >
                        <div
                            class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between"
                        >
                            <div
                                class="flex items-start gap-4"
                            >
                                <div
                                    class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.7"
                                        class="h-6 w-6"
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
                                </div>

                                <div>
                                    <p
                                        class="text-lg font-semibold text-neutral-950"
                                    >
                                        Recent Order
                                    </p>

                                    @if (!empty($recentOrder))
                                        <div
                                            class="mt-1 flex flex-wrap items-center gap-2"
                                        >
                                            <span
                                                class="text-sm font-semibold text-brand-700"
                                            >
                                                Order #{{ $recentOrder->id }}
                                            </span>

                                            @if (!empty($recentOrder->status))
                                                <span
                                                    class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold capitalize text-emerald-700"
                                                >
                                                    {{ str_replace('_', ' ', $recentOrder->status) }}
                                                </span>
                                            @endif
                                        </div>

                                        <p
                                            class="mt-2 text-sm text-neutral-500"
                                        >
                                            Placed on
                                            {{ $recentOrder->created_at->format('M d, Y') }}
                                        </p>
                                    @else
                                        <p
                                            class="mt-1 text-sm text-neutral-500"
                                        >
                                            You have not placed any orders yet.
                                        </p>
                                    @endif
                                </div>
                            </div>

                            <a
                                href="{{ route('account.orders.index') }}"
                                class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-md border border-brand-700 bg-white px-4 text-sm font-semibold text-brand-700 transition hover:bg-brand-50"
                            >
                                {{ !empty($recentOrder) ? 'View Order Details' : 'View Orders' }}

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
                        </div>
                    </div>


                    {{-- ================================================= --}}
                    {{-- DEFAULT DELIVERY ADDRESS --}}
                    {{-- ================================================= --}}
                    <div
                        class="mt-5 rounded-xl border border-neutral-200 bg-white p-5 shadow-sm sm:p-6"
                    >
                        <div
                            class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between"
                        >
                            <div
                                class="flex items-start gap-4"
                            >
                                <div
                                    class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.7"
                                        class="h-6 w-6"
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
                                </div>

                                <div>
                                    <p
                                        class="text-lg font-semibold text-neutral-950"
                                    >
                                        Default Delivery Address
                                    </p>

                                    @if (!empty($defaultAddress))
                                        <div
                                            class="mt-2 text-sm leading-6 text-neutral-600"
                                        >
                                            @if (!empty($defaultAddress->address_line_1))
                                                <p>
                                                    {{ $defaultAddress->address_line_1 }}
                                                </p>
                                            @endif

                                            @if (!empty($defaultAddress->address_line_2))
                                                <p>
                                                    {{ $defaultAddress->address_line_2 }}
                                                </p>
                                            @endif
                                            
                                            <p>
                                                {{ collect([
                                                    $defaultAddress->city ?? null,
                                                    $defaultAddress->state ?? null,
                                                    $defaultAddress->postal_code ?? null,
                                                ])->filter()->implode(', ') }}
                                            </p>

                                            @if (!empty($defaultAddress->phone))
                                                <p>
                                                    {{ $defaultAddress->phone }}
                                                </p>
                                            @endif
                                        </div>
                                    @else
                                        <p
                                            class="mt-1 text-sm text-neutral-500"
                                        >
                                            No default delivery address has been added yet.
                                        </p>
                                    @endif
                                </div>
                            </div>

                            <a
                                href="{{ route('account.addresses.index') }}"
                                class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-md border border-brand-700 bg-white px-4 text-sm font-semibold text-brand-700 transition hover:bg-brand-50"
                            >
                                Manage Addresses
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
                        </div>
                    </div>
                </section>
            </div>
        </x-container>
    </section>
@endsection