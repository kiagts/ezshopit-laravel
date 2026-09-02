@props(['price'])

@php
    use App\Support\Money;
    
    $effective = $price['effective'] ?? null;

    $base = $price['base'] ?? null;
    $sale = $price['sale'] ?? null;
@endphp

@if ($effective)
<div {{ $attributes->class('flex flex-col items-start') }}>
    {{-- Starting At / Reserved Space --}}
    <div class="h-4">
        @if ($price['from'] ?? false)
            <span class="block text-xs font-medium leading-4 text-neutral-500">
                Starting at
            </span>
        @endif
    </div>

    {{-- Current Selling Price --}}
    <div class="mt-1">
        <span class="text-[22px] font-bold leading-tight tracking-tight text-neutral-950">
            {{ Money::format($effective) }}
        </span>
    </div>
    {{-- Previous Price + Discount / Reserved Space --}}
    <div class="mt-1 flex h-6 items-center gap-2">
        @if ($sale && $base && $sale < $base)
            {{-- Previous Price --}}
            <span class="text-sm font-medium text-neutral-500 line-through">
                {{ Money::format($base) }}
            </span>

            {{-- Discount Percentage --}}
            @if ($price['discount'] ?? null)
                <span
                    class="rounded-md bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-600"
                >
                    {{ $price['discount'] }}% off
                </span>
            @endif
        @endif
    </div>
</div>
@else
    <p {{ $attributes->class('text-sm font-medium text-neutral-500') }}>Price unavailable</p>
@endif
