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
    <span {{ $attributes->class(['inline-flex w-fit items-center rounded-sm px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset', $classes[$state] ?? $classes['out_of_stock']]) }}>
        {{ $labels[$state] ?? $labels['out_of_stock'] }}
    </span>
@endif
