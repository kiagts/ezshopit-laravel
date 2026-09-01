@extends('layouts.admin')

@php
    use App\Support\Money;
@endphp

@section('title', 'Commission '.$commission->id)

@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <x-badge tone="success">Recognized</x-badge>
            <h1 class="mt-4 text-2xl font-semibold tracking-normal text-neutral-950">{{ $commission->vendorOrder->vendor_order_number }}</h1>
            <p class="mt-2 text-sm leading-6 text-neutral-600">{{ $commission->vendorOrder->vendor->display_name }} · {{ $commission->vendorOrder->order->order_number }}</p>
        </div>
        <x-button :href="route('admin.commissions.index')" variant="secondary">All Commissions</x-button>
    </div>

    <div class="grid gap-6 lg:grid-cols-[20rem_minmax(0,1fr)] lg:items-start">
        <aside class="space-y-6">
            <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
                <h2 class="font-semibold text-neutral-950">Snapshot</h2>
                <div class="mt-4 grid gap-3 text-sm">
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Rate</span><span class="font-medium">{{ $calculator->formatRate($commission->rate_basis_points) }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Shipping</span><span class="font-medium">{{ $commission->include_shipping ? 'Included' : 'Excluded' }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Currency</span><span class="font-medium">{{ $commission->currency }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Recognized</span><span class="text-right font-medium">{{ $commission->recognized_at?->format('M j, Y g:i A') }}</span></div>
                </div>
            </section>
            <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
                <h2 class="font-semibold text-neutral-950">Financial Summary</h2>
                <div class="mt-4 grid gap-3 text-sm">
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Original seller value</span><span class="font-medium">{{ Money::format($summary->originalSellerValue) }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Seller refunds</span><span class="font-medium">-{{ Money::format($summary->successfulVendorRefunds) }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Net seller value</span><span class="font-medium">{{ Money::format($summary->netSellerValue) }}</span></div>
                    <div class="flex justify-between gap-4 border-t border-neutral-100 pt-3"><span class="font-semibold text-neutral-950">Net commission</span><span class="font-semibold">{{ Money::format($summary->netCommissionAmount) }}</span></div>
                    <div class="flex justify-between gap-4"><span class="font-semibold text-neutral-950">Net after commission</span><span class="font-semibold">{{ Money::format($summary->netAfterCommission) }}</span></div>
                </div>
            </section>
        </aside>

        <section class="rounded-lg border border-neutral-200 bg-white shadow-soft">
            <div class="border-b border-neutral-100 p-5">
                <h2 class="font-semibold text-neutral-950">Commission Adjustments</h2>
            </div>
            <div class="divide-y divide-neutral-100">
                @forelse ($commission->adjustments as $adjustment)
                    <article class="grid gap-3 p-5 text-sm md:grid-cols-[1fr_1fr_1fr_auto] md:items-center">
                        <div>
                            <p class="font-semibold text-neutral-950">{{ $adjustment->type->label() }}</p>
                            <p class="mt-1 text-neutral-500">{{ $adjustment->reason }}</p>
                        </div>
                        <p class="text-neutral-600">{{ $adjustment->refund?->refund_number ?? 'No refund' }}</p>
                        <p class="text-neutral-600">{{ $adjustment->refundAllocation?->orderItem?->product_name ?? 'Shipping or adjustment' }}</p>
                        <p class="font-semibold text-neutral-950 md:text-right">{{ Money::format($adjustment->commission_amount_delta) }}</p>
                    </article>
                @empty
                    <x-empty-state title="No adjustments" description="Refund commission adjustments will appear here." class="m-5" />
                @endforelse
            </div>
        </section>
    </div>
@endsection
