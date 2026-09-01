@extends('layouts.app')

@php
    $clearUrl = route('storefront.stores.show', $vendor);
    $hasResults = $products->total() > 0;
    $selectedCategorySlug = $filters['category'][0] ?? null;
    $storeDescription = $vendor->description ?: 'Shop products from '.$vendor->display_name.' on '.config('app.name').'.';

    $categoryUrl = function (?string $slug = null) use ($vendor, $filters) {
        $query = collect(request()->query())
            ->except(['page', 'category', 'brand', 'attributes'])
            ->filter(fn ($value) => $value !== [] && $value !== null && $value !== '')
            ->all();

        if ($slug) {
            $query['category'] = $slug;
        }

        return route('storefront.stores.show', $vendor).($query ? '?'.http_build_query($query) : '');
    };
@endphp

@section('title', 'Shop '.$vendor->display_name.' | '.config('app.name'))
@section('description', str($storeDescription)->limit(155))

@push('head')
    <link rel="canonical" href="{{ route('storefront.stores.show', $vendor) }}">
    <meta property="og:title" content="Shop {{ $vendor->display_name }} | {{ config('app.name') }}">
    <meta property="og:description" content="{{ str($storeDescription)->limit(155) }}">
    @if ($vendor->banner_path || $vendor->logo_path)
        <meta property="og:image" content="{{ Storage::url($vendor->banner_path ?: $vendor->logo_path) }}">
    @endif
@endpush

