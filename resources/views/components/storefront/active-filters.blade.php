@props([
    'filters' => collect(),
    'clearUrl' => url()->current(),
])

@if ($filters->isNotEmpty())
    <div {{ $attributes->class('flex flex-wrap items-center gap-2') }}>
        @foreach ($filters as $filter)
            <a href="{{ $filter['url'] }}" class="inline-flex items-center gap-1 rounded-md border border-neutral-200 bg-white px-3 py-1.5 text-sm font-medium text-neutral-700 hover:border-neutral-300 hover:text-neutral-950">
                {{ $filter['label'] }}
                <span aria-hidden="true">&times;</span>
            </a>
        @endforeach
        <a href="{{ $clearUrl }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">Clear all</a>
    </div>
@endif
