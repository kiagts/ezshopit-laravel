@props([
    'size' => 'xl',
])

@php
    $sizes = [
        'lg' => 'max-w-5xl',
        'xl' => 'max-w-7xl',
        'full' => 'max-w-none',
    ];
@endphp

<div {{ $attributes->class(['mx-auto w-full px-4 sm:px-6 lg:px-8', $sizes[$size] ?? $sizes['xl']]) }}>
    {{ $slot }}
</div>
