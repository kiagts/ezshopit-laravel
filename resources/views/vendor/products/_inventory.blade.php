<section class="mt-8 rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-base font-semibold text-neutral-950">Inventory</h2>
            <p class="mt-2 text-sm leading-6 text-neutral-600">Stock is managed per sellable variant and does not affect product approval status.</p>
        </div>
        @if ($product->exists)
            <x-button :href="route('vendor.products.inventory.show', $product)" variant="secondary" size="sm">Manage Inventory</x-button>
        @endif
    </div>

    @if ($product->variants->isNotEmpty())
        <div class="mt-5 overflow-x-auto">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Variant</th>
                        <th class="px-4 py-3">SKU</th>
                        <th class="px-4 py-3">On Hand</th>
                        <th class="px-4 py-3">Available</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @foreach ($product->variants as $variant)
                        @php($inventory = $variant->inventory)
                        <tr>
                            <td class="px-4 py-3 font-medium text-neutral-950">{{ $variant->displayName() }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-neutral-600">{{ $variant->sku }}</td>
                            <td class="px-4 py-3 text-neutral-700">{{ $inventory?->on_hand ?? 0 }}</td>
                            <td class="px-4 py-3 font-semibold text-neutral-950">{{ $inventory?->availableQuantity() ?? 0 }}</td>
                            <td class="px-4 py-3">
                                @if ($inventory)
                                    <x-badge :tone="$inventory->stockStatusTone()">{{ $inventory->stockStatusLabel() }}</x-badge>
                                @else
                                    <x-badge tone="neutral">Not Initialized</x-badge>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="mt-5 rounded-lg border border-dashed border-neutral-300 bg-neutral-50 px-6 py-8 text-center text-sm text-neutral-500">Inventory appears after the product has a sellable variant.</p>
    @endif
</section>
