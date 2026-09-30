@extends('layouts.app')

@section('title', ($isHomepagePreview ?? false) ? 'Homepage Preview' : 'Home')

@if ($isHomepagePreview ?? false)
    @push('head')
        <meta name="robots" content="noindex,nofollow">
    @endpush
@endif

@section('content')
    @if ($isHomepagePreview ?? false)
        <section class="border-b border-amber-200 bg-amber-50">
            <x-container class="py-3">
                <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                    <p class="font-medium text-amber-900">Admin preview: disabled, scheduled, and expired saved sections are visible here.</p>
                    <a href="{{ route('admin.homepage.index') }}" class="font-semibold text-amber-900 underline">Back to homepage manager</a>
                </div>
            </x-container>
        </section>
    @endif

    @forelse ($homepageSections as $section)
        @include($section['view'], ['section' => $section])
    @empty
        <section class="bg-white py-16">
            <x-container>
                <div class="rounded-xl border border-dashed border-neutral-300 bg-neutral-50 p-10 text-center">
                    <h1 class="text-2xl font-semibold text-neutral-950">{{ config('marketplace.name') }}</h1>
                    <p class="mt-2 text-sm text-neutral-600">Homepage sections have not been configured yet.</p>
                </div>
            </x-container>
        </section>
    @endforelse
@endsection
