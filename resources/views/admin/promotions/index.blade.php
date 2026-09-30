@extends('layouts.admin')

@php
    use App\Support\Money;
@endphp

@section('title', 'Promotions')

@section('content')
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-neutral-950">Promotions</h1>
            <p class="mt-1 text-sm text-neutral-600">Manage marketplace-funded and vendor-funded coupon codes.</p>
        </div>
        <x-button :href="route('admin.promotions.create')">Create Promotion</x-button>
    </div>

    <form method="GET" class="mb-6 grid gap-3 rounded-lg border border-neutral-200 bg-white p-4 sm:grid-cols-4">
        <input name="q" value="{{ $filters['q'] }}" placeholder="Search code or name" class="rounded-md border-neutral-300 text-sm focus:border-brand-700 focus:ring-brand-700">
        <select name="funding_source" class="rounded-md border-neutral-300 text-sm focus:border-brand-700 focus:ring-brand-700">
            <option value="">All funding</option>
            <option value="marketplace" @selected($filters['funding_source'] === 'marketplace')>Marketplace</option>
            <option value="vendor" @selected($filters['funding_source'] === 'vendor')>Vendor</option>
        </select>
        <select name="vendor_id" class="rounded-md border-neutral-300 text-sm focus:border-brand-700 focus:ring-brand-700">
            <option value="">All vendors</option>
            @foreach ($vendors as $vendor)
                <option value="{{ $vendor->id }}" @selected((int) $filters['vendor_id'] === $vendor->id)>{{ $vendor->display_name }}</option>
            @endforeach
        </select>
        <div class="flex gap-2">
            <select name="status" class="min-w-0 flex-1 rounded-md border-neutral-300 text-sm focus:border-brand-700 focus:ring-brand-700">
                <option value="">Any status</option>
                <option value="active" @selected($filters['status'] === 'active')>Active</option>
                <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
            </select>
            <x-button type="submit" variant="secondary">Filter</x-button>
        </div>
    </form>

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-neutral-200 text-sm">
            <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase text-neutral-500">
                <tr>
                    <th class="px-4 py-3">Code</th>
                    <th class="px-4 py-3">Funding</th>
                    <th class="px-4 py-3">Discount</th>
                    <th class="px-4 py-3">Usage</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse ($promotions as $promotion)
                    <tr>
                        <td class="px-4 py-4">
                            <p class="font-semibold text-neutral-950">{{ $promotion->code }}</p>
                            <p class="text-xs text-neutral-500">{{ $promotion->name }}</p>
                        </td>
                        <td class="px-4 py-4">
                            <p>{{ $promotion->funding_source->label() }}</p>
                            @if ($promotion->vendor)
                                <p class="text-xs text-neutral-500">{{ $promotion->vendor->display_name }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-4">
                            @if ($promotion->discount_type->value === 'percentage')
                                {{ number_format($promotion->discount_value / 100, 2) }}%
                            @else
                                {{ Money::format($promotion->discount_value) }}
                            @endif
                        </td>
                        <td class="px-4 py-4">{{ $promotion->redemptions_count }} / {{ $promotion->usage_limit ?: 'Unlimited' }}</td>
                        <td class="px-4 py-4">
                            <x-badge :tone="$promotion->is_active ? 'success' : 'neutral'">{{ $promotion->is_active ? 'Active' : 'Inactive' }}</x-badge>
                        </td>
                        <td class="px-4 py-4 text-right">
                            <a href="{{ route('admin.promotions.edit', $promotion) }}" class="font-semibold text-brand-700 hover:text-brand-800">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-neutral-500">No promotions found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-5">{{ $promotions->links() }}</div>
@endsection
