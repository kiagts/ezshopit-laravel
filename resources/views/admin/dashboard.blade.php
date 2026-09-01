@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <div class="mb-8">
        <x-badge tone="brand">Marketplace operations</x-badge>
        <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Admin dashboard</h1>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-neutral-600">
            Review vendor activity and moderate submitted marketplace products.
        </p>
    </div>

    <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([['Products', $stats['products']], ['Pending Product Reviews', $stats['pendingProducts']], ['Low Stock Variants', $stats['lowStock']], ['Out of Stock Variants', $stats['outOfStock']]] as [$label, $value])
            <x-card>
                <p class="text-sm font-medium text-neutral-500">{{ $label }}</p>
                <p class="mt-3 text-3xl font-semibold text-neutral-950">{{ $value }}</p>
            </x-card>
        @endforeach
    </div>

    <section class="mt-8 rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-neutral-950">Operations workspace</h2>
                <p class="mt-2 text-sm leading-6 text-neutral-600">Review product moderation and inspect marketplace stock without mutating vendor inventory.</p>
            </div>
            <x-button :href="route('admin.inventory.index')" variant="secondary">View Inventory</x-button>
        </div>
    </section>
@endsection
