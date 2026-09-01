@props([
    'tone' => 'neutral',
])

@php
    $tones = [
        'neutral' => 'bg-neutral-100 text-neutral-700 ring-neutral-200',
        'brand' => 'bg-brand-50 text-brand-800 ring-brand-200',
        'success' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        'warning' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'danger' => 'bg-red-50 text-red-800 ring-red-200',
        'dark' => 'bg-neutral-900 text-white ring-neutral-900',
    ];
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset', $tones[$tone] ?? $tones['neutral']]) }}>
    {{ $slot }}
</span>
