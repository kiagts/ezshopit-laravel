@props(['label' => config('marketplace.name')])

<div {{ $attributes->class('flex h-full min-h-40 w-full items-center justify-center bg-neutral-100') }}>
    <span class="px-4 text-center text-sm font-medium text-neutral-400">{{ $label }}</span>
</div>
