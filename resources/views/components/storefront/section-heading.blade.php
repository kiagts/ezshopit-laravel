@props([
    'eyebrow' => null,
    'title',
    'description' => null,
])

<div {{ $attributes->class('flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between') }}>
    <div class="max-w-2xl">
        @if ($eyebrow)
            <p class="text-xs font-semibold uppercase tracking-normal text-brand-700">{{ $eyebrow }}</p>
        @endif
        <h2 class="text-2xl font-semibold tracking-normal text-neutral-950 sm:text-3xl">{{ $title }}</h2>
        @if ($description)
            <p class="mt-2 text-sm leading-6 text-neutral-600">{{ $description }}</p>
        @endif
    </div>

    @isset($action)
        <div>{{ $action }}</div>
    @endisset
</div>
