@extends('layouts.vendor')

@php
    use App\Support\Money;
@endphp

@section('title', 'Promotions')

@section('content')
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-neutral-950">Promotions</h1>
            <p class="mt-1 text-sm text-neutral-600">Create vendor-funded coupons for your own catalog.</p>
        </div>
        <x-button :href="route('vendor.promotions.create')">Create Promotion</x-button>
    </div>

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-neutral-200 text-sm">
            <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase text-neutral-500">
                <tr>
                    <th class="px-4 py-3">Code</th>
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
                            <a href="{{ route('vendor.promotions.edit', $promotion) }}" class="font-semibold text-brand-700 hover:text-brand-800">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-neutral-500">No vendor promotions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-5">{{ $promotions->links() }}</div>
@endsection
