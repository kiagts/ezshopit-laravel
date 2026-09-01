@props([
    'title',
    'description' => null,
])

<div {{ $attributes->class(['rounded-lg border border-dashed border-neutral-300 bg-white px-6 py-10 text-center']) }}>
    <h2 class="text-base font-semibold text-neutral-950">{{ $title }}</h2>

    @if ($description)
        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-neutral-500">{{ $description }}</p>
    @endif

    @if ($slot->isNotEmpty())
        <div class="mt-6 flex justify-center">
            {{ $slot }}
        </div>
    @endif
</div>
