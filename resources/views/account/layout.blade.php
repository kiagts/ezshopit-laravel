@extends('layouts.app')

@php
    $accountActive = trim($__env->yieldContent('account_active', 'account'));
    $mobileLinks = [
        ['label' => 'Wallet', 'route' => 'account.wallet', 'active' => 'wallet'],
        ['label' => 'Overview', 'route' => 'account.dashboard', 'active' => 'account'],
        ['label' => 'Orders', 'route' => 'account.orders.index', 'active' => 'orders'],
        ['label' => 'Returns', 'route' => 'account.returns.index', 'active' => 'returns'],
        ['label' => 'Disputes', 'route' => 'account.disputes.index', 'active' => 'disputes'],
        ['label' => 'Wishlist', 'route' => 'account.wishlist.index', 'active' => 'wishlist'],
        ['label' => 'Gift Cards', 'route' => 'account.gift-cards.index', 'active' => 'gift-cards'],
        ['label' => 'Reviews', 'route' => 'account.reviews.index', 'active' => 'reviews'],
        ['label' => 'Addresses', 'route' => 'account.addresses.index', 'active' => 'addresses'],
        ['label' => 'Notifications', 'route' => 'account.notifications.index', 'active' => 'notifications'],
        ['label' => 'Profile', 'route' => 'account.profile.edit', 'active' => 'profile'],
        ['label' => 'Security', 'route' => 'account.security.edit', 'active' => 'security'],
    ];
@endphp

@push('head')
    <meta name="robots" content="noindex,nofollow">
@endpush

@section('content')
    <section class="bg-neutral-50">
        <x-container class="py-8 sm:py-10 lg:py-12">
            <div class="mb-6 rounded-lg border border-neutral-200 bg-white p-4 shadow-soft lg:hidden">
                <p class="text-xs font-semibold uppercase tracking-[0.08em] text-neutral-500">Account</p>
                <nav class="mt-3 flex gap-2 overflow-x-auto pb-1" aria-label="Mobile account navigation">
                    @foreach ($mobileLinks as $link)
                        <a
                            href="{{ route($link['route']) }}"
                            @class([
                                'whitespace-nowrap rounded-md border px-3 py-2 text-sm font-medium transition',
                                'border-neutral-950 bg-neutral-950 text-white' => $accountActive === $link['active'],
                                'border-neutral-200 bg-white text-neutral-700 hover:border-neutral-300 hover:text-neutral-950' => $accountActive !== $link['active'],
                            ])
                        >
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </nav>
            </div>

            <div class="grid gap-6 lg:grid-cols-[18rem_minmax(0,1fr)] lg:items-start">
                <x-account.nav :active="$accountActive" class="hidden lg:block" />

                <section class="min-w-0">
                    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            @hasSection('account_badge')
                                <x-badge tone="brand">@yield('account_badge')</x-badge>
                            @endif

                            <h1 class="@hasSection('account_badge') mt-4 @endif text-3xl font-semibold tracking-normal text-neutral-950">
                                @yield('account_title')
                            </h1>

                            @hasSection('account_description')
                                <p class="mt-2 text-sm leading-6 text-neutral-600">
                                    @yield('account_description')
                                </p>
                            @endif
                        </div>

                        @yield('account_actions')
                    </div>

                    @yield('account_content')
                </section>
            </div>
        </x-container>
    </section>
@endsection
