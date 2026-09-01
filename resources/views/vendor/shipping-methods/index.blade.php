@extends('layouts.vendor')

@php
    use App\Support\Money;
@endphp

@section('title', 'Shipping Methods')

@section('content')
    <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <x-badge tone="brand">Delivery setup</x-badge>
            <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Shipping Methods</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-neutral-600">Configure flat-rate delivery options customers can select during checkout.</p>
        </div>
        <x-button :href="route('vendor.shipping-methods.create')">Add Shipping Method</x-button>
    </div>

    <section class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Method</th>
                        <th class="px-4 py-3">Rate</th>
                        <th class="px-4 py-3">Free Threshold</th>
                        <th class="px-4 py-3">Coverage</th>
                        <th class="px-4 py-3">Estimate</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($methods as $method)
                        <tr>
                            <td class="px-4 py-4">
                                <p class="font-semibold text-neutral-950">{{ $method->name }}</p>
                                <p class="mt-1 font-mono text-xs text-neutral-500">{{ $method->code }}</p>
                            </td>
                            <td class="px-4 py-4 font-medium text-neutral-950">{{ Money::format($method->flat_rate) }}</td>
                            <td class="px-4 py-4 text-neutral-700">{{ $method->thresholdLabel() }}</td>
                            <td class="max-w-xs px-4 py-4 text-neutral-700">{{ $method->coverageLabel() }}</td>
                            <td class="px-4 py-4 text-neutral-700">{{ $method->deliveryEstimate() ?: '-' }}</td>
                            <td class="px-4 py-4"><x-badge :tone="$method->is_active ? 'success' : 'neutral'">{{ $method->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                            <td class="px-4 py-4">
                                <div class="flex justify-end gap-2">
                                    <x-button :href="route('vendor.shipping-methods.edit', $method)" variant="secondary" size="sm">Edit</x-button>
                                    <form method="POST" action="{{ route('vendor.shipping-methods.destroy', $method) }}" onsubmit="return confirm('Delete this shipping method?')">
                                        @csrf
                                        @method('DELETE')
                                        <x-button type="submit" variant="ghost" size="sm">Delete</x-button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12">
                                <x-empty-state title="No shipping methods configured yet" description="Add a method so customers can receive your products during checkout.">
                                    <x-button :href="route('vendor.shipping-methods.create')">Add Shipping Method</x-button>
                                </x-empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-6">{{ $methods->links() }}</div>
@endsection
