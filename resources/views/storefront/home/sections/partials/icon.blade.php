@php
    $paths = match ($icon ?? 'shield') {
        'star' => ['M12 3.5 14.6 8.8l5.8.8-4.2 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8-4.2-4.1 5.8-.8L12 3.5Z'],
        'tag' => ['M4 11.5V5h6.5L20 14.5 14.5 20 4 9.5Z', 'M8 8h.01'],
        'truck' => ['M3 7h11v8H3z', 'M14 10h4l3 3v2h-7z', 'M7 18a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z', 'M17 18a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z'],
        'sparkles' => ['M12 3l1.5 4.5L18 9l-4.5 1.5L12 15l-1.5-4.5L6 9l4.5-1.5L12 3Z', 'M5 14l.8 2.2L8 17l-2.2.8L5 20l-.8-2.2L2 17l2.2-.8L5 14Z'],
        'store' => ['M4 10h16l-1.5-5h-13L4 10Z', 'M5 10v9h14v-9', 'M9 19v-5h6v5'],
        default => ['M12 3 4.5 6v5.25c0 4.65 3.18 8.55 7.5 9.75 4.32-1.2 7.5-5.1 7.5-9.75V6L12 3Z', 'm9.5 12 1.7 1.7 3.6-3.9'],
    };
@endphp

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true">
    @foreach ($paths as $path)
        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}" />
    @endforeach
</svg>
