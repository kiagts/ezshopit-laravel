@extends('layouts.admin')

@php
    use App\Support\Money;
@endphp

@section('title', 'Payment '.$payment->id)

@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <x-badge :tone="$payment->status->badgeTone()">{{ $payment->status->label() }}</x-badge>
            <h1 class="mt-4 text-2xl font-semibold tracking-normal text-neutral-950">Payment #{{ $payment->id }}</h1>
            <p class="mt-2 text-sm leading-6 text-neutral-600">{{ $payment->order->order_number }} · {{ $payment->order->user->email }}</p>
        </div>
        <x-button :href="route('admin.payments.index')" variant="secondary">All Payments</x-button>
    </div>

    <div class="grid gap-6 lg:grid-cols-[20rem_minmax(0,1fr)] lg:items-start">
        <aside class="space-y-6">
            <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
                <h2 class="font-semibold text-neutral-950">Payment Summary</h2>
                <div class="mt-4 grid gap-3 text-sm">
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Method</span><span class="text-right font-medium">{{ $payment->method->label() }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Provider</span><span class="text-right font-medium">{{ str($payment->provider)->replace('_', ' ')->title() }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Amount</span><span class="text-right font-medium">{{ Money::format($payment->amount) }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Refunded</span><span class="text-right font-medium">{{ Money::format($financialSummary->refundedAmount) }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Pending Refunds</span><span class="text-right font-medium">{{ Money::format($financialSummary->pendingRefundAmount) }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Refundable</span><span class="text-right font-medium">{{ Money::format($financialSummary->refundableAmount()) }}</span></div>
                    <div class="flex justify-between gap-4 border-t border-neutral-100 pt-3"><span class="font-semibold text-neutral-950">Net Paid</span><span class="text-right font-semibold">{{ Money::format($financialSummary->netPaidAmount()) }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Currency</span><span class="text-right font-medium">{{ $payment->currency }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Reference</span><span class="text-right font-medium">{{ $payment->provider_reference ?? '-' }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Expires</span><span class="text-right font-medium">{{ $payment->expires_at?->format('M j, Y g:i A') ?? 'No expiry' }}</span></div>
                </div>
            </section>

            @if ($payment->requires_review_at)
                <section class="rounded-lg border border-amber-200 bg-amber-50 p-5 shadow-soft">
                    <h2 class="font-semibold text-amber-950">Payment Requires Review</h2>
                    <p class="mt-2 text-sm leading-6 text-amber-900">{{ $payment->review_reason }}</p>
                    <p class="mt-2 text-xs text-amber-800">Flagged {{ $payment->requires_review_at->format('M j, Y g:i A') }}</p>
                    @if ($payment->review_resolved_at)
                        <p class="mt-1 text-xs text-amber-800">Resolved {{ $payment->review_resolved_at->format('M j, Y g:i A') }}</p>
                    @endif
                </section>
            @endif

            <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
                <h2 class="font-semibold text-neutral-950">Order</h2>
                <p class="mt-3 font-medium text-neutral-950">{{ $payment->order->order_number }}</p>
                <p class="mt-1 text-sm text-neutral-600">{{ $payment->order->user->name }}</p>
                @if ($payment->order->cancellation_source)
                    <div class="mt-3 rounded-md bg-neutral-50 p-3 text-xs text-neutral-600">
                        <p>Cancellation: {{ $payment->order->cancellation_source->label() }}</p>
                        <p class="mt-1">{{ $payment->order->cancellation_reason }}</p>
                    </div>
                @endif
                <x-button :href="route('admin.orders.show', $payment->order)" variant="secondary" size="sm" class="mt-4">View Order</x-button>
            </section>
        </aside>

        <section class="rounded-lg border border-neutral-200 bg-white shadow-soft">
            <div class="border-b border-neutral-100 p-5">
                <h2 class="font-semibold text-neutral-950">Refunds</h2>
                <p class="mt-1 text-sm text-neutral-600">Append-only refund events against this parent payment.</p>
            </div>
            <div class="divide-y divide-neutral-100">
                @forelse ($payment->refunds as $refund)
                    <article class="grid gap-4 p-5 md:grid-cols-[1fr_1fr_1fr_auto] md:items-center">
                        <div>
                            <p class="font-semibold text-neutral-950">{{ $refund->refund_number }}</p>
                            <p class="mt-1 text-sm text-neutral-500">{{ $refund->requested_at?->format('M j, Y g:i A') }}</p>
                        </div>
                        <x-badge :tone="$refund->status->badgeTone()">{{ $refund->status->label() }}</x-badge>
                        <p class="font-semibold text-neutral-950">{{ Money::format($refund->amount) }}</p>
                        <x-button :href="route('admin.refunds.show', $refund)" variant="secondary" size="sm">View</x-button>
                    </article>
                @empty
                    <x-empty-state title="No refunds" description="Refunds will appear here after an admin creates one." class="m-5" />
                @endforelse
            </div>
        </section>

        <section class="rounded-lg border border-neutral-200 bg-white shadow-soft">
            <div class="border-b border-neutral-100 p-5">
                <h2 class="font-semibold text-neutral-950">Attempts</h2>
                <p class="mt-1 text-sm text-neutral-600">Provider attempt history. Sensitive provider data is not stored here.</p>
            </div>
            <div class="divide-y divide-neutral-100">
                @forelse ($payment->attempts as $attempt)
                    <article class="grid gap-4 p-5 md:grid-cols-[auto_1fr_1fr_1fr] md:items-center">
                        <p class="font-semibold text-neutral-950">#{{ $attempt->attempt_number }}</p>
                        <x-badge :tone="$attempt->status->badgeTone()">{{ $attempt->status->label() }}</x-badge>
                        <p class="text-sm text-neutral-600">{{ Money::format($attempt->amount) }} {{ $attempt->currency }}</p>
                        <p class="text-sm text-neutral-500">{{ $attempt->started_at?->format('M j, Y g:i A') }}</p>
                        @if ($attempt->failure_code || $attempt->failure_message)
                            <p class="md:col-span-4 rounded-md bg-red-50 p-3 text-sm text-red-800">{{ $attempt->failure_code }} {{ $attempt->failure_message }}</p>
                        @endif
                    </article>
                @empty
                    <x-empty-state title="No attempts" description="This payment does not have recorded attempts." class="m-5" />
                @endforelse
            </div>
        </section>

        <section class="rounded-lg border border-neutral-200 bg-white shadow-soft">
            <div class="border-b border-neutral-100 p-5">
                <h2 class="font-semibold text-neutral-950">Webhook Events</h2>
                <p class="mt-1 text-sm text-neutral-600">Verified provider events recorded for idempotency and review.</p>
            </div>
            <div class="divide-y divide-neutral-100">
                @forelse ($payment->webhookEvents as $event)
                    <article class="grid gap-3 p-5 text-sm md:grid-cols-[1fr_1fr_auto] md:items-center">
                        <p class="font-medium text-neutral-950">{{ $event->event_type }}</p>
                        <p class="text-neutral-500">{{ $event->provider_event_id }}</p>
                        <p class="text-neutral-500">{{ $event->processed_at?->format('M j, Y g:i A') ?? 'Pending' }}</p>
                    </article>
                @empty
                    <x-empty-state title="No webhook events" description="Stripe events will appear here after webhooks are received." class="m-5" />
                @endforelse
            </div>
        </section>
    </div>
@endsection
