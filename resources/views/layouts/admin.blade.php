@php
    $adminNavigationCounters ??= [];

    $counter = fn (string $key): int => (int) ($adminNavigationCounters[$key] ?? 0);
    $navClass = fn (bool $active): string => $active
        ? 'bg-white/10 text-white'
        : 'text-neutral-400 hover:bg-white/5 hover:text-white';
    $badge = function (int $count): string {
        if ($count <= 0) {
            return '';
        }

        return '<span class="ml-auto rounded-full bg-brand-500 px-2 py-0.5 text-[11px] font-semibold text-white">'.($count > 99 ? '99+' : $count).'</span>';
    };

    $groups = [
        'Overview' => [
            ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'count' => 0],
        ],
        'Marketplace' => [
            ['label' => 'Vendors', 'route' => 'admin.vendors.index', 'active' => 'admin.vendors.*', 'count' => $counter('pendingVendors')],
            ['label' => 'Products', 'route' => 'admin.products.index', 'active' => 'admin.products.*', 'count' => $counter('pendingProducts')],
            ['label' => 'Categories', 'route' => 'admin.categories.index', 'active' => 'admin.categories.*', 'count' => 0],
            ['label' => 'Brands', 'route' => 'admin.brands.index', 'active' => 'admin.brands.*', 'count' => 0],
            ['label' => 'Attributes', 'route' => 'admin.attributes.index', 'active' => 'admin.attributes.*', 'count' => 0],
            ['label' => 'Attribute Sets', 'route' => 'admin.attribute-sets.index', 'active' => 'admin.attribute-sets.*', 'count' => 0],
            ['label' => 'Inventory', 'route' => 'admin.inventory.index', 'active' => 'admin.inventory.*', 'count' => 0],
            ['label' => 'Shipping', 'route' => 'admin.shipping-methods.index', 'active' => 'admin.shipping-methods.*', 'count' => 0],
        ],
        'Commerce' => [
            ['label' => 'Disputes', 'route' => 'admin.disputes.index', 'active' => 'admin.disputes.*', 'count' => 0],
            ['label' => 'Returns', 'route' => 'admin.returns.index', 'active' => 'admin.returns.*', 'count' => 0],
            ['label' => 'Orders', 'route' => 'admin.orders.index', 'active' => 'admin.orders.*', 'count' => 0],
            ['label' => 'Payments', 'route' => 'admin.payments.index', 'active' => 'admin.payments.*', 'count' => $counter('paymentReviews')],
            ['label' => 'Refunds', 'route' => 'admin.refunds.index', 'active' => 'admin.refunds.*', 'count' => $counter('refundsRequiringAttention')],
            ['label' => 'Promotions', 'route' => 'admin.promotions.index', 'active' => 'admin.promotions.*', 'count' => 0],
            ['label' => 'Reviews', 'route' => 'admin.reviews.index', 'active' => 'admin.reviews.*', 'count' => $counter('pendingReviews')],
        ],
        'Finance' => [
            ['label' => 'Commission Rules', 'route' => 'admin.commission-rules.index', 'active' => 'admin.commission-rules.*', 'count' => 0],
            ['label' => 'Commissions', 'route' => 'admin.commissions.index', 'active' => 'admin.commissions.*', 'count' => 0],
            ['label' => 'Vendor Ledger', 'route' => 'admin.vendor-ledger.index', 'active' => 'admin.vendor-ledger.*', 'count' => 0],
            ['label' => 'Payouts', 'route' => 'admin.payouts.index', 'active' => 'admin.payouts.*', 'count' => $counter('payoutsAwaitingAction')],
        ],
        'Operations' => [
            ['label' => 'Homepage', 'route' => 'admin.homepage.index', 'active' => 'admin.homepage.*', 'count' => 0],
            ['label' => 'Pages', 'route' => 'admin.pages.index', 'active' => 'admin.pages.*', 'count' => 0],
            ['label' => 'Settings', 'route' => 'admin.settings.index', 'active' => 'admin.settings.*', 'count' => 0],
            ['label' => 'Navigation', 'route' => 'admin.navigation.index', 'active' => 'admin.navigation.*', 'count' => 0],
            ['label' => 'Markets', 'route' => 'admin.markets.index', 'active' => 'admin.markets.*', 'count' => 0],
            ['label' => 'Customer wallets', 'route' => 'admin.wallets.index', 'active' => 'admin.wallets.*', 'count' => 0],
            ['label' => 'Gift cards', 'route' => 'admin.gift-cards.index', 'active' => 'admin.gift-cards.*', 'count' => 0],
            ['label' => 'Notifications', 'route' => 'admin.notifications.index', 'active' => 'admin.notifications.*', 'count' => auth()->user()?->unreadNotifications()->count() ?? 0],
        ],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex,nofollow">

        <title>@yield('title', 'Dashboard') | Admin | {{ config('marketplace.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-neutral-100 font-sans text-neutral-950 antialiased">
        <div class="min-h-screen lg:grid lg:grid-cols-[18rem_1fr]">
            <aside class="border-b border-neutral-200 bg-neutral-950 text-white lg:min-h-screen lg:border-b-0">
                <div class="flex h-16 items-center justify-between border-b border-white/10 px-5">
                    <a href="{{ route('admin.dashboard') }}" class="text-base font-semibold">{{ config('marketplace.name') }}</a>
                    <x-badge tone="dark" class="ring-white/20">Admin</x-badge>
                </div>

                <nav class="hidden gap-5 px-3 py-4 text-sm lg:grid" aria-label="Admin navigation">
                    @foreach ($groups as $group => $items)
                        <div>
                            <p class="px-3 text-[11px] font-semibold uppercase tracking-normal text-neutral-500">{{ $group }}</p>
                            <div class="mt-2 grid gap-1">
                                @foreach ($items as $item)
                                    <a href="{{ route($item['route']) }}" class="{{ $navClass(request()->routeIs($item['active'])) }} flex items-center gap-3 rounded-md px-3 py-2 font-medium">
                                        <span>{{ $item['label'] }}</span>
                                        {!! $badge($item['count']) !!}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </nav>

                <nav class="flex gap-2 overflow-x-auto px-3 py-3 text-sm lg:hidden" aria-label="Admin mobile navigation">
                    @foreach ($groups as $items)
                        @foreach ($items as $item)
                            <a href="{{ route($item['route']) }}" class="{{ $navClass(request()->routeIs($item['active'])) }} inline-flex shrink-0 items-center gap-2 rounded-md px-3 py-2 font-medium">
                                {{ $item['label'] }}
                                @if ($item['count'] > 0)
                                    <span class="rounded-full bg-brand-500 px-1.5 text-[11px] text-white">{{ $item['count'] > 99 ? '99+' : $item['count'] }}</span>
                                @endif
                            </a>
                        @endforeach
                    @endforeach
                </nav>
            </aside>

            <div class="flex min-h-screen flex-col">
                <header class="border-b border-neutral-200 bg-white">
                    <div class="flex min-h-16 flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                        <div>
                            <p class="text-xs font-medium uppercase text-neutral-500">Admin workspace</p>
                            <p class="text-sm font-semibold text-neutral-950">@yield('title', 'Dashboard')</p>
                        </div>

                        <div class="flex items-center gap-3">
                            <x-notification-bell
                                index-route="admin.notifications.index"
                                open-route="admin.notifications.open"
                                read-all-route="admin.notifications.read-all"
                            />

                            <div class="hidden text-right sm:block">
                                <p class="text-sm font-semibold text-neutral-950">{{ auth()->user()->name }}</p>
                                <p class="text-xs text-neutral-500">{{ auth()->user()->email }}</p>
                            </div>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-button type="submit" variant="secondary" size="sm">Logout</x-button>
                            </form>
                        </div>
                    </div>
                </header>

                <main class="flex-1 px-4 py-8 sm:px-6 lg:px-8">
                    @if (session('status'))
                        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
                    @endif

                    @yield('content')
                </main>
            </div>
        </div>

        @stack('scripts')
    </body>
</html>
