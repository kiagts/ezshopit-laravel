<footer class="border-t border-neutral-200 bg-white">
    <x-container class="grid gap-8 py-10 text-sm text-neutral-600 md:grid-cols-[1.5fr_repeat(3,1fr)]">
        <div>
            <img
                src="{{ Storage::url('frontstore/images/logo.png') }}"
                alt="{{ config('marketplace.name') }}"
                class="h-auto w-[150px] object-contain"
            >
                <p class="mt-3 max-w-sm leading-6">{{ $tagline ?: 'A multi-vendor marketplace built around verified sellers and clear catalog data.' }}</p>
        </div>
        <div>
            <p class="font-semibold text-neutral-950">Shop</p><div class="mt-3 space-y-2">
                @foreach (($footerMenus['shop'] ?? []) as $item)<a href="{{ $item['url'] }}" class="block hover:text-neutral-950">{{ $item['label'] }}</a>@endforeach
                @forelse ($footerPages as $page)
                    <a href="{{ route('storefront.pages.show', $page->slug) }}" class="block hover:text-neutral-950">{{ $page->title }}</a>
                @empty
                @endforelse
            </div>
        </div>
        @if ($supportEmail || $supportPhone)
            <div><p class="font-semibold text-neutral-950">Support</p><div class="mt-3 space-y-2">@if($supportEmail)<a href="mailto:{{ $supportEmail }}" class="block hover:text-neutral-950">{{ $supportEmail }}</a>@endif @if($supportPhone)<a href="tel:{{ $supportPhone }}" class="block hover:text-neutral-950">{{ $supportPhone }}</a>@endif</div></div>
        @endif
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
        <div><p class="font-semibold text-neutral-950">Sellers</p>
            <div class="mt-3 space-y-2">
                @forelse (($footerMenus['sellers'] ?? []) as $item)<a href="{{ $item['url'] }}" class="block hover:text-neutral-950">{{ $item['label'] }}</a>@empty<a href="{{ route('vendor.apply') }}" class="block hover:text-neutral-950">Sell With Us</a>@endforelse
            </div>
        </div>
    </x-container>
    <x-container class="border-t border-neutral-100 py-5 text-sm text-neutral-500">
        <p>&copy; {{ now()->year }} {{ $copyrightText ?: ($marketplaceName ?? config('marketplace.name')).' marketplace storefront.' }}</p>
    </x-container>
</footer>
