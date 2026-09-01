@extends('layouts.admin')

@php
    use App\Support\Money;
@endphp

@section('title', $refund->refund_number)

@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <x-badge :tone="$refund->status->badgeTone()">{{ $refund->status->label() }}</x-badge>
            <h1 class="mt-4 text-2xl font-semibold tracking-normal text-neutral-950">{{ $refund->refund_number }}</h1>
            <p class="mt-2 text-sm leading-6 text-neutral-600">{{ $refund->order->order_number }} · {{ $refund->order->user->email }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-button :href="route('admin.orders.show', $refund->order)" variant="secondary">Order</x-button>
            <x-button :href="route('admin.refunds.index')" variant="secondary">All Refunds</x-button>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[20rem_minmax(0,1fr)] lg:items-start">
        <aside class="space-y-6">
            <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
                <h2 class="font-semibold text-neutral-950">Refund Summary</h2>
                <div class="mt-4 grid gap-3 text-sm">
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Amount</span><span class="font-medium">{{ Money::format($refund->amount) }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Currency</span><span class="font-medium">{{ $refund->currency }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Provider</span><span class="font-medium">{{ str($refund->provider)->replace('_', ' ')->title() }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Reference</span><span class="text-right font-medium">{{ $refund->provider_reference ?? 'Pending' }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Requested</span><span class="text-right font-medium">{{ $refund->requested_at?->format('M j, Y g:i A') }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Processed</span><span class="text-right font-medium">{{ $refund->processed_at?->format('M j, Y g:i A') ?? 'Not completed' }}</span></div>
                </div>
            </section>

            <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
                <h2 class="font-semibold text-neutral-950">Payment Financials</h2>
                <div class="mt-4 grid gap-3 text-sm">
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Original</span><span class="font-medium">{{ Money::format($financialSummary->originalAmount) }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Refunded</span><span class="font-medium">{{ Money::format($financialSummary->refundedAmount) }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Pending</span><span class="font-medium">{{ Money::format($financialSummary->pendingRefundAmount) }}</span></div>
                    <div class="flex justify-between gap-4 border-t border-neutral-100 pt-3"><span class="font-semibold text-neutral-950">Net Paid</span><span class="font-semibold">{{ Money::format($financialSummary->netPaidAmount()) }}</span></div>
                </div>
            </section>
        </aside>

        <div class="space-y-6">
            <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
                <h2 class="font-semibold text-neutral-950">Reason</h2>
                <p class="mt-3 text-sm leading-6 text-neutral-700">{{ $refund->reason }}</p>
                @if ($refund->notes)
                    <div class="mt-4 rounded-md border border-neutral-200 bg-neutral-50 p-3 text-sm text-neutral-700">{{ $refund->notes }}</div>
                @endif
                @if ($refund->failure_code || $refund->failure_message)
                    <div class="mt-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                        {{ $refund->failure_code }} {{ $refund->failure_message }}
                    </div>
                @endif
            </section>

            <section class="rounded-lg border border-neutral-200 bg-white shadow-soft">
                <div class="border-b border-neutral-100 p-5">
                    <h2 class="font-semibold text-neutral-950">Allocations</h2>
                </div>
                <div class="divide-y divide-neutral-100">
                    @foreach ($refund->allocations as $allocation)
                        <article class="grid gap-3 p-5 text-sm md:grid-cols-[1fr_1fr_auto] md:items-center">
                            <div>
                                <p class="font-semibold text-neutral-950">{{ $allocation->type->label() }}</p>
                                <p class="mt-1 text-neutral-500">{{ $allocation->vendorOrder?->vendor?->display_name ?? 'Marketplace' }} · {{ $allocation->vendorOrder?->vendor_order_number ?? 'Adjustment' }}</p>
                            </div>
                            <p class="text-neutral-600">{{ $allocation->orderItem?->product_name ?? 'No item allocation' }} @if($allocation->quantity) · Qty {{ $allocation->quantity }} @endif</p>
                            <p class="font-semibold text-neutral-950 md:text-right">{{ Money::format($allocation->amount) }}</p>
                        </article>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
@endsection
