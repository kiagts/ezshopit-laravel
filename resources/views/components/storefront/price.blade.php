@props(['price'])

@php
    use App\Support\Money;
    
    $effective = $price['effective'] ?? null;
    $base = $price['base'] ?? null;
    $sale = $price['sale'] ?? null;
@endphp

@if ($effective)
    <div {{ $attributes->class('flex flex-col items-start') }}>
        {{-- Starting At --}}
        @if ($price['from'] ?? false)
            <span class="mb-0.5 text-xs font-medium leading-none text-neutral-500">
                Starting at
            </span>
        @endif
        {{-- Current Price --}}
        <span class="text-[22px] font-bold leading-tight tracking-tight text-neutral-950">
            {{ Money::format($effective) }}
        </span>
        {{-- Previous Price + Discount --}}
        @if ($sale && $base && $sale < $base)
            <div class="mt-1 flex items-center gap-2">
                {{-- Previous Price --}}
                <span class="text-sm font-medium text-neutral-500 line-through">
                    {{ Money::format($base) }}
                </span>

                {{-- Discount Percentage --}}
                @if ($price['discount'] ?? null)
                    <span class="rounded-sm bg-rose-50 px-1.5 py-0.5 text-xs font-medium text-rose-700">
                        {{ $price['discount'] }}% off
                    </span>
                @endif
            </div>
        @endif
    </div>
@else
    <p {{ $attributes->class('text-sm font-medium text-neutral-500') }}>Price unavailable</p>
@endif
