@props(['product', 'variant', 'inventory'])

@php($locked = $product->status === \App\Enums\ProductStatus::Archived)

<div class="grid gap-4 lg:grid-cols-4">
    <form method="POST" action="{{ route('vendor.products.variants.inventory.increase', [$product, $variant]) }}" class="rounded-lg border border-neutral-200 bg-white p-4">
        @csrf
        <p class="text-sm font-semibold text-neutral-950">Add Stock</p>
        <p class="mt-1 text-xs text-neutral-500">Current on hand: {{ $inventory->on_hand }}</p>
        <div class="mt-3 space-y-3">
            <x-input label="Quantity" name="quantity" type="number" min="1" placeholder="5" :disabled="$locked" />
            <x-input label="Reason" name="reason" placeholder="Restock received" :disabled="$locked" />
            <x-button type="submit" size="sm" :disabled="$locked">Add</x-button>
        </div>
    </form>

    <form method="POST" action="{{ route('vendor.products.variants.inventory.decrease', [$product, $variant]) }}" class="rounded-lg border border-neutral-200 bg-white p-4">
        @csrf
        <p class="text-sm font-semibold text-neutral-950">Remove Stock</p>
        <p class="mt-1 text-xs text-neutral-500">Reserved: {{ $inventory->reserved }}</p>
        <div class="mt-3 space-y-3">
            <x-input label="Quantity" name="quantity" type="number" min="1" placeholder="2" :disabled="$locked" />
            <x-input label="Reason" name="reason" placeholder="Damaged stock" :disabled="$locked" />
            <x-button type="submit" size="sm" variant="secondary" :disabled="$locked">Remove</x-button>
        </div>
    </form>

    <form method="POST" action="{{ route('vendor.products.variants.inventory.adjust', [$product, $variant]) }}" class="rounded-lg border border-neutral-200 bg-white p-4">
        @csrf
        <p class="text-sm font-semibold text-neutral-950">Adjust Count</p>
        <p class="mt-1 text-xs text-neutral-500">Physical count correction</p>
        <div class="mt-3 space-y-3">
            <x-input label="On Hand" name="on_hand" type="number" min="0" :value="$inventory->on_hand" :disabled="$locked" />
            <x-input label="Reason" name="reason" placeholder="Inventory audit" :disabled="$locked" />
            <x-button type="submit" size="sm" variant="secondary" :disabled="$locked">Adjust</x-button>
        </div>
    </form>

    <form method="POST" action="{{ route('vendor.products.variants.inventory.threshold', [$product, $variant]) }}" class="rounded-lg border border-neutral-200 bg-white p-4">
        @csrf
        @method('PUT')
        <p class="text-sm font-semibold text-neutral-950">Low-Stock Threshold</p>
        <p class="mt-1 text-xs text-neutral-500">Current threshold: {{ $inventory->low_stock_threshold }}</p>
        <div class="mt-3 space-y-3">
            <x-input label="Threshold" name="low_stock_threshold" type="number" min="0" :value="$inventory->low_stock_threshold" :disabled="$locked" />
            <x-button type="submit" size="sm" variant="secondary" :disabled="$locked">Save</x-button>
        </div>
    </form>
</div>

@if ($locked)
    <p class="mt-3 text-sm text-red-700">Archived products keep inventory visible, but stock changes are locked.</p>
@endif
