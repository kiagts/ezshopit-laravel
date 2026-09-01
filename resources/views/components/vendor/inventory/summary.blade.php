@props(['inventory'])

<div class="grid gap-3 sm:grid-cols-4">
    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4">
        <p class="text-xs font-medium uppercase text-neutral-500">On Hand</p>
        <p class="mt-2 text-2xl font-semibold text-neutral-950">{{ $inventory->on_hand }}</p>
    </div>
    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4">
        <p class="text-xs font-medium uppercase text-neutral-500">Reserved</p>
        <p class="mt-2 text-2xl font-semibold text-neutral-950">{{ $inventory->reserved }}</p>
    </div>
    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4">
        <p class="text-xs font-medium uppercase text-neutral-500">Available</p>
        <p class="mt-2 text-2xl font-semibold text-neutral-950">{{ $inventory->availableQuantity() }}</p>
    </div>
    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4">
        <p class="text-xs font-medium uppercase text-neutral-500">Status</p>
        <div class="mt-3"><x-badge :tone="$inventory->stockStatusTone()">{{ $inventory->stockStatusLabel() }}</x-badge></div>
    </div>
</div>
