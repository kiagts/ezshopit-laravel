@extends('layouts.app')

@php
    use App\Support\Money;
@endphp

@section('title', 'Shopping Cart')
@section('description', 'Review your current marketplace cart.')

@section('content')
    <x-container class="py-10 sm:py-14">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <x-badge tone="brand">Cart</x-badge>
                <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Shopping Cart</h1>
                <p class="mt-2 text-sm leading-6 text-neutral-600">
                    {{ number_format($item_quantity) }} {{ str('item')->plural($item_quantity) }} across {{ number_format($groups->count()) }} {{ str('seller')->plural($groups->count()) }}.
                </p>
            </div>
            <div class="flex flex-wrap gap-3">
                <x-button :href="route('home')" variant="secondary">Continue Shopping</x-button>
                @if ($line_count > 0)
                    <form method="POST" action="{{ route('cart.clear') }}" onsubmit="return confirm('Clear all items from your cart?')">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="ghost">Clear cart</x-button>
                    </form>
                @endif
            </div>
        </div>

        @if ($line_count === 0)
            <div class="rounded-lg border border-dashed border-neutral-300 bg-white p-10 text-center shadow-soft">
                <h2 class="text-xl font-semibold text-neutral-950">Your cart is empty.</h2>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-neutral-600">Discover products from sellers across the marketplace.</p>
                <x-button :href="route('home')" class="mt-6">Start Shopping</x-button>
            </div>
        @else
            @if ($has_blocking_issues)
                <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
                    Some items need attention before checkout. Unavailable items are not included in the subtotal.
                </div>
            @endif

            <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
                <div class="space-y-6">
                    @foreach ($groups as $group)
                        <section class="rounded-lg border border-neutral-200 bg-white shadow-soft">
                            <div class="flex items-center justify-between gap-3 border-b border-neutral-200 px-5 py-4">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Sold by</p>
                                    @if ($group['vendor'])
                                        <a href="{{ route('storefront.stores.show', $group['vendor']) }}" class="mt-1 inline-block font-semibold text-neutral-950 hover:text-brand-800">{{ $group['vendor']->display_name }}</a>
                                    @else
                                        <p class="mt-1 font-semibold text-neutral-950">Unavailable seller</p>
                                    @endif
                                </div>
                                <p class="text-sm font-medium text-neutral-600">{{ Money::format($group['subtotal']) }}</p>
                            </div>

                            <div class="divide-y divide-neutral-100">
                                @foreach ($group['items'] as $row)
                                    @php
                                        $item = $row['item'];
                                        $product = $row['product'];
                                        $image = $row['image'];
                                    @endphp

                                    <article class="grid gap-4 p-5 sm:grid-cols-[6rem_1fr]">
                                        <div class="h-24 w-24 overflow-hidden rounded-md bg-neutral-100">
                                            @if ($product && $image)
                                                <a href="{{ route('storefront.products.show', $product) }}" aria-label="View {{ $product->name }}">
                                                    <img src="{{ Storage::url($image->path) }}" alt="{{ $image->alt_text ?: $product->name }}" class="h-full w-full object-cover">
                                                </a>
                                            @elseif ($product)
                                                <a href="{{ route('storefront.products.show', $product) }}" aria-label="View {{ $product->name }}">
                                                    <x-storefront.image-placeholder :label="$product->name" class="h-24 w-24" />
                                                </a>
                                            @else
                                                <div class="flex h-full w-full items-center justify-center text-sm font-medium text-neutral-400">Item</div>
                                            @endif
                                        </div>

                                        <div class="grid gap-4 lg:grid-cols-[1fr_13rem_7rem] lg:items-start">
                                            <div>
                                                @if ($product)
                                                    <a href="{{ route('storefront.products.show', $product) }}" class="font-semibold text-neutral-950 hover:text-brand-800">{{ $product->name }}</a>
                                                    @if ($row['variant_label'])
                                                        <p class="mt-1 text-sm text-neutral-600">{{ $row['variant_label'] }}</p>
                                                    @endif
                                                    <p class="mt-1 text-xs text-neutral-500">SKU: {{ $row['variant']->sku }}</p>
                                                @else
                                                    <h2 class="font-semibold text-neutral-950">This item is no longer available.</h2>
                                                @endif

                                                @if ($row['message'])
                                                    <p class="mt-3 text-sm font-medium {{ $row['status'] === 'valid' ? 'text-neutral-600' : 'text-amber-800' }}">{{ $row['message'] }}</p>
                                                @endif
                                            </div>

                                            <div class="space-y-3">
                                                @if ($row['unit_price'])
                                                    <p class="text-sm text-neutral-500">Unit price</p>
                                                    <x-storefront.price :price="$row['price']" />
                                                @else
                                                    <p class="text-sm font-medium text-neutral-500">Price unavailable</p>
                                                @endif

                                                @if (in_array($row['status'], ['valid', 'insufficient_stock'], true))
                                                    <form method="POST" action="{{ route('cart.items.update', $item) }}" class="flex flex-wrap items-end gap-2">
                                                        @csrf
                                                        @method('PATCH')
                                                        <label class="text-xs font-medium uppercase tracking-normal text-neutral-500">
                                                            Qty
                                                            <input name="quantity" type="number" min="1" max="{{ $maxQuantity }}" value="{{ $item->quantity }}" class="mt-1 h-10 w-20 rounded-md border border-neutral-300 px-3 text-sm normal-case tracking-normal text-neutral-900 focus:border-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-100">
                                                        </label>
                                                        <x-button type="submit" variant="secondary" size="sm">Update</x-button>
                                                    </form>
                                                @else
                                                    <p class="text-sm text-neutral-500">Qty {{ $item->quantity }}</p>
                                                @endif
                                            </div>

                                            <div class="flex flex-col gap-3 lg:items-end">
                                                <div>
                                                    <p class="text-sm text-neutral-500">Line total</p>
                                                    <p class="mt-1 font-semibold text-neutral-950">{{ $row['line_total_formatted'] }}</p>
                                                </div>
                                                <form method="POST" action="{{ route('cart.items.destroy', $item) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-sm font-medium text-neutral-600 hover:text-neutral-950">Remove</button>
                                                </form>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>

                <aside class="rounded-lg border border-neutral-200 bg-white p-6 shadow-soft lg:sticky lg:top-32">
                    <h2 class="text-lg font-semibold text-neutral-950">Order Summary</h2>
                    <div class="mt-5 space-y-4 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-neutral-600">Items subtotal</span>
                            <span class="font-semibold text-neutral-950">{{ $subtotal_formatted }}</span>
                        </div>
                        <p class="leading-6 text-neutral-500">Shipping and taxes are calculated at checkout.</p>
                        @if ($has_blocking_issues)
                            <p class="rounded-md bg-amber-50 p-3 text-amber-900">Resolve unavailable or stock-limited items before checkout.</p>
                        @endif
                    </div>
                    @if ($has_blocking_issues)
                        <div class="mt-6 rounded-md border border-dashed border-neutral-300 bg-neutral-50 p-4 text-sm leading-6 text-neutral-600">
                            Resolve the items above before checkout.
                        </div>
                    @else
                        <x-button :href="route('checkout.index')" class="mt-6 w-full">Proceed to Checkout</x-button>
                    @endif
                </aside>
            </div>
        @endif
    </x-container>
@endsection
