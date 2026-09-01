@props(['items' => collect()])

<nav {{ $attributes->class('flex flex-wrap items-center gap-2 text-sm text-neutral-500') }} aria-label="Breadcrumb">
    <a href="{{ route('home') }}" class="hover:text-neutral-950">Home</a>
    @foreach ($items as $item)
        <span aria-hidden="true">/</span>
        @if (! $loop->last)
            <a href="{{ route('storefront.categories.show', $item) }}" class="hover:text-neutral-950">{{ $item->name }}</a>
        @else
            <span class="font-medium text-neutral-800" aria-current="page">{{ $item->name }}</span>
        @endif
    @endforeach
</nav>
