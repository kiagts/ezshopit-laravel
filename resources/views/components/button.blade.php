@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-md border font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:pointer-events-none disabled:opacity-50';

    $variants = [
        'primary' => 'border-brand-700 bg-brand-700 text-white shadow-sm hover:bg-brand-800 focus-visible:outline-brand-700',
        'secondary' => 'border-neutral-300 bg-white text-neutral-900 shadow-sm hover:bg-neutral-100 focus-visible:outline-brand-700',
        'ghost' => 'border-transparent bg-transparent text-neutral-700 hover:bg-neutral-100 focus-visible:outline-brand-700',
        'danger' => 'border-red-700 bg-red-700 text-white shadow-sm hover:bg-red-800 focus-visible:outline-red-700',
    ];

    $sizes = [
        'sm' => 'h-9 px-3 text-sm',
        'md' => 'h-10 px-4 text-sm',
        'lg' => 'h-11 px-5 text-base',
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$base, $variants[$variant] ?? $variants['primary'], $sizes[$size] ?? $sizes['md']]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class([$base, $variants[$variant] ?? $variants['primary'], $sizes[$size] ?? $sizes['md']]) }}>
        {{ $slot }}
    </button>
@endif
