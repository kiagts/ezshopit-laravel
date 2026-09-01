@props(['price'])

@php
    use App\Support\Money;
    
    $effective = $price['effective'] ?? null;
    $base = $price['base'] ?? null;
    $sale = $price['sale'] ?? null;
@endphp

@if ($effective)
    <div {{ $attributes->class('flex flex-wrap items-baseline gap-x-2 gap-y-1') }}>
        <span class="text-base font-semibold text-neutral-950">
            @if ($price['from'] ?? false)
                From
            @endif
            {{ Money::format($effective) }}
        </span>

        @if ($sale && $base && $sale < $base)
            <span class="text-sm text-neutral-400 line-through">{{ Money::format($base) }}</span>
            @if ($price['discount'] ?? null)
                <span class="rounded-sm bg-rose-50 px-1.5 py-0.5 text-xs font-medium text-rose-700">{{ $price['discount'] }}% off</span>
            @endif
        @endif
    </div>
@else
    <p {{ $attributes->class('text-sm font-medium text-neutral-500') }}>Price unavailable</p>
@endif
