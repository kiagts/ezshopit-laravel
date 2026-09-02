@props([
    'rating' => 0,
    'count' => null,
    'size' => 'sm',
])

@php
    $rounded = (float) $rating;
    $sizes = [
        'sm' => 'h-4 w-4',
        'md' => 'h-5 w-5',
        'lg' => 'h-6 w-6',
    ];
    $iconSize = $sizes[$size] ?? $sizes['sm'];
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1']) }}>
    <span class="inline-flex items-center" aria-hidden="true">
        @for ($i = 1; $i <= 5; $i++)
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="{{ $i <= round($rounded) ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.7" class="{{ $iconSize }} {{ $i <= round($rounded) ? 'text-amber-400' : 'text-neutral-300' }}">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 2.5l2.91 5.9 6.51.95-4.71 4.59 1.11 6.48L12 17.36l-5.82 3.06 1.11-6.48-4.71-4.59 6.51-.95L12 2.5z" />
            </svg>
        @endfor
    </span>
    <span class="sr-only">{{ number_format($rounded, 1) }} out of 5 stars</span>
    @if ($count !== null)
        <span class="text-sm font-medium text-neutral-700">{{ number_format($rounded, 1) }}</span>
        <span class="text-sm text-neutral-500">({{ number_format($count) }})</span>
    @endif
</span>
