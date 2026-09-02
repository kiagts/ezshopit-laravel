@php
    use App\Enums\VendorPayoutStatus;
    use App\Support\Countries;
    use App\Support\Money;

    $snapshot = $payout->destination_snapshot ?: [];
@endphp

@extends('layouts.admin')

@section('title', $payout->payout_number)

@section('content')
    <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <x-badge :tone="$payout->status->badgeTone()">{{ $payout->status->label() }}</x-badge>
            <h1 class="mt-4 text-2xl font-semibold text-neutral-950">{{ $payout->payout_number }}</h1>
            <p class="mt-2 text-sm text-neutral-600">{{ $payout->vendor->display_name }} · {{ Money::format($payout->amount, $payout->currency) }}</p>
        </div>
        <x-button :href="route('admin.payouts.index')" variant="secondary">Back to Payouts</x-button>
    </div>

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    @if ($errors->any())
        <x-alert type="danger" class="mb-6">{{ $errors->first() }}</x-alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_24rem]">
        <div class="space-y-6">
            <x-card>
                <h2 class="text-base font-semibold text-neutral-950">Payout Destination Snapshot</h2>
                <dl class="mt-4 grid gap-4 text-sm md:grid-cols-2">
                    <div><dt class="text-neutral-500">Method</dt><dd class="mt-1 font-medium text-neutral-950">{{ $payout->methodLabel() }}</dd></div>
                    <div><dt class="text-neutral-500">Country</dt><dd class="mt-1 font-medium text-neutral-950">{{ Countries::name($snapshot['country_code'] ?? null) ?: '-' }}</dd></div>
                    <div><dt class="text-neutral-500">Account holder</dt><dd class="mt-1 font-medium text-neutral-950">{{ $snapshot['account_holder_name'] ?? '-' }}</dd></div>
                    <div><dt class="text-neutral-500">Bank</dt><dd class="mt-1 font-medium text-neutral-950">{{ $snapshot['bank_name'] ?? '-' }}</dd></div>
                    <div><dt class="text-neutral-500">Account number</dt><dd class="mt-1 font-medium text-neutral-950">{{ $snapshot['account_number'] ?? '-' }}</dd></div>
                    <div><dt class="text-neutral-500">IBAN</dt><dd class="mt-1 font-medium text-neutral-950">{{ $snapshot['iban'] ?? '-' }}</dd></div>
                    <div><dt class="text-neutral-500">SWIFT/BIC</dt><dd class="mt-1 font-medium text-neutral-950">{{ $snapshot['swift_code'] ?? '-' }}</dd></div>
                    <div><dt class="text-neutral-500">Routing number</dt><dd class="mt-1 font-medium text-neutral-950">{{ $snapshot['routing_number'] ?? '-' }}</dd></div>
                </dl>
            </x-card>

            <x-card>
                <h2 class="text-base font-semibold text-neutral-950">Lifecycle</h2>
                <dl class="mt-4 grid gap-4 text-sm md:grid-cols-2">
                    <div><dt class="text-neutral-500">Requested</dt><dd class="mt-1 font-medium text-neutral-950">{{ $payout->requested_at?->format('M j, Y g:i A') }}</dd></div>
                    <div><dt class="text-neutral-500">Approved</dt><dd class="mt-1 font-medium text-neutral-950">{{ $payout->approved_at?->format('M j, Y g:i A') ?? '-' }}</dd></div>
                    <div><dt class="text-neutral-500">Processing</dt><dd class="mt-1 font-medium text-neutral-950">{{ $payout->processing_at?->format('M j, Y g:i A') ?? '-' }}</dd></div>
                    <div><dt class="text-neutral-500">Paid</dt><dd class="mt-1 font-medium text-neutral-950">{{ $payout->paid_at?->format('M j, Y g:i A') ?? '-' }}</dd></div>
                    <div><dt class="text-neutral-500">External reference</dt><dd class="mt-1 font-medium text-neutral-950">{{ $payout->external_reference ?? '-' }}</dd></div>
                    <div><dt class="text-neutral-500">Ledger entry</dt><dd class="mt-1 font-medium text-neutral-950">{{ $payout->ledgerEntry?->source_key ?? '-' }}</dd></div>
                </dl>
                @if ($payout->rejection_reason || $payout->cancellation_reason || $payout->internal_notes)
                    <div class="mt-5 space-y-2 text-sm text-neutral-600">
                        @if ($payout->rejection_reason)<p><strong>Rejection:</strong> {{ $payout->rejection_reason }}</p>@endif
                        @if ($payout->cancellation_reason)<p><strong>Cancellation:</strong> {{ $payout->cancellation_reason }}</p>@endif
                        @if ($payout->internal_notes)<p><strong>Internal notes:</strong> {{ $payout->internal_notes }}</p>@endif
                    </div>
                @endif
            </x-card>
        </div>

        <aside class="space-y-6">
            <x-card>
                <h2 class="text-base font-semibold text-neutral-950">Vendor Balance Context</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-neutral-500">Available ledger</dt><dd class="font-semibold">{{ Money::format($balance->availableLedgerBalance, $balance->currency) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-neutral-500">Active reserved</dt><dd class="font-semibold">{{ Money::format($balance->activePayoutReserved, $balance->currency) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-neutral-500">Requestable</dt><dd class="font-semibold">{{ Money::format($balance->requestableBalance, $balance->currency) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-neutral-500">Paid out</dt><dd class="font-semibold">{{ Money::format($balance->paidOutTotal, $balance->currency) }}</dd></div>
                </dl>
            </x-card>

            <x-card>
                <h2 class="text-base font-semibold text-neutral-950">Actions</h2>
                <div class="mt-4 space-y-3">
                    @if ($payout->status === VendorPayoutStatus::Pending)
                        <form method="POST" action="{{ route('admin.payouts.approve', $payout) }}">
                            @csrf
                            <x-button type="submit" class="w-full">Approve</x-button>
                        </form>
                        <form method="POST" action="{{ route('admin.payouts.reject', $payout) }}" class="space-y-3">
                            @csrf
                            <x-textarea name="reason" label="Reject reason" placeholder="Explain why this request cannot be processed.">{{ old('reason') }}</x-textarea>
                            <x-button type="submit" variant="danger" class="w-full">Reject</x-button>
                        </form>
                    @elseif ($payout->status === VendorPayoutStatus::Approved)
                        <form method="POST" action="{{ route('admin.payouts.processing', $payout) }}">
                            @csrf
                            <x-button type="submit" class="w-full">Start Processing</x-button>
                        </form>
                        <form method="POST" action="{{ route('admin.payouts.cancel', $payout) }}" class="space-y-3">
                            @csrf
                            <x-textarea name="reason" label="Cancel reason" placeholder="Reason for cancelling before processing.">{{ old('reason') }}</x-textarea>
                            <x-button type="submit" variant="danger" class="w-full">Cancel</x-button>
                        </form>
                    @elseif ($payout->status === VendorPayoutStatus::Processing)
                        <form method="POST" action="{{ route('admin.payouts.paid', $payout) }}" class="space-y-3">
                            @csrf
                            <x-input label="External reference" name="external_reference" value="{{ old('external_reference') }}" placeholder="Bank transfer ID" />
                            <x-textarea name="internal_notes" label="Internal notes" placeholder="Admin-only payout notes.">{{ old('internal_notes') }}</x-textarea>
                            <x-button type="submit" class="w-full">Mark Paid</x-button>
                        </form>
                    @else
                        <p class="text-sm text-neutral-600">This payout is terminal.</p>
                    @endif
                </div>
            </x-card>
        </aside>
    </div>
@endsection
