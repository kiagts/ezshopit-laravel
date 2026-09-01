@props([
    'category',
    'href' => null,
    'tone' => 'neutral',
])

@php
    use Illuminate\Support\Facades\Storage;

    $children = $category->activeChildren ?? collect();

    $toneClasses = match ($tone) {
        'blue' => 'bg-blue-50',
        'green' => 'bg-emerald-50',
        'pink' => 'bg-rose-50',
        'violet' => 'bg-violet-50',
        'amber' => 'bg-amber-50',
        'sky' => 'bg-sky-50',
        'orange' => 'bg-orange-50',
        default => 'bg-neutral-50',
    };

    $iconToneClasses = match ($tone) {
        'blue' => 'bg-white/80 text-blue-700',
        'green' => 'bg-white/80 text-emerald-700',
        'pink' => 'bg-white/80 text-rose-700',
        'violet' => 'bg-white/80 text-violet-700',
        'amber' => 'bg-white/80 text-amber-700',
        'sky' => 'bg-white/80 text-sky-700',
        'orange' => 'bg-white/80 text-orange-700',
        default => 'bg-white/80 text-neutral-700',
    };

    $imagePath = $category->image_path ?: $category->icon_path;
@endphp


<article
    {{ $attributes->class([
        'group relative min-h-[310px] overflow-hidden rounded-2xl border border-neutral-100 p-5 transition duration-300 hover:-translate-y-1 hover:shadow-lg',
        $toneClasses,
    ]) }}
>

    @if ($href)

        <a
            href="{{ $href }}"
            class="absolute inset-0 z-10"
            aria-label="Browse {{ $category->name }}"
        ></a>

    @endif


    {{-- ========================================================= --}}
    {{-- TOP ICON --}}
    {{-- ========================================================= --}}
    <div
        class="relative z-20 flex h-9 w-9 items-center justify-center rounded-lg shadow-sm ring-1 ring-black/5 {{ $iconToneClasses }}"
    >

        @if ($category->icon_path)

            <img
                src="{{ Storage::url($category->icon_path) }}"
                alt="{{ $category->name }}"
                class="h-5 w-5 object-contain"
            >

        @else

            <span class="text-sm font-bold">
                {{ str($category->name)->substr(0, 1)->upper() }}
            </span>

        @endif

    </div>


    {{-- ========================================================= --}}
    {{-- CATEGORY CONTENT --}}
    {{-- ========================================================= --}}
    <div class="relative z-20 mt-5 max-w-[72%]">

        <h3 class="text-base font-bold leading-6 text-neutral-950">
            {{ $category->name }}
        </h3>


        @if ($children->isNotEmpty())

            <p class="mt-2 line-clamp-2 text-xs leading-5 text-neutral-500">
                {{ $children->take(3)->pluck('name')->implode(', ') }}
            </p>

        @endif


        @if ($href)

            <span
                class="mt-6 inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700 transition group-hover:gap-2.5"
            >
                Explore

                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    class="h-3.5 w-3.5"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M5 12h14M13 6l6 6-6 6"
                    />
                </svg>
            </span>

        @endif

    </div>


    {{-- ========================================================= --}}
    {{-- CATEGORY IMAGE --}}
    {{-- ========================================================= --}}
    @if ($imagePath)

        <div
            class="pointer-events-none absolute bottom-0 right-0 h-[46%] w-[62%]"
        >

            <img
                src="{{ Storage::url($imagePath) }}"
                alt="{{ $category->name }}"
                class="h-full w-full object-contain object-bottom-right transition duration-500 group-hover:scale-105"
            >

        </div>

    @else

        <div
            class="pointer-events-none absolute bottom-5 right-5 flex h-24 w-24 items-center justify-center rounded-full bg-white/50 text-4xl font-bold text-neutral-300"
        >
            {{ str($category->name)->substr(0, 1)->upper() }}
        </div>

    @endif

</article>