@section('content')
    <section class="bg-white">
        <x-container class="py-8">
            <nav class="flex flex-wrap items-center gap-2 text-sm text-neutral-500" aria-label="Breadcrumb">
                <a href="{{ route('home') }}" class="hover:text-neutral-950">Home</a>
                <span aria-hidden="true">/</span>
                <span class="font-medium text-neutral-800" aria-current="page">{{ $vendor->display_name }}</span>
            </nav>

            <div class="mt-6 overflow-hidden rounded-lg border border-neutral-200 bg-neutral-100">
                <div class="h-48 sm:h-60 lg:h-72">
                    @if ($vendor->banner_path)
                        <img src="{{ Storage::url($vendor->banner_path) }}" alt="{{ $vendor->display_name }} store banner" class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full items-center justify-center bg-neutral-100">
                            <div class="text-center">
                                <p class="text-sm font-semibold uppercase tracking-normal text-brand-700">Marketplace seller</p>
                                <p class="mt-2 text-2xl font-semibold text-neutral-950">{{ $vendor->display_name }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="-mt-10 flex flex-col gap-5 sm:-mt-12 sm:flex-row sm:items-end sm:justify-between">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                    <div class="flex h-24 w-24 items-center justify-center rounded-lg border-4 border-white bg-brand-50 text-2xl font-semibold text-brand-800 shadow-soft sm:h-28 sm:w-28">
                        @if ($vendor->logo_path)
                            <img src="{{ Storage::url($vendor->logo_path) }}" alt="{{ $vendor->display_name }} logo" class="h-full w-full rounded-md object-contain">
                        @else
                            {{ str($vendor->display_name)->substr(0, 1)->upper() }}
                        @endif
                    </div>
                    <div class="max-w-2xl pb-1">
                        <p class="text-sm font-semibold uppercase tracking-normal text-emerald-700">Marketplace seller</p>
                        <h1 class="mt-2 text-3xl font-semibold tracking-normal text-neutral-950">{{ $vendor->display_name }}</h1>
                        @if ($vendor->description)
                            <p class="mt-3 line-clamp-2 text-sm leading-6 text-neutral-600">{{ $vendor->description }}</p>
                        @endif
                        <p class="mt-3 text-xs text-neutral-500">Selling since {{ $vendor->created_at->format('Y') }}</p>
                    </div>
                </div>
            </div>

            <div class="mt-7 flex flex-wrap gap-2 border-y border-neutral-200 py-3 text-sm font-medium">
                <a href="{{ route('storefront.stores.show', $vendor) }}" class="rounded-md px-3 py-2 text-neutral-700 hover:bg-neutral-50 hover:text-neutral-950">Store Home</a>
                <a href="#products" class="rounded-md px-3 py-2 text-neutral-700 hover:bg-neutral-50 hover:text-neutral-950">Products</a>
                <a href="#categories" class="rounded-md px-3 py-2 text-neutral-700 hover:bg-neutral-50 hover:text-neutral-950">Categories</a>
                <a href="#about" class="rounded-md px-3 py-2 text-neutral-700 hover:bg-neutral-50 hover:text-neutral-950">About</a>
            </div>

            <form method="GET" action="{{ route('storefront.stores.show', $vendor) }}" class="mt-7 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
                @foreach ($filters['category'] as $categorySlug)
                    <input type="hidden" name="category" value="{{ $categorySlug }}">
                @endforeach
                @if ($filters['price_min'])
                    <input type="hidden" name="price_min" value="{{ $filters['price_min'] }}">
                @endif
                @if ($filters['price_max'])
                    <input type="hidden" name="price_max" value="{{ $filters['price_max'] }}">
                @endif
                @if ($filters['in_stock'])
                    <input type="hidden" name="in_stock" value="1">
                @endif
                <label class="sr-only" for="store-search">Search products in {{ $vendor->display_name }}</label>
                <input id="store-search" name="q" type="search" value="{{ $filters['q'] }}" placeholder="Search products in {{ $vendor->display_name }}" class="h-11 rounded-md border border-neutral-300 bg-white px-3 text-sm focus:border-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-100">
                <x-button type="submit">Search Store</x-button>
            </form>
        </x-container>
    </section>

    <section class="bg-neutral-50 py-8">
        <x-container>
            <div id="categories" class="mb-6">
                <h2 class="text-sm font-semibold uppercase tracking-normal text-neutral-500">Store Categories</h2>
                <div class="mt-3 flex gap-2 overflow-x-auto pb-2">
                    <a href="{{ $categoryUrl() }}" class="shrink-0 rounded-md border px-4 py-2 text-sm font-medium {{ $selectedCategorySlug ? 'border-neutral-200 bg-white text-neutral-700 hover:border-neutral-300' : 'border-brand-700 bg-brand-50 text-brand-900' }}">
                        All Products
                    </a>
                    @foreach ($storeCategories->take(10) as $storeCategory)
                        <a href="{{ $categoryUrl($storeCategory->slug) }}" class="shrink-0 rounded-md border px-4 py-2 text-sm font-medium {{ $selectedCategorySlug === $storeCategory->slug ? 'border-brand-700 bg-brand-50 text-brand-900' : 'border-neutral-200 bg-white text-neutral-700 hover:border-neutral-300 hover:text-neutral-950' }}">
                            {{ $storeCategory->name }}
                        </a>
                    @endforeach
                </div>
            </div>

            <x-storefront.active-filters :filters="$activeFilters" :clear-url="$clearUrl" class="mb-5" />

            <div id="products" class="mb-5 flex flex-col gap-3 rounded-lg border border-neutral-200 bg-white p-4 shadow-soft md:flex-row md:items-center md:justify-between">
                <div>
                    @if (filled($filters['q'] ?? null))
                        <p class="text-sm font-medium text-neutral-950">Results for &ldquo;{{ $filters['q'] }}&rdquo;</p>
                    @else
                        <p class="text-sm font-medium text-neutral-950">{{ number_format($products->total()) }} {{ str('product')->plural($products->total()) }}</p>
                    @endif
                    <p class="text-xs text-neutral-500">{{ number_format($products->total()) }} {{ str('result')->plural($products->total()) }} in {{ $vendor->display_name }}</p>
                </div>

                <div class="flex gap-3">
                    <button type="button" data-discovery-filters-open class="inline-flex h-10 items-center justify-center rounded-md border border-neutral-300 bg-white px-4 text-sm font-medium text-neutral-700 lg:hidden">
                        Filters @if ($activeFilterCount) ({{ $activeFilterCount }}) @endif
                    </button>

                    <form method="GET" action="{{ route('storefront.stores.show', $vendor) }}" class="flex items-center gap-2">
                        @if (filled($filters['q'] ?? null))
                            <input type="hidden" name="q" value="{{ $filters['q'] }}">
                        @endif
                        @foreach ($filters['brand'] as $brand)
                            <input type="hidden" name="brand[]" value="{{ $brand }}">
                        @endforeach
                        @foreach ($filters['category'] as $categorySlug)
                            <input type="hidden" name="category" value="{{ $categorySlug }}">
                        @endforeach
                        @foreach ($filters['attributes'] as $code => $values)
                            @foreach ($values as $value)
                                <input type="hidden" name="attributes[{{ $code }}][]" value="{{ $value }}">
                            @endforeach
                        @endforeach
                        @if ($filters['price_min'])
                            <input type="hidden" name="price_min" value="{{ $filters['price_min'] }}">
                        @endif
                        @if ($filters['price_max'])
                            <input type="hidden" name="price_max" value="{{ $filters['price_max'] }}">
                        @endif
                        @if ($filters['in_stock'])
                            <input type="hidden" name="in_stock" value="1">
                        @endif
                        <label for="store-sort" class="text-sm font-medium text-neutral-700">Sort</label>
                        <select id="store-sort" name="sort" onchange="this.form.submit()" class="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm focus:border-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-100">
                            @foreach ($sortOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['sort'] ?? 'featured') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>
            </div>

            <div class="grid gap-8 lg:grid-cols-[17rem_1fr]">
                <aside class="hidden lg:block">
                    <div class="sticky top-36">
                        <x-storefront.discovery-filters
                            :filters="$filters"
                            :brand-options="$brandOptions"
                            :category-options="$categoryOptions"
                            :attribute-filters="$attributeFilters"
                            :show-category-filter="true"
                            :clear-url="$clearUrl"
                        />
                    </div>
                </aside>

                <div>
                    @if ($hasResults)
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                            @foreach ($products as $product)
                                <x-storefront.product-card :product="$product" :show-vendor="false" :is-wishlisted="in_array($product->id, $wishlistedProductIds ?? [], true)" />
                            @endforeach
                        </div>

                        <div class="mt-8">
                            {{ $products->links() }}
                        </div>
                    @else
                        <div class="rounded-lg border border-dashed border-neutral-300 bg-white p-8 text-center">
                            <h2 class="text-xl font-semibold text-neutral-950">
                                @if ($storeCategories->isEmpty() && blank($filters['q']) && $activeFilterCount === 0)
                                    This store doesn't have any products available yet.
                                @else
                                    No products match these filters.
                                @endif
                            </h2>
                            <p class="mt-2 text-sm leading-6 text-neutral-600">
                                @if ($storeCategories->isEmpty() && blank($filters['q']) && $activeFilterCount === 0)
                                    Seller identity is available now, and products can appear here once approved.
                                @else
                                    Try adjusting your filters.
                                @endif
                            </p>
                            @if ($activeFilterCount || filled($filters['q']))
                                <x-button :href="$clearUrl" variant="secondary" class="mt-5">Clear Filters</x-button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <section id="about" class="mt-10 rounded-lg border border-neutral-200 bg-white p-6 shadow-soft">
                <h2 class="text-xl font-semibold text-neutral-950">About {{ $vendor->display_name }}</h2>
                <p class="mt-4 max-w-3xl whitespace-pre-line text-sm leading-7 text-neutral-700">{{ $vendor->description ?: 'This seller is preparing their public store description.' }}</p>
                <p class="mt-4 text-xs text-neutral-500">Selling on {{ config('app.name') }} since {{ $vendor->created_at->format('M Y') }}.</p>
            </section>
        </x-container>
    </section>

    <div class="fixed inset-0 z-50 hidden bg-neutral-950/40 lg:hidden" data-discovery-filters>
        <div class="ml-auto flex h-full w-full max-w-sm flex-col bg-white">
            <div class="flex h-16 items-center justify-between border-b border-neutral-200 px-4">
                <p class="font-semibold text-neutral-950">Filters</p>
                <button type="button" data-discovery-filters-close class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-neutral-200 text-neutral-700" aria-label="Close filters">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto p-5">
                <x-storefront.discovery-filters
                    :filters="$filters"
                    :brand-options="$brandOptions"
                    :category-options="$categoryOptions"
                    :attribute-filters="$attributeFilters"
                    :show-category-filter="true"
                    :clear-url="$clearUrl"
                />
            </div>
        </div>
    </div>
@endsection

@pushOnce('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const drawer = document.querySelector('[data-discovery-filters]');
            const open = document.querySelector('[data-discovery-filters-open]');
            const close = document.querySelector('[data-discovery-filters-close]');

            open?.addEventListener('click', () => drawer?.classList.remove('hidden'));
            close?.addEventListener('click', () => drawer?.classList.add('hidden'));
            drawer?.addEventListener('click', (event) => {
                if (event.target === drawer) {
                    drawer.classList.add('hidden');
                }
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    drawer?.classList.add('hidden');
                }
            });

            document.querySelectorAll('[data-filter-search]').forEach((input) => {
                input.addEventListener('input', () => {
                    const target = document.getElementById(input.dataset.filterSearch);
                    target?.querySelectorAll('[data-filter-option]').forEach((option) => {
                        option.classList.toggle('hidden', ! option.textContent.toLowerCase().includes(input.value.toLowerCase()));
                    });
                });
            });
        });
    </script>
@endPushOnce
