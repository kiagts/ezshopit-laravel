@extends('layouts.admin')

@section('title', 'Inventory')

@section('content')
    <div class="mb-8">
        <x-badge tone="brand">Marketplace stock</x-badge>
        <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Inventory</h1>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-neutral-600">Read-only stock visibility across vendor product variants.</p>
    </div>

    <form method="GET" class="mb-6 grid gap-3 rounded-lg border border-neutral-200 bg-white p-4 shadow-sm md:grid-cols-[1fr_14rem_auto]">
        <x-input label="Search" name="q" :value="$filters['q']" placeholder="Product, SKU, or vendor" />
        <x-select label="Status" name="status">
            <option value="">All</option>
            <option value="in_stock" @selected($filters['status'] === 'in_stock')>In Stock</option>
            <option value="low_stock" @selected($filters['status'] === 'low_stock')>Low Stock</option>
            <option value="out_of_stock" @selected($filters['status'] === 'out_of_stock')>Out of Stock</option>
        </x-select>
        <div class="flex items-end"><x-button type="submit" class="w-full">Filter</x-button></div>
    </form>

    <section class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Vendor</th>
                        <th class="px-4 py-3">Product</th>
                        <th class="px-4 py-3">Variant</th>
                        <th class="px-4 py-3">SKU</th>
                        <th class="px-4 py-3">On Hand</th>
                        <th class="px-4 py-3">Reserved</th>
                        <th class="px-4 py-3">Available</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Updated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($inventories as $inventory)
                        @php($variant = $inventory->variant)
                        @php($product = $variant->product)
                        <tr>
                            <td class="px-4 py-4 font-medium text-neutral-950">{{ $product->vendor->display_name }}</td>
                            <td class="px-4 py-4 text-neutral-700">{{ $product->name }}</td>
                            <td class="px-4 py-4 text-neutral-700">{{ $variant->displayName() }}</td>
                            <td class="px-4 py-4 font-mono text-xs text-neutral-700">{{ $variant->sku }}</td>
                            <td class="px-4 py-4 text-neutral-700">{{ $inventory->on_hand }}</td>
                            <td class="px-4 py-4 text-neutral-700">{{ $inventory->reserved }}</td>
                            <td class="px-4 py-4 font-semibold text-neutral-950">{{ $inventory->availableQuantity() }}</td>
                            <td class="px-4 py-4"><x-badge :tone="$inventory->stockStatusTone()">{{ $inventory->stockStatusLabel() }}</x-badge></td>
                            <td class="px-4 py-4 text-neutral-500">{{ $inventory->updated_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12">
                                <x-empty-state title="No inventory records" description="Variant inventory will appear here as vendors create products." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-6">{{ $inventories->links() }}</div>
@endsection
