@extends('layouts.app')

@section('title', 'Application Foundation')

@section('content')
    <x-container class="py-14 sm:py-20">
        <div class="max-w-3xl">
            <x-badge tone="brand">Foundation</x-badge>
            <h1 class="mt-5 text-4xl font-semibold tracking-normal text-neutral-950 sm:text-5xl">
                Marketplace application structure is ready.
            </h1>
            <p class="mt-5 text-lg leading-8 text-neutral-600">
                This Laravel application now has the base layout, shared UI primitives, route conventions, and architecture notes needed to begin marketplace development feature by feature.
            </p>
        </div>

        <div class="mt-12 grid gap-6 lg:grid-cols-3">
            <x-card>
                <h2 class="text-base font-semibold text-neutral-950">Public marketplace</h2>
                <p class="mt-3 text-sm leading-6 text-neutral-600">Blade-rendered customer and storefront pages remain under Laravel-owned web routes.</p>
            </x-card>

            <x-card>
                <h2 class="text-base font-semibold text-neutral-950">Vendor workspace</h2>
                <p class="mt-3 text-sm leading-6 text-neutral-600">Future vendor routes are isolated under the vendor route file and URL prefix.</p>
            </x-card>

            <x-card>
                <h2 class="text-base font-semibold text-neutral-950">Admin operations</h2>
                <p class="mt-3 text-sm leading-6 text-neutral-600">Future admin routes are isolated under the admin route file and URL prefix.</p>
            </x-card>
        </div>

        <div class="mt-10">
            <x-empty-state
                title="Feature modules intentionally not built yet"
                description="Products, vendors, cart, checkout, orders, payouts, reviews, promotions, dashboards, and CMS are reserved for later prompts."
            />
        </div>
    </x-container>
@endsection
