@extends('layouts.vendor')

@section('title', 'Product Inventory')

@section('content')
    <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <x-badge :tone="$product->status->badgeTone()">{{ $product->status->label() }}</x-badge>
            <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">{{ $product->name }} Inventory</h1>
            <p class="mt-2 text-sm text-neutral-600">Manage operational stock without changing product approval status.</p>
        </div>
        <x-button :href="route('vendor.inventory.index')" variant="secondary">All Inventory</x-button>
    </div>

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    @if ($errors->any())
        <x-alert type="danger" class="mb-6">{{ $errors->first() }}</x-alert>
    @endif

    <div class="space-y-6">
        @foreach ($product->variants as $variant)
            @php($inventory = $variant->inventory)
            <section class="rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
                <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-neutral-950">{{ $variant->displayName() }}</h2>
                        <p class="mt-1 font-mono text-xs text-neutral-500">{{ $variant->sku }}</p>
                    </div>
                    <x-button :href="route('vendor.products.variants.inventory.history', [$product, $variant])" variant="secondary" size="sm">Movement History</x-button>
                </div>

                <x-vendor.inventory.summary :inventory="$inventory" />
                <div class="mt-5">
                    <x-vendor.inventory.operations :product="$product" :variant="$variant" :inventory="$inventory" />
                </div>
            </section>
        @endforeach
    </div>
@endsection
