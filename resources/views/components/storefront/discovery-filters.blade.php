@props([
    'filters',
    'brandOptions' => collect(),
    'categoryOptions' => collect(),
    'attributeFilters' => collect(),
    'showCategoryFilter' => false,
    'clearUrl' => url()->current().(filled($filters['q'] ?? null) ? '?'.http_build_query(['q' => $filters['q']]) : ''),
])

<form method="GET" action="{{ url()->current() }}" {{ $attributes->class('space-y-7') }}>
    @if (filled($filters['q'] ?? null))
        <input type="hidden" name="q" value="{{ $filters['q'] }}">
    @endif

    <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'featured' }}">

    @if ($showCategoryFilter && $categoryOptions->isNotEmpty())
        <section>
            <h2 class="text-sm font-semibold text-neutral-950">Category</h2>
            <div class="mt-3 space-y-2">
                @foreach ($categoryOptions as $category)
                    <label class="flex cursor-pointer items-center gap-3 text-sm text-neutral-700">
                        <input type="checkbox" name="category[]" value="{{ $category->slug }}" @checked(in_array($category->slug, $filters['category'] ?? [], true)) class="h-4 w-4 rounded border-neutral-300 text-brand-700 focus:ring-brand-700">
                        <span>{{ $category->name }}</span>
                    </label>
                @endforeach
            </div>
        </section>
    @endif

    @if ($brandOptions->isNotEmpty())
        <section>
            <h2 class="text-sm font-semibold text-neutral-950">Brand</h2>
            @if ($brandOptions->count() > 8)
                <input type="search" data-filter-search="brand-options" placeholder="Search brands" class="mt-3 h-9 w-full rounded-md border border-neutral-300 bg-white px-3 text-sm focus:border-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-100">
            @endif
            <div id="brand-options" class="mt-3 space-y-2">
                @foreach ($brandOptions as $brand)
                    <label class="flex cursor-pointer items-center gap-3 text-sm text-neutral-700" data-filter-option>
                        <input type="checkbox" name="brand[]" value="{{ $brand->slug }}" @checked(in_array($brand->slug, $filters['brand'] ?? [], true)) class="h-4 w-4 rounded border-neutral-300 text-brand-700 focus:ring-brand-700">
                        <span>{{ $brand->name }}</span>
                    </label>
                @endforeach
            </div>
        </section>
    @endif

    <section>
        <h2 class="text-sm font-semibold text-neutral-950">Price</h2>
        <div class="mt-3 grid grid-cols-2 gap-3">
            <label class="text-xs font-medium uppercase tracking-normal text-neutral-500">
                Min
                <input name="price_min" value="{{ $filters['price_min'] ?? '' }}" inputmode="decimal" placeholder="0.00" class="mt-1 h-10 w-full rounded-md border border-neutral-300 bg-white px-3 text-sm font-normal normal-case tracking-normal text-neutral-900 focus:border-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-100">
            </label>
            <label class="text-xs font-medium uppercase tracking-normal text-neutral-500">
                Max
                <input name="price_max" value="{{ $filters['price_max'] ?? '' }}" inputmode="decimal" placeholder="500.00" class="mt-1 h-10 w-full rounded-md border border-neutral-300 bg-white px-3 text-sm font-normal normal-case tracking-normal text-neutral-900 focus:border-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-100">
            </label>
        </div>
    </section>

    @foreach ($attributeFilters as $group)
        @php($attribute = $group['attribute'])
        <section>
            <h2 class="text-sm font-semibold text-neutral-950">{{ $attribute->name }}</h2>
            <div class="mt-3 space-y-2">
                @foreach ($group['options']->take(12) as $option)
                    <label class="flex cursor-pointer items-center gap-3 text-sm text-neutral-700">
                        <input type="checkbox" name="attributes[{{ $attribute->code }}][]" value="{{ $option['code'] }}" @checked(in_array($option['code'], $filters['attributes'][$attribute->code] ?? [], true)) class="h-4 w-4 rounded border-neutral-300 text-brand-700 focus:ring-brand-700">
                        @if (($option['hex'] ?? null) && $attribute->code === 'color')
                            <span class="h-4 w-4 rounded-full border border-neutral-200" style="background-color: {{ $option['hex'] }}"></span>
                        @endif
                        <span>{{ $option['label'] }}</span>
                    </label>
                @endforeach
            </div>
        </section>
    @endforeach

    <section>
        <h2 class="text-sm font-semibold text-neutral-950">Availability</h2>
        <label class="mt-3 flex cursor-pointer items-center gap-3 text-sm text-neutral-700">
            <input type="checkbox" name="in_stock" value="1" @checked($filters['in_stock'] ?? false) class="h-4 w-4 rounded border-neutral-300 text-brand-700 focus:ring-brand-700">
            <span>In Stock Only</span>
        </label>
    </section>

    <div class="flex gap-3 border-t border-neutral-200 pt-5">
        <x-button type="submit" class="flex-1">Apply</x-button>
        <x-button :href="$clearUrl" variant="secondary">Clear</x-button>
    </div>
</form>
