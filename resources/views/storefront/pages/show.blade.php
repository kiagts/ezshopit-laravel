@extends('layouts.app')
@section('title', $page->seo_title ?: $page->title)
@section('description', $page->seo_description ?: $page->excerpt)
@push('head')<link rel="canonical" href="{{ route('storefront.pages.show', $page->slug) }}"><meta name="robots" content="{{ ($preview ?? false) ? 'noindex,nofollow' : 'index,follow' }}">@endpush
@section('content')
    @if ($preview ?? false)<div class="bg-amber-50 px-4 py-3 text-center text-sm font-semibold text-amber-900">Preview mode</div>@endif
    <main class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:py-16">
<header class="mb-10 max-w-3xl"><p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">{{ $page->page_type->label() }}</p><h1 class="mt-3 text-3xl font-bold tracking-tight text-neutral-950 sm:text-4xl">{{ $page->localized_title }}</h1>@if($page->excerpt)<p class="mt-4 text-lg leading-8 text-neutral-600">{{ $page->excerpt }}</p>@endif</header>
        <div class="space-y-8">
        @foreach($page->sections as $section)
            @if($section->type->value === 'faq')
                <section><h2 class="text-2xl font-semibold">{{ $section->title }}</h2><div class="mt-4 divide-y rounded-xl border">@foreach($section->faqItems as $faq)<details class="group p-5"><summary class="cursor-pointer font-semibold focus:outline-none focus:ring-2 focus:ring-emerald-500">{{ $faq->question }}</summary><p class="mt-3 leading-7 text-neutral-600">{{ $faq->answer }}</p></details>@endforeach</div></section>
            @elseif($section->type->value === 'callout')
                <section class="rounded-xl bg-emerald-50 p-6"><h2 class="text-xl font-semibold">{{ $section->title }}</h2><p class="mt-2 whitespace-pre-line leading-7 text-neutral-700">{{ $section->content }}</p>@if(data_get($section->settings,'cta_url'))<a class="mt-4 inline-flex font-semibold text-emerald-800 underline" href="{{ data_get($section->settings,'cta_url') }}">{{ data_get($section->settings,'cta_label','Learn more') }}</a>@endif</section>
            @else
                <section class="prose prose-neutral max-w-none"><h2>{{ $section->title }}</h2><p class="whitespace-pre-line leading-8">{{ $section->content }}</p>@if($section->type->value === 'image_text' && data_get($section->settings,'image'))<img class="rounded-xl" src="{{ data_get($section->settings,'image') }}" alt="{{ data_get($section->settings,'image_alt',$section->title) }}">@endif</section>
            @endif
        @endforeach
        </div>
    </main>
@endsection
