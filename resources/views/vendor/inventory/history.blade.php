@extends('layouts.vendor')

@section('title', 'Inventory History')

@section('content')
    <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <x-badge :tone="$inventory->stockStatusTone()">{{ $inventory->stockStatusLabel() }}</x-badge>
            <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Inventory History</h1>
            <p class="mt-2 text-sm text-neutral-600">{{ $product->name }} / {{ $variant->displayName() }} / {{ $variant->sku }}</p>
        </div>
        <x-button :href="route('vendor.products.inventory.show', $product)" variant="secondary">Manage Inventory</x-button>
    </div>

    <x-vendor.inventory.summary :inventory="$inventory" />

    <section class="mt-6 overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Change</th>
                        <th class="px-4 py-3">Before</th>
                        <th class="px-4 py-3">After</th>
                        <th class="px-4 py-3">Reason</th>
                        <th class="px-4 py-3">Changed By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($movements as $movement)
                        <tr>
                            <td class="px-4 py-4 text-neutral-700">{{ $movement->created_at->format('M j, Y H:i') }}</td>
                            <td class="px-4 py-4"><x-badge :tone="$movement->type->badgeTone()">{{ $movement->type->label() }}</x-badge></td>
                            <td class="px-4 py-4 font-semibold {{ $movement->quantity >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ $movement->quantity >= 0 ? '+' : '' }}{{ $movement->quantity }}</td>
                            <td class="px-4 py-4 text-neutral-700">{{ $movement->before_quantity }}</td>
                            <td class="px-4 py-4 text-neutral-700">{{ $movement->after_quantity }}</td>
                            <td class="px-4 py-4 text-neutral-700">{{ $movement->reason ?: 'Not provided' }}</td>
                            <td class="px-4 py-4 text-neutral-700">{{ $movement->createdBy?->name ?: 'System' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12">
                                <x-empty-state title="No movement history yet" description="Stock changes will appear here after the first inventory operation." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-6">{{ $movements->links() }}</div>
@endsection
