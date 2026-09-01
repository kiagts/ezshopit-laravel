@props(['active' => 'account'])

<aside class="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm">
    <p class="px-2 text-xs font-medium uppercase text-neutral-500">Account</p>
    <nav class="mt-3 grid gap-1 text-sm" aria-label="Account navigation">
        <a href="{{ route('account.dashboard') }}" class="{{ $active === 'account' ? 'bg-brand-50 text-brand-800' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-950' }} rounded-md px-3 py-2 font-medium">
            Account
        </a>
        <a href="{{ route('account.addresses.index') }}" class="{{ $active === 'addresses' ? 'bg-brand-50 text-brand-800' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-950' }} rounded-md px-3 py-2 font-medium">
            Addresses
        </a>
        <a href="{{ route('account.orders.index') }}" class="{{ $active === 'orders' ? 'bg-brand-50 text-brand-800' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-950' }} rounded-md px-3 py-2 font-medium">
            Orders
        </a>
        <a href="{{ route('account.wishlist.index') }}" class="{{ $active === 'wishlist' ? 'bg-brand-50 text-brand-800' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-950' }} rounded-md px-3 py-2 font-medium">
            Wishlist
        </a>
    </nav>
</aside>
