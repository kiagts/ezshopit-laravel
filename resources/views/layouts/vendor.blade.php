<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', 'Dashboard') | Vendor | {{ config('marketplace.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-neutral-100 font-sans text-neutral-950 antialiased">
        <div class="min-h-screen lg:grid lg:grid-cols-[17rem_1fr]">
            <aside class="border-b border-neutral-200 bg-white lg:min-h-screen lg:border-b-0 lg:border-r">
                <div class="flex h-16 items-center border-b border-neutral-200 px-6">
                    <a href="{{ route('vendor.dashboard') }}" class="text-base font-semibold">{{ config('marketplace.name') }}</a>
                </div>

                <nav class="grid gap-1 px-3 py-4 text-sm" aria-label="Vendor navigation">
                    @php
                        $vendor = auth()->user()->vendor;
                    @endphp

                    @if ($vendor?->isApproved())
                        <a href="{{ route('vendor.dashboard') }}" class="{{ request()->routeIs('vendor.dashboard') ? 'bg-brand-50 text-brand-800' : 'text-neutral-500 hover:bg-neutral-100 hover:text-neutral-950' }} rounded-md px-3 py-2 font-medium">Dashboard</a>
                        <a href="{{ route('vendor.products.index') }}" class="{{ request()->routeIs('vendor.products.*') ? 'bg-brand-50 text-brand-800' : 'text-neutral-500 hover:bg-neutral-100 hover:text-neutral-950' }} rounded-md px-3 py-2 font-medium">Products</a>
                        <a href="{{ route('vendor.orders.index') }}" class="{{ request()->routeIs('vendor.orders.*') ? 'bg-brand-50 text-brand-800' : 'text-neutral-500 hover:bg-neutral-100 hover:text-neutral-950' }} rounded-md px-3 py-2 font-medium">Orders</a>
                        <a href="{{ route('vendor.earnings.index') }}" class="{{ request()->routeIs('vendor.earnings.*') ? 'bg-brand-50 text-brand-800' : 'text-neutral-500 hover:bg-neutral-100 hover:text-neutral-950' }} rounded-md px-3 py-2 font-medium">Earnings</a>
                        <a href="{{ route('vendor.payouts.index') }}" class="{{ request()->routeIs('vendor.payouts.*') || request()->routeIs('vendor.payout-methods.*') ? 'bg-brand-50 text-brand-800' : 'text-neutral-500 hover:bg-neutral-100 hover:text-neutral-950' }} rounded-md px-3 py-2 font-medium">Payouts</a>
                        <a href="{{ route('vendor.commissions.index') }}" class="{{ request()->routeIs('vendor.commissions.*') ? 'bg-brand-50 text-brand-800' : 'text-neutral-500 hover:bg-neutral-100 hover:text-neutral-950' }} rounded-md px-3 py-2 font-medium">Commissions</a>
                        <a href="{{ route('vendor.reviews.index') }}" class="{{ request()->routeIs('vendor.reviews.*') ? 'bg-brand-50 text-brand-800' : 'text-neutral-500 hover:bg-neutral-100 hover:text-neutral-950' }} rounded-md px-3 py-2 font-medium">Reviews</a>
                        <a href="{{ route('vendor.inventory.index') }}" class="{{ request()->routeIs('vendor.inventory.*') ? 'bg-brand-50 text-brand-800' : 'text-neutral-500 hover:bg-neutral-100 hover:text-neutral-950' }} rounded-md px-3 py-2 font-medium">Inventory</a>
                        <a href="{{ route('vendor.shipping-methods.index') }}" class="{{ request()->routeIs('vendor.shipping-methods.*') ? 'bg-brand-50 text-brand-800' : 'text-neutral-500 hover:bg-neutral-100 hover:text-neutral-950' }} rounded-md px-3 py-2 font-medium">Shipping</a>
                        @foreach (['Store Settings'] as $item)
                            <span class="rounded-md px-3 py-2 font-medium text-neutral-500">{{ $item }}</span>
                        @endforeach
                    @else
                        <a href="{{ route('vendor.onboarding.edit') }}" class="{{ request()->routeIs('vendor.onboarding.*') ? 'bg-brand-50 text-brand-800' : 'text-neutral-500 hover:bg-neutral-100 hover:text-neutral-950' }} rounded-md px-3 py-2 font-medium">Store Application</a>
                        <a href="{{ route('vendor.application-status') }}" class="{{ request()->routeIs('vendor.application-status') ? 'bg-brand-50 text-brand-800' : 'text-neutral-500 hover:bg-neutral-100 hover:text-neutral-950' }} rounded-md px-3 py-2 font-medium">Application Status</a>
                        <a href="{{ route('vendor.payouts.index') }}" class="{{ request()->routeIs('vendor.payouts.*') ? 'bg-brand-50 text-brand-800' : 'text-neutral-500 hover:bg-neutral-100 hover:text-neutral-950' }} rounded-md px-3 py-2 font-medium">Payout History</a>
                    @endif
                </nav>
            </aside>

            <div class="flex min-h-screen flex-col">
                <header class="border-b border-neutral-200 bg-white">
                    <div class="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                        <div>
                            <p class="text-xs font-medium uppercase text-neutral-500">Vendor workspace</p>
                            <p class="text-sm font-semibold text-neutral-950">{{ auth()->user()->name }}</p>
                        </div>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-button type="submit" variant="secondary" size="sm">Logout</x-button>
                        </form>
                    </div>
                </header>

                <main class="flex-1 px-4 py-8 sm:px-6 lg:px-8">
                    @yield('content')
                </main>
            </div>
        </div>
    </body>
</html>
