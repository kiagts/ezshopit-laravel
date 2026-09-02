@extends('layouts.app')

@php
    use App\Support\Money;

    $paymentMethod = $order->payment?->method->label() ?? 'Payment pending';

    $paymentStatus = (
        $order->payment?->status
        ?? $order->payment_status
    )->label();
@endphp

@section('title', 'Order Placed')

@section('content')

    <section class="bg-neutral-50">

        <x-container class="py-10 sm:py-12 lg:py-14">

            {{-- ========================================================= --}}
            {{-- ORDER CONFIRMATION --}}
            {{-- ========================================================= --}}

            <div
                class="mx-auto grid max-w-6xl gap-8 lg:grid-cols-[0.85fr_1.15fr] lg:items-center"
            >

                {{-- ===================================================== --}}
                {{-- LEFT: SUCCESS MESSAGE --}}
                {{-- ===================================================== --}}

                <div>

                    {{-- Success Illustration --}}
                    <div
                        class="relative mx-auto flex h-40 w-40 items-center justify-center sm:mx-0"
                    >

                        <div
                            class="absolute inset-4 rounded-full bg-emerald-100/70"
                        ></div>

                        <div
                            class="relative flex h-24 w-24 items-center justify-center rounded-3xl bg-emerald-800 text-white shadow-lg"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                class="h-12 w-12"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M6 8h12l1 12H5L6 8Z"
                                />

                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M9 8V6a3 3 0 0 1 6 0v2"
                                />

                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="m8.5 14 2 2 5-5"
                                />
                            </svg>
                        </div>


                        {{-- Decorative Dots --}}
                        <span
                            class="absolute left-2 top-8 h-2 w-2 rounded-full bg-emerald-300"
                            aria-hidden="true"
                        ></span>

                        <span
                            class="absolute right-3 top-6 h-2.5 w-2.5 rounded-full bg-amber-300"
                            aria-hidden="true"
                        ></span>

                        <span
                            class="absolute bottom-6 left-6 h-2 w-2 rounded-full bg-emerald-200"
                            aria-hidden="true"
                        ></span>

                        <span
                            class="absolute bottom-4 right-6 h-2 w-2 rounded-full bg-emerald-400"
                            aria-hidden="true"
                        ></span>

                    </div>


                    {{-- Success Badge --}}
                    <div
                        class="mt-2 inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            class="h-4 w-4"
                            aria-hidden="true"
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

                        Order placed
                    </div>


                    <h1
                        class="mt-5 max-w-xl text-3xl font-semibold leading-tight tracking-normal text-neutral-950 sm:text-4xl"
                    >
                        Thank you! Your order has been placed.
                    </h1>


                    <p
                        class="mt-4 max-w-lg text-sm leading-7 text-neutral-600 sm:text-base"
                    >
                        We’ve received your order and are getting it ready.
                        You can track its progress from your account.
                    </p>

                </div>


                {{-- ===================================================== --}}
                {{-- RIGHT: ORDER DETAILS --}}
                {{-- ===================================================== --}}

                <div
                    class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm sm:p-6 lg:p-7"
                >

                    {{-- ================================================= --}}
                    {{-- ORDER SUMMARY --}}
                    {{-- ================================================= --}}

                    <div
                        class="grid gap-5 sm:grid-cols-3"
                    >

                        {{-- Order --}}
                        <div
                            class="flex items-start gap-3 sm:block"
                        >
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-700"
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

                            <div class="sm:mt-3">
                                <p
                                    class="text-xs font-medium text-neutral-500"
                                >
                                    Order ID
                                </p>

                                <p
                                    class="mt-1 break-all text-sm font-semibold text-neutral-950"
                                >
                                    {{ $order->order_number }}
                                </p>
                            </div>
                        </div>


                        {{-- Sellers --}}
                        <div
                            class="flex items-start gap-3 border-neutral-100 sm:block sm:border-l sm:pl-5"
                        >
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-700"
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

                            <div class="sm:mt-3">
                                <p
                                    class="text-xs font-medium text-neutral-500"
                                >
                                    Sellers
                                </p>

                                <p
                                    class="mt-1 text-sm font-semibold text-neutral-950"
                                >
                                    {{ $order->sellerCount() }}
                                </p>
                            </div>
                        </div>


                        {{-- Total --}}
                        <div
                            class="flex items-start gap-3 border-neutral-100 sm:block sm:border-l sm:pl-5"
                        >
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-700"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                    class="h-5 w-5"
                                >
                                    <rect
                                        x="4"
                                        y="6"
                                        width="16"
                                        height="12"
                                        rx="2"
                                    />

                                    <path
                                        stroke-linecap="round"
                                        d="M15 12h3"
                                    />
                                </svg>
                            </div>

                            <div class="sm:mt-3">
                                <p
                                    class="text-xs font-medium text-neutral-500"
                                >
                                    Total Amount
                                </p>

                                <p
                                    class="mt-1 text-base font-semibold text-emerald-800"
                                >
                                    {{ Money::format($order->grand_total) }}
                                </p>
                            </div>
                        </div>

                    </div>


                    {{-- ================================================= --}}
                    {{-- DIVIDER --}}
                    {{-- ================================================= --}}

                    <div
                        class="my-6 border-t border-neutral-200"
                    ></div>


                    {{-- ================================================= --}}
                    {{-- PAYMENT --}}
                    {{-- ================================================= --}}

                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                    >

                        <div
                            class="flex items-center gap-3"
                        >

                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-neutral-100 text-neutral-700"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                    class="h-5 w-5"
                                >
                                    <rect
                                        x="3"
                                        y="5"
                                        width="18"
                                        height="14"
                                        rx="2"
                                    />

                                    <path
                                        stroke-linecap="round"
                                        d="M3 9h18M7 15h4"
                                    />
                                </svg>
                            </div>


                            <div>

                                <p
                                    class="text-sm font-semibold text-neutral-950"
                                >
                                    Payment Method
                                </p>

                                <p
                                    class="mt-1 text-sm text-neutral-600"
                                >
                                    {{ $paymentMethod }}
                                    ·
                                    {{ $paymentStatus }}
                                </p>

                            </div>

                        </div>


                        <span
                            class="inline-flex w-fit items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700"
                        >
                            {{ $paymentStatus }}
                        </span>

                    </div>


                    {{-- ================================================= --}}
                    {{-- WHAT HAPPENS NEXT --}}
                    {{-- ================================================= --}}

                    <div
                        class="mt-5 flex items-start gap-4 rounded-xl bg-neutral-50 p-4"
                    >

                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-700"
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
                                    stroke-linejoin="round"
                                    d="M3 7h11v9H3zM14 10h3l4 4v2h-7z"
                                />

                                <circle
                                    cx="7"
                                    cy="18"
                                    r="1.5"
                                />

                                <circle
                                    cx="18"
                                    cy="18"
                                    r="1.5"
                                />
                            </svg>
                        </div>


                        <div class="min-w-0">

                            <p
                                class="text-sm font-semibold text-neutral-950"
                            >
                                What happens next?
                            </p>

                            <p
                                class="mt-1 text-sm leading-6 text-neutral-600"
                            >
                                We’ll prepare your order and update its status
                                as it moves through fulfillment. You can track
                                everything from your account.
                            </p>

                        </div>

                    </div>


                    {{-- ================================================= --}}
                    {{-- ACTIONS --}}
                    {{-- ================================================= --}}

                    <div
                        class="mt-6 flex flex-col gap-3 sm:flex-row"
                    >

                        <a
                            href="{{ route('account.orders.show', $order) }}"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-brand-700 px-5 text-sm font-semibold text-white transition hover:bg-brand-800"
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
                                    d="M6.75 7.5h10.5l1 12H5.75l1-12Z"
                                />

                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M9 8V6a3 3 0 0 1 6 0v2"
                                />
                            </svg>

                            View Order
                        </a>


                        <a
                            href="{{ route('home') }}"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-neutral-300 bg-white px-5 text-sm font-semibold text-neutral-800 transition hover:border-brand-700 hover:text-brand-700"
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
                                    d="M4 10h16M5 10l1-5h12l1 5M6 10v9h12v-9"
                                />
                            </svg>

                            Continue Shopping
                        </a>

                    </div>

                </div>

            </div>


            {{-- ========================================================= --}}
            {{-- TRUST STRIP --}}
            {{-- ========================================================= --}}

            <div
                class="mx-auto mt-8 grid max-w-6xl overflow-hidden rounded-2xl border border-neutral-200 bg-white sm:grid-cols-2 lg:grid-cols-4"
            >

                {{-- Secure Checkout --}}
                <div
                    class="flex items-center gap-3 border-b border-neutral-100 p-4 sm:border-r lg:border-b-0"
                >

                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-700"
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
                                stroke-linejoin="round"
                                d="M12 3 19 6v5c0 4.6-2.9 8.4-7 10-4.1-1.6-7-5.4-7-10V6l7-3Z"
                            />

                            <path
                                stroke-linecap="round"
                                d="m9 12 2 2 4-5"
                            />
                        </svg>
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-neutral-950">
                            Secure Checkout
                        </p>

                        <p class="mt-0.5 text-xs text-neutral-500">
                            Your data is protected
                        </p>
                    </div>

                </div>

                {{-- Easy Returns --}}
                <div
                    class="flex items-center gap-3 border-b border-neutral-100 p-4 lg:border-b-0 lg:border-r"
                >
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-700"
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
                        <p class="text-sm font-semibold text-neutral-950">
                            Easy Returns
                        </p>
                        <p class="mt-0.5 text-xs text-neutral-500">
                            Simple return process
                        </p>
                    </div>
                </div>

                {{-- Customer Support --}}
                <div
                    class="flex items-center gap-3 border-b border-neutral-100 p-4 sm:border-b-0 sm:border-r"
                >
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-700"
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
                                d="M4 13v-2a8 8 0 0 1 16 0v2"
                            />

                            <path
                                stroke-linecap="round"
                                d="M4 13h3v5H5a1 1 0 0 1-1-1v-4Zm16 0h-3v5h2a1 1 0 0 0 1-1v-4Z"
                            />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-neutral-950">
                            Customer Support
                        </p>

                        <p class="mt-0.5 text-xs text-neutral-500">
                            We’re here to help
                        </p>
                    </div>
                </div>

                {{-- Trusted Marketplace --}}
                <div
                    class="flex items-center gap-3 p-4"
                >
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-700"
                    >
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
                                cy="9"
                                r="4"
                            />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="m9.5 13-1 7 3.5-2 3.5 2-1-7"
                            />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-neutral-950">
                            Trusted Marketplace
                        </p>
                        <p class="mt-0.5 text-xs text-neutral-500">
                            Shop with confidence
                        </p>
                    </div>
                </div>
            </div>
        </x-container>
    </section>
@endsection