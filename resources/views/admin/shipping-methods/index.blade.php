@extends('layouts.admin')

@php
    use App\Support\Money;
@endphp

@section('title', 'Shipping Methods')

@section('content')
    <div class="mb-8">
        <x-badge tone="brand">Marketplace delivery</x-badge>
        <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Shipping Methods</h1>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-neutral-600">Read-only visibility into vendor-configured delivery methods.</p>
    </div>

    <form method="GET" class="mb-6 grid gap-3 rounded-lg border border-neutral-200 bg-white p-4 shadow-sm md:grid-cols-[1fr_14rem_12rem_auto]">
        <x-input label="Search" name="q" :value="$filters['q']" placeholder="Method, code, or vendor" />
        <x-select label="Vendor" name="vendor_id">
            <option value="">All vendors</option>
            @foreach ($vendors as $vendor)
                <option value="{{ $vendor->id }}" @selected((int) $filters['vendor_id'] === $vendor->id)>{{ $vendor->display_name }}</option>
            @endforeach
        </x-select>
        <x-select label="Status" name="status">
            <option value="">All</option>
            <option value="active" @selected($filters['status'] === 'active')>Active</option>
            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
        </x-select>
        <div class="flex items-end"><x-button type="submit" class="w-full">Filter</x-button></div>
    </form>

    <section class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Vendor</th>
                        <th class="px-4 py-3">Method</th>
                        <th class="px-4 py-3">Rate</th>
                        <th class="px-4 py-3">Coverage</th>
                        <th class="px-4 py-3">Estimate</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($methods as $method)
                        <tr>
                            <td class="px-4 py-4 font-medium text-neutral-950">{{ $method->vendor->display_name }}</td>
                            <td class="px-4 py-4">
                                <p class="font-semibold text-neutral-950">{{ $method->name }}</p>
                                <p class="mt-1 font-mono text-xs text-neutral-500">{{ $method->code }}</p>
                            </td>
                            <td class="px-4 py-4 text-neutral-700">{{ Money::format($method->flat_rate) }}</td>
                            <td class="max-w-xs px-4 py-4 text-neutral-700">{{ $method->coverageLabel() }}</td>
                            <td class="px-4 py-4 text-neutral-700">{{ $method->deliveryEstimate() ?: '-' }}</td>
                            <td class="px-4 py-4"><x-badge :tone="$method->is_active ? 'success' : 'neutral'">{{ $method->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12">
                                <x-empty-state title="No shipping methods" description="Vendor shipping methods will appear here once configured." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-6">{{ $methods->links() }}</div>
@endsection
