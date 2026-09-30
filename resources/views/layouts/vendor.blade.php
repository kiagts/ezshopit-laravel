@php
    $vendor = auth()->user()?->vendor;
    $isApprovedVendor = $vendor?->isApproved() ?? false;
    $unreadNotificationsCount = auth()->user()?->unreadNotifications()->count() ?? 0;
    $ordersRequiringActionCount = $isApprovedVendor
        ? $vendor->vendorOrders()->whereIn('status', [
            \App\Enums\VendorOrderStatus::Pending,
            \App\Enums\VendorOrderStatus::Confirmed,
            \App\Enums\VendorOrderStatus::Processing,
            \App\Enums\VendorOrderStatus::ReadyToShip,
        ])->count()
        : 0;
    $rejectedProductsCount = $isApprovedVendor
        ? $vendor->products()->where('approval_status', \App\Enums\ProductApprovalStatus::Rejected)->count()
        : 0;
    $navGroups = $isApprovedVendor ? [
        'Overview' => [
            ['label' => 'Dashboard', 'route' => 'vendor.dashboard', 'match' => 'vendor.dashboard'],
        ],
        'Commerce' => [
            ['label' => 'Products', 'route' => 'vendor.products.index', 'match' => 'vendor.products.*', 'badge' => $rejectedProductsCount],
            ['label' => 'Inventory', 'route' => 'vendor.inventory.index', 'match' => 'vendor.inventory.*'],
            ['label' => 'Orders', 'route' => 'vendor.orders.index', 'match' => 'vendor.orders.*', 'badge' => $ordersRequiringActionCount],
            ['label' => 'Disputes', 'route' => 'vendor.disputes.index', 'match' => 'vendor.disputes.*', 'badge' => 0],
            ['label' => 'Returns', 'route' => 'vendor.returns.index', 'match' => 'vendor.returns.*', 'badge' => 0],
            ['label' => 'Promotions', 'route' => 'vendor.promotions.index', 'match' => 'vendor.promotions.*'],
        ],
        'Customers' => [
            ['label' => 'Reviews', 'route' => 'vendor.reviews.index', 'match' => 'vendor.reviews.*'],
        ],
        'Finance' => [
            ['label' => 'Finance', 'route' => 'vendor.finance.index', 'match' => ['vendor.finance.*', 'vendor.earnings.*']],
            ['label' => 'Commissions', 'route' => 'vendor.commissions.index', 'match' => 'vendor.commissions.*'],
            ['label' => 'Payouts', 'route' => 'vendor.payouts.index', 'match' => ['vendor.payouts.*', 'vendor.payout-methods.*']],
        ],
        'Account' => [
            ['label' => 'Notifications', 'route' => 'vendor.notifications.index', 'match' => 'vendor.notifications.*', 'badge' => $unreadNotificationsCount],
            ['label' => 'Store Profile', 'route' => 'vendor.store.edit', 'match' => 'vendor.store.*'],
            ['label' => 'Shipping', 'route' => 'vendor.shipping-methods.index', 'match' => 'vendor.shipping-methods.*'],
        ],
    ] : [
        'Application' => [
            ['label' => 'Store Application', 'route' => 'vendor.onboarding.edit', 'match' => 'vendor.onboarding.*'],
            ['label' => 'Application Status', 'route' => 'vendor.application-status', 'match' => 'vendor.application-status'],
            ['label' => 'Notifications', 'route' => 'vendor.notifications.index', 'match' => 'vendor.notifications.*', 'badge' => $unreadNotificationsCount],
            ['label' => 'Payout History', 'route' => 'vendor.payouts.index', 'match' => 'vendor.payouts.*'],
        ],
    ];
    $flatNav = collect($navGroups)->flatten(1);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex,nofollow">

        <title>@yield('title', 'Dashboard') | Vendor | {{ config('marketplace.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-neutral-100 font-sans text-neutral-950 antialiased">
        <div class="min-h-screen lg:grid lg:grid-cols-[17rem_1fr]">
            <aside class="hidden border-r border-neutral-200 bg-white lg:block lg:min-h-screen">
                <div class="flex h-16 items-center border-b border-neutral-200 px-6">
                    <a href="{{ $isApprovedVendor ? route('vendor.dashboard') : route('vendor.application-status') }}" class="text-base font-semibold">{{ config('marketplace.name') }}</a>
                </div>

                <nav class="px-3 py-4 text-sm" aria-label="Vendor navigation">
                    @foreach ($navGroups as $group => $links)
                        <div class="@if (! $loop->first) mt-5 @endif">
                            <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-neutral-400">{{ $group }}</p>
                            <div class="grid gap-1">
                                @foreach ($links as $link)
                                    @php
                                        $matches = (array) $link['match'];
                                        $active = collect($matches)->contains(fn ($match) => request()->routeIs($match));
                                        $badge = (int) ($link['badge'] ?? 0);
                                    @endphp
                                    <a href="{{ route($link['route']) }}" class="{{ $active ? 'bg-brand-50 text-brand-800' : 'text-neutral-500 hover:bg-neutral-100 hover:text-neutral-950' }} flex items-center justify-between gap-3 rounded-md px-3 py-2 font-medium">
                                        <span>{{ $link['label'] }}</span>
                                        @if ($badge > 0)
                                            <span class="rounded-full bg-brand-700 px-2 py-0.5 text-[11px] font-semibold text-white">{{ $badge > 99 ? '99+' : $badge }}</span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </nav>
            </aside>

            <div class="flex min-h-screen flex-col">
                <header class="border-b border-neutral-200 bg-white">
                    <div class="flex min-h-16 flex-col gap-4 px-4 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="truncate text-sm font-semibold text-neutral-950">{{ $vendor?->display_name ?? auth()->user()->name }}</p>
                                @if ($vendor?->status)
                                    <x-badge :tone="$vendor->status->badgeTone()">{{ $vendor->status->label() }}</x-badge>
                                @endif
                            </div>
                            <p class="mt-1 text-xs font-medium uppercase text-neutral-500">Vendor workspace</p>
                        </div>

                        <div class="flex items-center gap-3">
                            @if ($isApprovedVendor)
                                <x-button :href="route('vendor.products.create')" size="sm">Add Product</x-button>
                            @endif
                            <x-notification-bell
                                index-route="vendor.notifications.index"
                                open-route="vendor.notifications.open"
                                read-all-route="vendor.notifications.read-all"
                            />

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-button type="submit" variant="secondary" size="sm">Logout</x-button>
                            </form>
                        </div>
                    </div>

                    <nav class="flex gap-2 overflow-x-auto border-t border-neutral-100 px-4 py-3 sm:px-6 lg:hidden" aria-label="Mobile vendor navigation">
                        @foreach ($flatNav as $link)
                            @php
                                $matches = (array) $link['match'];
                                $active = collect($matches)->contains(fn ($match) => request()->routeIs($match));
                                $badge = (int) ($link['badge'] ?? 0);
                            @endphp
                            <a href="{{ route($link['route']) }}" class="{{ $active ? 'border-neutral-950 bg-neutral-950 text-white' : 'border-neutral-200 bg-white text-neutral-700 hover:border-neutral-300 hover:text-neutral-950' }} inline-flex h-10 shrink-0 items-center gap-2 rounded-md border px-3 text-sm font-medium">
                                <span>{{ $link['label'] }}</span>
                                @if ($badge > 0)
                                    <span class="{{ $active ? 'bg-white text-neutral-950' : 'bg-brand-700 text-white' }} rounded-full px-2 py-0.5 text-[11px] font-semibold">{{ $badge > 99 ? '99+' : $badge }}</span>
                                @endif
                            </a>
                        @endforeach
                    </nav>
                </header>

                @if (session('status'))
                    <div class="px-4 pt-6 sm:px-6 lg:px-8">
                        <x-alert type="success">{{ session('status') }}</x-alert>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="px-4 pt-6 sm:px-6 lg:px-8">
                        <x-alert type="danger">{{ $errors->first() }}</x-alert>
                    </div>
                @endif

                <main class="flex-1 px-4 py-8 sm:px-6 lg:px-8">
                    @yield('content')
                </main>
            </div>
        </div>
        @stack('scripts')
    </body>
</html>

