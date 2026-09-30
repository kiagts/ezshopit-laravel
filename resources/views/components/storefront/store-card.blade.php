@props([
    'vendor',
    'href' => null,
])

@php
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Facades\Storage;

    $href ??= Route::has('storefront.stores.show')
        ? route('storefront.stores.show', $vendor)
        : null;

    /*
    |--------------------------------------------------------------------------
    | Store Stats
    |--------------------------------------------------------------------------
    */

    $rating = $vendor->rating ?? null;

    $reviewCount = $vendor->review_count ?? 0;

    $productCount = $vendor->products_count ?? 0;
@endphp


<article
    {{ $attributes->class([
        'group relative flex min-h-[150px] flex-col rounded-xl border border-neutral-200 bg-white p-4 transition duration-200 hover:border-neutral-300 hover:shadow-md'
    ]) }}
>

    @if ($href)
        <a
            href="{{ $href }}"
            class="absolute inset-0 z-10"
            aria-label="Visit {{ $vendor->display_name }}"
        ></a>
    @endif


    {{-- ========================================================= --}}
    {{-- STORE INFORMATION --}}
    {{-- ========================================================= --}}
    <div class="pointer-events-none relative z-20 flex items-start gap-3">

        {{-- Store Logo --}}
        <div
            class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-neutral-100 text-xl font-bold text-neutral-700 ring-1 ring-neutral-200"
        >

            @if ($vendor->logo_path)

                <img
                    src="{{ Storage::url($vendor->logo_path) }}"
                    alt="{{ $vendor->display_name }} logo"
                    class="h-full w-full object-cover"
                >

            @else

                <span>
                    {{ str($vendor->display_name)->substr(0, 1)->upper() }}
                </span>

            @endif

        </div>


        {{-- Store Details --}}
        <div class="min-w-0 flex-1">

            {{-- Name --}}
            <h3 class="truncate text-sm font-semibold text-neutral-950">
                {{ $vendor->display_name }}
            </h3>


            {{-- Rating --}}
            @if ($rating !== null)
            <div class="mt-2 flex items-center gap-1.5">

                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                    class="h-4 w-4 shrink-0 text-amber-400"
                >
                    <path
                        d="M12 2.5l2.91 5.9 6.51.95-4.71 4.59 1.11 6.48L12 17.36l-5.82 3.06 1.11-6.48-4.71-4.59 6.51-.95L12 2.5z"
                    />
                </svg>

                <span class="text-xs font-semibold text-neutral-700">
                    {{ number_format($rating, 1) }}
                </span>

                @if ($reviewCount > 0)

                    <span class="text-[11px] text-neutral-400">
                        ({{ number_format($reviewCount) }})
                    </span>

                @endif

            </div>


            @endif

            {{-- Product Count --}}
            <p class="mt-2 text-xs text-neutral-500">
                {{ number_format($productCount) }}
                {{ $productCount === 1 ? 'Product' : 'Products' }}
            </p>

        </div>

    </div>


    {{-- ========================================================= --}}
    {{-- VISIT STORE --}}
    {{-- ========================================================= --}}
    @if ($href)

        <div class="pointer-events-none relative z-20 mt-auto pt-5">

            <span
                class="inline-flex items-center gap-2 text-xs font-semibold text-emerald-700 transition group-hover:gap-3 group-hover:text-emerald-800"
            >
                Visit Store

                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    class="h-4 w-4"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M5 12h14M13 6l6 6-6 6"
                    />
                </svg>

            </span>

        </div>

    @endif

</article>