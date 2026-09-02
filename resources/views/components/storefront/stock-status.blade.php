@props(['state' => 'in_stock'])

@php
    $labels = [
        'in_stock'     => 'In stock',
        'low_stock'    => 'Limited stock',
        'out_of_stock' => 'Out of stock',
    ];

    $classes = [
        'in_stock' => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
        'low_stock' => 'bg-amber-50 text-amber-800 ring-amber-100',
        'out_of_stock' => 'bg-neutral-100 text-neutral-600 ring-neutral-200',
    ];
@endphp

@if ($state !== 'in_stock')
    <span {{ $attributes->class(['absolute left-3 top-3 inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-semibold', $classes[$state] ?? $classes['out_of_stock']]) }}>
        {{ $labels[$state] ?? $labels['out_of_stock'] }}
    </span>
@endif
