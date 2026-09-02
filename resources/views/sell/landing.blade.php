@extends('layouts.app')

@section('title', 'Sell on Marketplace')

@section('content')

    {{-- ========================================================= --}}
    {{-- HERO --}}
    {{-- ========================================================= --}}
    <section class="border-b border-neutral-100 bg-gradient-to-br from-white via-emerald-50/30 to-white">

        <x-container class="py-12 sm:py-16 lg:py-20">

            <div
                class="grid gap-10 lg:grid-cols-[1fr_0.95fr] lg:items-center"
            >

                {{-- ================================================= --}}
                {{-- LEFT CONTENT --}}
                {{-- ================================================= --}}
                <div>

                    <x-badge tone="brand">
                        Seller onboarding
                    </x-badge>

                    <h1
                        class="mt-5 max-w-2xl text-4xl font-semibold leading-[1.08] tracking-normal text-neutral-950 sm:text-5xl lg:text-[3.5rem]"
                    >
                        Build your marketplace presence with a
                        <span class="text-brand-700">
                            seller account.
                        </span>
                    </h1>

                    <p
                        class="mt-5 max-w-xl text-base leading-7 text-neutral-600 sm:text-lg sm:leading-8"
                    >
                        Create a vendor profile, submit your store application,
                        and get ready for the seller tools that will power
                        products, orders, customers, and earnings.
                    </p>


                    {{-- CTA --}}
                    <div
                        class="mt-8 flex flex-col gap-3 sm:flex-row"
                    >

                        <x-button
                            :href="route('vendor.register')"
                            size="lg"
                        >
                            Become a Seller

                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                class="ml-2 h-4 w-4"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M5 12h14M13 6l6 6-6 6"
                                />
                            </svg>
                        </x-button>

                        <x-button
                            :href="route('login')"
                            variant="secondary"
                            size="lg"
                        >
                            Sign In
                        </x-button>

                    </div>


                    {{-- Trust Points --}}
                    <div
                        class="mt-7 flex flex-wrap gap-x-6 gap-y-3"
                    >

                        @foreach ([
                            'No setup fee',
                            'Verified onboarding',
                            'Secure marketplace',
                        ] as $trust)

                            <div
                                class="flex items-center gap-2 text-xs font-medium text-neutral-600"
                            >
                                <span
                                    class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-50 text-emerald-700"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        class="h-3.5 w-3.5"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="m5 12 4 4L19 6"
                                        />
                                    </svg>
                                </span>

                                {{ $trust }}
                            </div>

                        @endforeach

                    </div>

                </div>


                {{-- ================================================= --}}
                {{-- RIGHT HERO VISUAL --}}
                {{-- ================================================= --}}
                <div
                    class="relative"
                >

                    {{-- Decorative Background --}}
                    <div
                        class="absolute inset-6 rounded-full bg-emerald-100/50 blur-3xl"
                        aria-hidden="true"
                    ></div>


                    {{-- Dashboard Illustration --}}
                    <div
                        class="relative overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-lg"
                    >

                        <div
                            class="border-b border-neutral-100 px-5 py-4"
                        >
                            <div
                                class="flex items-center gap-2"
                            >
                                <span class="h-2.5 w-2.5 rounded-full bg-red-400"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                            </div>
                        </div>


                        <div class="grid grid-cols-[110px_1fr]">

                            {{-- Mini Sidebar --}}
                            <div
                                class="border-r border-neutral-100 bg-neutral-50 p-4"
                            >
                                <div
                                    class="h-5 w-16 rounded bg-brand-100"
                                ></div>

                                <div class="mt-5 space-y-3">
                                    <div class="h-2.5 w-14 rounded bg-neutral-200"></div>
                                    <div class="h-2.5 w-12 rounded bg-neutral-200"></div>
                                    <div class="h-2.5 w-16 rounded bg-neutral-200"></div>
                                    <div class="h-2.5 w-10 rounded bg-neutral-200"></div>
                                </div>
                            </div>


                            {{-- Mini Dashboard --}}
                            <div class="p-5">

                                <div
                                    class="grid grid-cols-3 gap-3"
                                >
                                    @foreach ([
                                        ['label' => 'Revenue', 'value' => '$12.8K'],
                                        ['label' => 'Orders', 'value' => '86'],
                                        ['label' => 'Products', 'value' => '142'],
                                    ] as $stat)

                                        <div
                                            class="rounded-lg border border-neutral-100 bg-white p-3 shadow-sm"
                                        >
                                            <p
                                                class="text-[10px] text-neutral-400"
                                            >
                                                {{ $stat['label'] }}
                                            </p>

                                            <p
                                                class="mt-1 text-sm font-semibold text-neutral-900"
                                            >
                                                {{ $stat['value'] }}
                                            </p>

                                        </div>

                                    @endforeach
                                </div>


                                {{-- Fake Chart --}}
                                <div
                                    class="mt-4 rounded-lg border border-neutral-100 p-4"
                                >
                                    <div
                                        class="flex h-28 items-end gap-2"
                                    >
                                        @foreach ([30, 45, 40, 65, 55, 80, 68, 88, 72, 92] as $height)

                                            <div
                                                class="flex-1 rounded-t bg-emerald-100"
                                                style="height: {{ $height }}%"
                                            ></div>

                                        @endforeach
                                    </div>
                                </div>

                            </div>

                        </div>

                    </div>


                    {{-- Selling Steps Floating Card --}}
                    <div
                        class="relative mx-auto -mt-8 w-[90%] rounded-xl border border-neutral-200 bg-white p-5 shadow-xl sm:w-[82%]"
                    >

                        <h2
                            class="text-base font-semibold text-neutral-950"
                        >
                            How selling will work
                        </h2>

                        <div
                            class="mt-4 space-y-3"
                        >

                            @foreach ([
                                [
                                    'icon' => 'user',
                                    'text' => 'Create a secure seller account',
                                ],
                                [
                                    'icon' => 'document',
                                    'text' => 'Complete your store application',
                                ],
                                [
                                    'icon' => 'clock',
                                    'text' => 'Wait for admin review',
                                ],
                                [
                                    'icon' => 'dashboard',
                                    'text' => 'Access the seller dashboard after approval',
                                ],
                            ] as $step)

                                <div
                                    class="flex items-center gap-3"
                                >

                                    <span
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-700"
                                    >
                                        @if ($step['icon'] === 'user')

                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                                class="h-4 w-4"
                                            >
                                                <circle cx="12" cy="7" r="3" />
                                                <path
                                                    stroke-linecap="round"
                                                    d="M5 20c0-4 2.8-6 7-6s7 2 7 6"
                                                />
                                            </svg>

                                        @elseif ($step['icon'] === 'document')

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
                                                    d="M6 3h9l3 3v15H6zM9 11h6M9 15h6"
                                                />
                                            </svg>

                                        @elseif ($step['icon'] === 'clock')

                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                                class="h-4 w-4"
                                            >
                                                <circle cx="12" cy="12" r="8" />
                                                <path
                                                    stroke-linecap="round"
                                                    d="M12 8v4l3 2"
                                                />
                                            </svg>

                                        @else

                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                                class="h-4 w-4"
                                            >
                                                <rect
                                                    x="4"
                                                    y="5"
                                                    width="16"
                                                    height="14"
                                                    rx="2"
                                                />
                                                <path
                                                    stroke-linecap="round"
                                                    d="M8 10h8M8 14h5"
                                                />
                                            </svg>

                                        @endif
                                    </span>

                                    <p
                                        class="text-sm text-neutral-600"
                                    >
                                        {{ $step['text'] }}
                                    </p>

                                </div>

                            @endforeach

                        </div>

                    </div>

                </div>

            </div>

        </x-container>

    </section>


    {{-- ========================================================= --}}
    {{-- HOW IT WORKS --}}
    {{-- ========================================================= --}}
    <section class="bg-white py-12 sm:py-16">

        <x-container>

            <div class="text-center">

                <p
                    class="text-xs font-semibold uppercase tracking-wide text-brand-700"
                >
                    Simple onboarding
                </p>

                <h2
                    class="mt-2 text-2xl font-semibold text-neutral-950 sm:text-3xl"
                >
                    How selling on {{ config('marketplace.name') }} works
                </h2>

            </div>


            <div
                class="relative mt-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-4"
            >

                @foreach ([
                    [
                        'number' => '01',
                        'title' => 'Create Account',
                        'description' => 'Sign up and create your seller account in minutes.',
                    ],
                    [
                        'number' => '02',
                        'title' => 'Submit Application',
                        'description' => 'Complete your store details and submit for review.',
                    ],
                    [
                        'number' => '03',
                        'title' => 'Get Approved',
                        'description' => 'Our team reviews and verifies your application.',
                    ],
                    [
                        'number' => '04',
                        'title' => 'Start Selling',
                        'description' => 'Get access to seller tools and start your business.',
                    ],
                ] as $step)

                    <div
                        class="relative text-center"
                    >

                        <div
                            class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-emerald-700"
                        >

                            <span
                                class="text-lg font-semibold"
                            >
                                {{ $step['number'] }}
                            </span>

                        </div>

                        <h3
                            class="mt-4 text-base font-semibold text-neutral-950"
                        >
                            {{ $step['title'] }}
                        </h3>

                        <p
                            class="mx-auto mt-2 max-w-[220px] text-sm leading-6 text-neutral-500"
                        >
                            {{ $step['description'] }}
                        </p>

                    </div>

                @endforeach

            </div>

        </x-container>

    </section>


    {{-- ========================================================= --}}
    {{-- SELLER BENEFITS --}}
    {{-- ========================================================= --}}
    <section class="bg-neutral-50 py-12 sm:py-16">

        <x-container>

            <div class="text-center">

                <p
                    class="text-xs font-semibold uppercase tracking-wide text-brand-700"
                >
                    Everything you need to grow
                </p>

                <h2
                    class="mt-2 text-2xl font-semibold text-neutral-950 sm:text-3xl"
                >
                    Why sellers choose {{ config('marketplace.name') }}
                </h2>

            </div>


            @php
                $benefits = [
                    [
                        'title' => 'Reach More Customers',
                        'description' => 'Access a growing customer base already shopping across the marketplace.',
                        'icon' => 'users',
                    ],
                    [
                        'title' => 'Manage Products',
                        'description' => 'Add and manage products, variants, pricing, and inventory with ease.',
                        'icon' => 'box',
                    ],
                    [
                        'title' => 'Track Orders',
                        'description' => 'Manage orders and stay up to date with fulfilment and customer activity.',
                        'icon' => 'orders',
                    ],
                    [
                        'title' => 'Secure Payments',
                        'description' => 'Sell confidently with reliable and secure marketplace payment processing.',
                        'icon' => 'shield',
                    ],
                    [
                        'title' => 'Seller Analytics',
                        'description' => 'Monitor performance, sales, orders, products, and business activity.',
                        'icon' => 'chart',
                    ],
                    [
                        'title' => 'Marketplace Support',
                        'description' => 'Get support during onboarding and throughout your seller journey.',
                        'icon' => 'support',
                    ],
                ];
            @endphp


            <div
                class="mt-8 grid gap-4 md:grid-cols-2 lg:grid-cols-3"
            >

                @foreach ($benefits as $benefit)

                    <div
                        class="group flex gap-4 rounded-xl border border-neutral-200 bg-white p-5 transition hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-md"
                    >

                        <div
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-700"
                        >

                            @if ($benefit['icon'] === 'users')

                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    class="h-6 w-6"
                                >
                                    <circle cx="9" cy="8" r="3" />
                                    <path
                                        stroke-linecap="round"
                                        d="M3 20c0-4 2.4-6 6-6s6 2 6 6M16 6a3 3 0 0 1 0 6M17 14c2.7.4 4 2.1 4 5"
                                    />
                                </svg>

                            @elseif ($benefit['icon'] === 'box')

                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    class="h-6 w-6"
                                >
                                    <path
                                        stroke-linejoin="round"
                                        d="m4 7 8-4 8 4-8 4-8-4Zm0 0v10l8 4 8-4V7M12 11v10"
                                    />
                                </svg>

                            @elseif ($benefit['icon'] === 'orders')

                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    class="h-6 w-6"
                                >
                                    <rect x="5" y="4" width="14" height="16" rx="2" />
                                    <path
                                        stroke-linecap="round"
                                        d="M8 8h8M8 12h8M8 16h5"
                                    />
                                </svg>

                            @elseif ($benefit['icon'] === 'shield')

                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    class="h-6 w-6"
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

                            @elseif ($benefit['icon'] === 'chart')

                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    class="h-6 w-6"
                                >
                                    <path
                                        stroke-linecap="round"
                                        d="M5 19V9M12 19V5M19 19v-7"
                                    />
                                </svg>

                            @else

                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    class="h-6 w-6"
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

                            @endif

                        </div>


                        <div class="min-w-0">

                            <h3
                                class="text-base font-semibold text-neutral-950"
                            >
                                {{ $benefit['title'] }}
                            </h3>

                            <p
                                class="mt-2 text-sm leading-6 text-neutral-600"
                            >
                                {{ $benefit['description'] }}
                            </p>

                        </div>

                    </div>

                @endforeach

            </div>

        </x-container>

    </section>


    {{-- ========================================================= --}}
    {{-- DASHBOARD PREVIEW --}}
    {{-- ========================================================= --}}
    <section class="bg-white py-12 sm:py-16">

        <x-container>

            <div
                class="overflow-hidden rounded-2xl border border-neutral-200 bg-gradient-to-br from-white to-emerald-50/40"
            >

                <div
                    class="grid gap-8 p-6 lg:grid-cols-[1.15fr_0.85fr] lg:items-center lg:p-8"
                >

                    {{-- Dashboard Image --}}
                    <div class="relative">

                        {{-- Background decoration --}}
                        <div
                            class="absolute -inset-4 rounded-3xl bg-emerald-100/50 blur-2xl"
                            aria-hidden="true"
                        ></div>

                        {{-- Image container --}}
                        <div
                            class="relative overflow-hidden rounded-xl border border-neutral-200 bg-white p-2 shadow-xl"
                        >
                            <img
                                src="{{ Storage::url('demo/seller/seller-dashboard.png') }}"
                                alt="{{ config('marketplace.name') }} Seller Dashboard"
                                class="h-auto w-full rounded-lg object-cover"
                            >
                        </div>

                    </div>


                    {{-- Dashboard Features --}}
                    <div>

                        <p
                            class="text-xs font-semibold uppercase tracking-wide text-brand-700"
                        >
                            Seller tools
                        </p>

                        <h2
                            class="mt-2 text-2xl font-semibold text-neutral-950 sm:text-3xl"
                        >
                            Powerful seller dashboard
                        </h2>

                        <p
                            class="mt-4 text-sm leading-7 text-neutral-600"
                        >
                            Manage everything from one place and stay in control
                            of your marketplace business.
                        </p>


                        <div
                            class="mt-6 space-y-3"
                        >

                            @foreach ([
                                'Product and inventory management',
                                'Order processing and fulfilment',
                                'Sales analytics and reports',
                                'Customer management',
                                'Payout and transaction tracking',
                                'Store performance overview',
                            ] as $feature)

                                <div
                                    class="flex items-center gap-3"
                                >

                                    <span
                                        class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-50 text-emerald-700"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            class="h-3.5 w-3.5"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="m5 12 4 4L19 6"
                                            />
                                        </svg>
                                    </span>

                                    <span
                                        class="text-sm text-neutral-700"
                                    >
                                        {{ $feature }}
                                    </span>

                                </div>

                            @endforeach

                        </div>


                        <x-button
                            :href="route('vendor.register')"
                            class="mt-7"
                        >
                            Start Selling
                        </x-button>

                    </div>

                </div>

            </div>

        </x-container>

    </section>

    {{-- ========================================================= --}}
    {{-- ONBOARDING + FAQ --}}
    {{-- ========================================================= --}}
    <section class="bg-white py-12 sm:py-16">

        <x-container>

            <div
                class="grid gap-10 lg:grid-cols-2"
            >

                {{-- ================================================= --}}
                {{-- ONBOARDING TIMELINE --}}
                {{-- ================================================= --}}
                <div>

                    <h2
                        class="text-2xl font-semibold text-neutral-950"
                    >
                        Seller onboarding timeline
                    </h2>


                    <div
                        class="relative mt-7 space-y-6 before:absolute before:bottom-5 before:left-[19px] before:top-5 before:w-px before:bg-emerald-200"
                    >

                        @foreach ([
                            [
                                'title' => 'Application Submitted',
                                'description' => 'You submit your store application with all required information.',
                            ],
                            [
                                'title' => 'Marketplace Review',
                                'description' => 'Our team carefully reviews your application and store information.',
                            ],
                            [
                                'title' => 'Store Verification',
                                'description' => 'We verify your submitted documents and marketplace details.',
                            ],
                            [
                                'title' => 'Store Approved',
                                'description' => 'Your marketplace seller account is approved.',
                            ],
                            [
                                'title' => 'Dashboard Access',
                                'description' => 'Get access to seller tools and begin managing your store.',
                            ],
                        ] as $step)

                            <div
                                class="relative flex gap-4"
                            >

                                <div
                                    class="relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-emerald-200 bg-emerald-50 text-xs font-semibold text-emerald-700"
                                >
                                    {{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}
                                </div>

                                <div>

                                    <h3
                                        class="text-sm font-semibold text-neutral-950"
                                    >
                                        {{ $step['title'] }}
                                    </h3>

                                    <p
                                        class="mt-1 max-w-md text-sm leading-6 text-neutral-500"
                                    >
                                        {{ $step['description'] }}
                                    </p>

                                </div>

                            </div>

                        @endforeach

                    </div>

                </div>


                {{-- ================================================= --}}
                {{-- FAQ --}}
                {{-- ================================================= --}}
                <div>

                    <h2
                        class="text-2xl font-semibold text-neutral-950"
                    >
                        Frequently asked questions
                    </h2>


                    @php
                        $faqs = [
                            [
                                'question' => 'How do I become a seller?',
                                'answer' => 'Create a seller account, complete your store application, and submit it for marketplace review.',
                            ],
                            [
                                'question' => 'How long does the approval process take?',
                                'answer' => 'Approval time depends on the information and verification requirements for your store application.',
                            ],
                            [
                                'question' => 'Are there any fees to start selling?',
                                'answer' => 'Marketplace fees and seller charges can be defined according to your marketplace pricing model.',
                            ],
                            [
                                'question' => 'How do I receive payments?',
                                'answer' => 'Approved sellers can receive payouts through the marketplace seller payment workflow.',
                            ],
                            [
                                'question' => 'Can I sell products in multiple categories?',
                                'answer' => 'Yes. Sellers can manage products across supported marketplace categories.',
                            ],
                            [
                                'question' => 'Can I update my store information later?',
                                'answer' => 'Seller store information can be managed from the seller dashboard after approval.',
                            ],
                        ];
                    @endphp


                    <div
                        class="mt-7 divide-y divide-neutral-200 overflow-hidden rounded-xl border border-neutral-200 bg-white"
                        data-seller-faq
                    >

                        @foreach ($faqs as $index => $faq)

                            <div
                                data-faq-item
                            >

                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left"
                                    data-faq-button
                                >

                                    <span
                                        class="text-sm font-medium text-neutral-900"
                                    >
                                        {{ $faq['question'] }}
                                    </span>

                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        class="h-4 w-4 shrink-0 text-neutral-500 transition-transform"
                                        data-faq-icon
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="m6 9 6 6 6-6"
                                        />
                                    </svg>

                                </button>


                                <div
                                    class="hidden px-5 pb-5 text-sm leading-6 text-neutral-600"
                                    data-faq-content
                                >
                                    {{ $faq['answer'] }}
                                </div>

                            </div>

                        @endforeach

                    </div>

                </div>

            </div>

        </x-container>

    </section>


    {{-- ========================================================= --}}
    {{-- FINAL CTA --}}
    {{-- ========================================================= --}}
    <section class="bg-white pb-12">

        <x-container>

            <div
                class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-emerald-950 via-emerald-900 to-emerald-800 px-6 py-8 sm:px-8 lg:px-10"
            >

                <div
                    class="absolute -left-10 -top-16 h-40 w-40 rounded-full bg-white/5"
                    aria-hidden="true"
                ></div>

                <div
                    class="absolute -bottom-20 right-20 h-44 w-44 rounded-full bg-emerald-400/10"
                    aria-hidden="true"
                ></div>


                <div
                    class="relative z-10 flex flex-col gap-6 md:flex-row md:items-center md:justify-between"
                >

                    <div>

                        <h2
                            class="text-2xl font-semibold text-white"
                        >
                            Ready to grow your business?
                        </h2>

                        <p
                            class="mt-2 max-w-xl text-sm leading-6 text-white/75"
                        >
                            Join {{ config('marketplace.name') }} and start building
                            your marketplace presence with tools designed for sellers.
                        </p>

                    </div>


                    <a
                        href="{{ route('vendor.register') }}"
                        class="inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-lg bg-white px-6 text-sm font-semibold text-emerald-900 transition hover:bg-neutral-100"
                    >
                        Become a Seller

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

        </x-container>

    </section>

@endsection


@pushOnce('scripts')

    <script>
        document.addEventListener('DOMContentLoaded', () => {

            /*
            |--------------------------------------------------------------------------
            | Seller FAQ Accordion
            |--------------------------------------------------------------------------
            */

            const faqRoot = document.querySelector(
                '[data-seller-faq]'
            );

            if (!faqRoot) {
                return;
            }

            const items = faqRoot.querySelectorAll(
                '[data-faq-item]'
            );

            items.forEach((item) => {

                const button =
                    item.querySelector('[data-faq-button]');

                const content =
                    item.querySelector('[data-faq-content]');

                const icon =
                    item.querySelector('[data-faq-icon]');


                button?.addEventListener(
                    'click',
                    () => {

                        const isOpen =
                            !content.classList.contains('hidden');


                        /*
                        |--------------------------------------------------------------------------
                        | Close All
                        |--------------------------------------------------------------------------
                        */

                        items.forEach((otherItem) => {

                            otherItem
                                .querySelector('[data-faq-content]')
                                ?.classList.add('hidden');

                            otherItem
                                .querySelector('[data-faq-icon]')
                                ?.classList.remove('rotate-180');

                        });


                        /*
                        |--------------------------------------------------------------------------
                        | Open Selected
                        |--------------------------------------------------------------------------
                        */

                        if (!isOpen) {

                            content.classList.remove('hidden');

                            icon?.classList.add('rotate-180');

                        }

                    }
                );

            });

        });
    </script>

@endPushOnce