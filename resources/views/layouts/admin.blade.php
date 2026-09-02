<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', 'Dashboard') | Admin | {{ config('marketplace.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-neutral-100 font-sans text-neutral-950 antialiased">
        <div class="min-h-screen lg:grid lg:grid-cols-[17rem_1fr]">
            <aside class="border-b border-neutral-200 bg-neutral-950 text-white lg:min-h-screen lg:border-b-0">
                <div class="flex h-16 items-center border-b border-white/10 px-6">
                    <a href="{{ route('admin.dashboard') }}" class="text-base font-semibold">{{ config('marketplace.name') }}</a>
                </div>

                <nav class="grid gap-1 px-3 py-4 text-sm" aria-label="Admin navigation">
                    <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'bg-white/10 text-white' : 'text-neutral-400 hover:bg-white/5 hover:text-white' }} rounded-md px-3 py-2 font-medium">Dashboard</a>
                    <a href="{{ route('admin.orders.index') }}" class="{{ request()->routeIs('admin.orders.*') ? 'bg-white/10 text-white' : 'text-neutral-400 hover:bg-white/5 hover:text-white' }} rounded-md px-3 py-2 font-medium">Orders</a>
                    <a href="{{ route('admin.payments.index') }}" class="{{ request()->routeIs('admin.payments.*') ? 'bg-white/10 text-white' : 'text-neutral-400 hover:bg-white/5 hover:text-white' }} rounded-md px-3 py-2 font-medium">Payments</a>
                    <a href="{{ route('admin.refunds.index') }}" class="{{ request()->routeIs('admin.refunds.*') ? 'bg-white/10 text-white' : 'text-neutral-400 hover:bg-white/5 hover:text-white' }} rounded-md px-3 py-2 font-medium">Refunds</a>
                    <a href="{{ route('admin.payouts.index') }}" class="{{ request()->routeIs('admin.payouts.*') ? 'bg-white/10 text-white' : 'text-neutral-400 hover:bg-white/5 hover:text-white' }} rounded-md px-3 py-2 font-medium">Payouts</a>
                    <a href="{{ route('admin.reviews.index') }}" class="{{ request()->routeIs('admin.reviews.*') ? 'bg-white/10 text-white' : 'text-neutral-400 hover:bg-white/5 hover:text-white' }} rounded-md px-3 py-2 font-medium">Reviews</a>
                    <a href="{{ route('admin.commissions.index') }}" class="{{ request()->routeIs('admin.commissions.*') ? 'bg-white/10 text-white' : 'text-neutral-400 hover:bg-white/5 hover:text-white' }} rounded-md px-3 py-2 font-medium">Commissions</a>
                    <a href="{{ route('admin.commission-rules.index') }}" class="{{ request()->routeIs('admin.commission-rules.*') ? 'bg-white/10 text-white' : 'text-neutral-400 hover:bg-white/5 hover:text-white' }} rounded-md px-3 py-2 font-medium">Commission Rules</a>
                    <a href="{{ route('admin.vendor-ledger.index') }}" class="{{ request()->routeIs('admin.vendor-ledger.*') ? 'bg-white/10 text-white' : 'text-neutral-400 hover:bg-white/5 hover:text-white' }} rounded-md px-3 py-2 font-medium">Vendor Ledger</a>
                    <a href="{{ route('admin.vendors.index') }}" class="{{ request()->routeIs('admin.vendors.*') ? 'bg-white/10 text-white' : 'text-neutral-400 hover:bg-white/5 hover:text-white' }} rounded-md px-3 py-2 font-medium">Vendors</a>
                    <a href="{{ route('admin.inventory.index') }}" class="{{ request()->routeIs('admin.inventory.*') ? 'bg-white/10 text-white' : 'text-neutral-400 hover:bg-white/5 hover:text-white' }} rounded-md px-3 py-2 font-medium">Inventory</a>
                    <a href="{{ route('admin.shipping-methods.index') }}" class="{{ request()->routeIs('admin.shipping-methods.*') ? 'bg-white/10 text-white' : 'text-neutral-400 hover:bg-white/5 hover:text-white' }} rounded-md px-3 py-2 font-medium">Shipping</a>
                    <a href="{{ route('admin.categories.index') }}" class="{{ request()->routeIs('admin.categories.*') ? 'bg-white/10 text-white' : 'text-neutral-400 hover:bg-white/5 hover:text-white' }} rounded-md px-3 py-2 font-medium">Categories</a>
                    <a href="{{ route('admin.brands.index') }}" class="{{ request()->routeIs('admin.brands.*') ? 'bg-white/10 text-white' : 'text-neutral-400 hover:bg-white/5 hover:text-white' }} rounded-md px-3 py-2 font-medium">Brands</a>
                    <a href="{{ route('admin.products.index') }}" class="{{ request()->routeIs('admin.products.*') ? 'bg-white/10 text-white' : 'text-neutral-400 hover:bg-white/5 hover:text-white' }} rounded-md px-3 py-2 font-medium">Products</a>
                    <a href="{{ route('admin.attributes.index') }}" class="{{ request()->routeIs('admin.attributes.*') ? 'bg-white/10 text-white' : 'text-neutral-400 hover:bg-white/5 hover:text-white' }} rounded-md px-3 py-2 font-medium">Attributes</a>
                    <a href="{{ route('admin.attribute-sets.index') }}" class="{{ request()->routeIs('admin.attribute-sets.*') ? 'bg-white/10 text-white' : 'text-neutral-400 hover:bg-white/5 hover:text-white' }} rounded-md px-3 py-2 font-medium">Attribute Sets</a>
                    @foreach (['Customers', 'Settings'] as $item)
                        <span class="rounded-md px-3 py-2 font-medium text-neutral-400">{{ $item }}</span>
                    @endforeach
                </nav>
            </aside>

            <div class="flex min-h-screen flex-col">
                <header class="border-b border-neutral-200 bg-white">
                    <div class="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                        <div>
                            <p class="text-xs font-medium uppercase text-neutral-500">Admin workspace</p>
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
