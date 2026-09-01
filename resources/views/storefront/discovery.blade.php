@extends('layouts.app')

@php
    $isSearch = $mode === 'search';
    $hasResults = $products && $products->total() > 0;
    $clearUrl = $isSearch && filled($filters['q'] ?? null)
        ? route('storefront.search', ['q' => $filters['q']])
        : url()->current();
@endphp

@section('title', $isSearch ? 'Search Products' : $category->name)
@section('description', $isSearch ? 'Search marketplace products from approved sellers.' : ($category->seo_description ?: $category->description ?: 'Browse marketplace products.'))

@section('content')
    <section class="bg-white">
    <x-container class="py-8">

        {{-- Breadcrumb --}}
        @if (! $isSearch)
            <x-storefront.breadcrumb :items="$breadcrumbs" />
        @endif

        {{-- Single Hero Banner --}}
        <div
            class="relative mt-6 min-h-[220px] overflow-hidden rounded-2xl bg-neutral-900 sm:min-h-[250px] lg:min-h-[280px]"
        >
            {{-- Background Image --}}
            <img
                src="https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=1800&q=85"
                alt=""
                class="absolute inset-0 h-full w-full object-cover"
            >

            {{-- Overlay --}}
            <div class="absolute inset-0 bg-neutral-950/65"></div>

            {{-- Gradient --}}
            <div
                class="absolute inset-0 bg-gradient-to-r from-neutral-950/90 via-neutral-950/60 to-transparent"
            ></div>

            {{-- Content --}}
            <div
                class="relative z-10 flex min-h-[220px] items-center px-6 py-8 sm:min-h-[250px] sm:px-8 lg:min-h-[280px] lg:px-10"
            >
                <div class="max-w-2xl">
                    @if ($isSearch)
                        <p
                            class="text-xs font-semibold uppercase tracking-wider text-white/70"
                        >
                            Search
                        </p>
                        @if (filled($filters['q'] ?? null))
                            <h1
                                class="mt-2 text-3xl font-semibold leading-tight text-white sm:text-4xl"
                            >
                                Search results for
                                &ldquo;{{ $filters['q'] }}&rdquo;
                            </h1>
                        @else
                            <h1
                                class="mt-2 text-3xl font-semibold leading-tight text-white sm:text-4xl"
                            >
                                Search the Marketplace
                            </h1>
                        @endif
                    @else
                        <p
                            class="text-xs font-semibold uppercase tracking-wider text-white"
                        >
                            Shop Category
                        </p>
                        <h1
                            class="mt-2 text-3xl font-semibold leading-tight text-white sm:text-4xl lg:text-5xl"
                        >
                            {{ $category->name }}
                        </h1>
                        @if (filled($category->description ?? null))
                            <p
                                class="mt-3 max-w-xl text-sm leading-6 text-white/75 sm:text-base"
                            >
                                {{ $category->description }}
                            </p>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </x-container>
</section>

    <section class="bg-white py-8">
        <x-container>
            @if ($isSearch && blank($filters['q'] ?? null))
                <div class="max-w-2xl rounded-lg border border-neutral-200 bg-white p-8 shadow-soft">
                    <h2 class="text-xl font-semibold text-neutral-950">Start with a search term</h2>
                    <p class="mt-2 text-sm leading-6 text-neutral-600">Search products by name, description, brand, store, or SKU.</p>
                    <form method="GET" action="{{ route('storefront.search') }}" class="mt-5 flex gap-3">
                        <input name="q" type="search" placeholder="Search products" class="h-11 flex-1 rounded-md border border-neutral-300 px-3 text-sm focus:border-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-100">
                        <x-button type="submit">Search</x-button>
                    </form>
                </div>
            @else
                <x-storefront.active-filters :filters="$activeFilters" :clear-url="$clearUrl" class="mb-5" />

                <div class="mb-5 flex flex-col gap-3 rounded-lg border border-neutral-200 bg-white p-4 shadow-soft md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-sm font-medium text-neutral-950">{{ $products->total() }} {{ str('result')->plural($products->total()) }}</p>
                        <p class="text-xs text-neutral-500">Filters are saved in the URL for sharing.</p>
                    </div>

                    <div class="flex gap-3">
                        <button type="button" data-discovery-filters-open class="inline-flex h-10 items-center justify-center rounded-md border border-neutral-300 bg-white px-4 text-sm font-medium text-neutral-700 lg:hidden">
                            Filters @if ($activeFilterCount) ({{ $activeFilterCount }}) @endif
                        </button>

                        <form method="GET" action="{{ url()->current() }}" class="flex items-center gap-2">
                            @if (filled($filters['q'] ?? null))
                                <input type="hidden" name="q" value="{{ $filters['q'] }}">
                            @endif
                            @foreach ($filters['brand'] as $brand)
                                <input type="hidden" name="brand[]" value="{{ $brand }}">
                            @endforeach
                            @foreach ($filters['category'] as $selectedCategory)
                                <input type="hidden" name="category[]" value="{{ $selectedCategory }}">
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
                            <label for="sort" class="text-sm font-medium text-neutral-700">Sort</label>
                            <select id="sort" name="sort" onchange="this.form.submit()" class="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm focus:border-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-100">
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
                                :show-category-filter="$isSearch"
                            />
                        </div>
                    </aside>

                    <div>
                        @if ($hasResults)
                            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                                @foreach ($products as $product)
                                    <x-storefront.product-card :product="$product" :is-wishlisted="in_array($product->id, $wishlistedProductIds ?? [], true)" />
                                @endforeach
                            </div>

                            <div class="mt-8">
                                {{ $products->links() }}
                            </div>
                        @else
                            <div class="rounded-lg border border-dashed border-neutral-300 bg-white p-8 text-center">
                                <h2 class="text-xl font-semibold text-neutral-950">
                                    @if ($isSearch)
                                        We could not find a match.
                                    @else
                                        No products match these filters.
                                    @endif
                                </h2>
                                <p class="mt-2 text-sm leading-6 text-neutral-600">
                                    @if ($isSearch)
                                        Try checking the spelling, using fewer words, or browsing a category.
                                    @else
                                        Try removing one or more filters.
                                    @endif
                                </p>
                                <x-button :href="$clearUrl" variant="secondary" class="mt-5">Clear Filters</x-button>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
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
                    :show-category-filter="$isSearch"
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
