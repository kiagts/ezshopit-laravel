@props([
    'type' => 'info',
])

@php
    $types = [
        'info' => 'border-blue-200 bg-blue-50 text-blue-950',
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-950',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-950',
        'danger' => 'border-red-200 bg-red-50 text-red-950',
    ];
@endphp

<div role="alert" {{ $attributes->class(['rounded-md border px-4 py-3 text-sm', $types[$type] ?? $types['info']]) }}>
    {{ $slot }}
</div>
