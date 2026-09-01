@props([
    'label' => null,
    'name' => null,
])

<label class="block">
    @if ($label)
        <span class="mb-2 block text-sm font-medium text-neutral-800">{{ $label }}</span>
    @endif

    <textarea
        name="{{ $name }}"
        {{ $attributes->class([
            'block min-h-28 w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-950 shadow-sm transition placeholder:text-neutral-400 focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100',
            'border-red-500 focus:border-red-600 focus:ring-red-100' => $name && $errors->has($name),
        ]) }}
    >{{ $slot }}</textarea>

    @if ($name && $errors->has($name))
        <span class="mt-2 block text-sm text-red-700">{{ $errors->first($name) }}</span>
    @endif
</label>
