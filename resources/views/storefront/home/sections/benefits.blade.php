<section class="bg-white py-8 sm:py-10">
    <x-container>
        <div class="grid gap-4 rounded-2xl border border-neutral-200 bg-neutral-50 p-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($section['contentItems'] as $item)
                <div class="flex gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-emerald-700 shadow-sm">
                        @include('storefront.home.sections.partials.icon', ['icon' => $item['icon_key']])
                    </span>

                    <div>
                        <h2 class="text-sm font-semibold text-neutral-950">{{ $item['title'] }}</h2>
                        @if ($item['body'])
                            <p class="mt-1 text-xs leading-5 text-neutral-500">{{ $item['body'] }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </x-container>
</section>
