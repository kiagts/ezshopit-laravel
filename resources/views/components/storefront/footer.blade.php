<footer class="border-t border-neutral-200 bg-white">
    <x-container class="grid gap-8 py-10 text-sm text-neutral-600 md:grid-cols-[1.5fr_repeat(3,1fr)]">
        <div>
            <img
                src="{{ Storage::url('frontstore/images/logo.png') }}"
                alt="{{ config('marketplace.name') }}"
                class="h-auto w-[150px] object-contain"
            >
            <p class="mt-3 max-w-sm leading-6">A multi-vendor marketplac built around verified sellers, clear catalog data, and role-aware account areas.</p>
        </div>
        <div>
            <p class="font-semibold text-neutral-950">Marketplace</p>
            <div class="mt-3 space-y-2">
                <p class="text-neutral-500">Categories</p>
                <p class="text-neutral-500">Brands</p>
                <p class="text-neutral-500">Deals</p>
            </div>
        </div>
        <div>
            <p class="font-semibold text-neutral-950">Account</p>
            <div class="mt-3 space-y-2">
                @guest
                    <a href="{{ route('login') }}" class="block hover:text-neutral-950">Sign In</a>
                    <a href="{{ route('register') }}" class="block hover:text-neutral-950">Create Account</a>
                @else
                    <a href="{{ auth()->user()->dashboardPath() }}" class="block hover:text-neutral-950">Dashboard</a>
                @endguest
            </div>
        </div>
        <div>
            <p class="font-semibold text-neutral-950">Sellers</p>
            <div class="mt-3 space-y-2">
                <a href="{{ route('vendor.apply') }}" class="block hover:text-neutral-950">Sell With Us</a>
                <p class="text-neutral-500">Approval workflow</p>
            </div>
        </div>
    </x-container>
    <x-container class="border-t border-neutral-100 py-5 text-sm text-neutral-500">
        <p>&copy; {{ now()->year }} {{ config('marketplace.name') }}. Marketplace storefront.</p>
    </x-container>
</footer>
