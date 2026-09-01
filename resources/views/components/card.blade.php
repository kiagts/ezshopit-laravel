@props([
    'padding' => 'md',
])

@php
    $paddingClasses = [
        'none' => '',
        'sm' => 'p-4',
        'md' => 'p-6',
        'lg' => 'p-8',
    ];
@endphp

<section {{ $attributes->class(['rounded-lg border border-neutral-200 bg-white shadow-sm', $paddingClasses[$padding] ?? $paddingClasses['md']]) }}>
    {{ $slot }}
</section>
