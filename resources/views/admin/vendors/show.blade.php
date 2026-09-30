@extends('layouts.admin')

@php
    use App\Support\Money;
@endphp

@section('title', $vendor->display_name)

@section('content')
    <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <a href="{{ route('admin.vendors.index') }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">Back to vendors</a>
            <div class="mt-4 flex items-center gap-4">
                @if ($vendor->logo_path)
                    <img src="{{ Storage::url($vendor->logo_path) }}" alt="{{ $vendor->display_name }} logo" class="h-14 w-14 rounded-md object-cover">
                @endif
                <div>
                    <x-badge :tone="$vendor->status->badgeTone()">{{ $vendor->status->label() }}</x-badge>
                    <h1 class="mt-3 text-3xl font-semibold tracking-normal text-neutral-950">{{ $vendor->display_name }}</h1>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <x-button :href="route('admin.vendors.financials', $vendor)" variant="secondary">View Financials</x-button>
            @if ($vendor->status === \App\Enums\VendorStatus::Pending)
                <form method="POST" action="{{ route('admin.vendors.approve', $vendor) }}">
                    @csrf
                    <x-button type="submit">Approve</x-button>
                </form>
            @elseif ($vendor->status === \App\Enums\VendorStatus::Approved)
                <form method="POST" action="{{ route('admin.vendors.suspend', $vendor) }}">
                    @csrf
                    <x-button type="submit" variant="danger">Suspend Vendor</x-button>
                </form>
            @elseif ($vendor->status === \App\Enums\VendorStatus::Suspended)
                <form method="POST" action="{{ route('admin.vendors.reactivate', $vendor) }}">
                    @csrf
                    <x-button type="submit">Reactivate Vendor</x-button>
                </form>
            @endif
        </div>
    </div>

    @error('status')
        <x-alert type="danger" class="mb-6">{{ $message }}</x-alert>
    @enderror

    <div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
        <div class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-card>
                    <p class="text-sm font-medium text-neutral-500">Products</p>
                    <p class="mt-2 text-2xl font-semibold text-neutral-950">{{ number_format($vendor->products_count) }}</p>
                </x-card>
                <x-card>
                    <p class="text-sm font-medium text-neutral-500">Seller Orders</p>
                    <p class="mt-2 text-2xl font-semibold text-neutral-950">{{ number_format($vendor->vendor_orders_count) }}</p>
                </x-card>
                <x-card>
                    <p class="text-sm font-medium text-neutral-500">Available Balance</p>
                    <p class="mt-2 text-2xl font-semibold text-neutral-950">{{ Money::format($financialSummary->availableBalance, $financialSummary->currency) }}</p>
                </x-card>
                <x-card>
                    <p class="text-sm font-medium text-neutral-500">Requestable Payout</p>
                    <p class="mt-2 text-2xl font-semibold text-neutral-950">{{ Money::format($payoutBalance->requestableBalance, $payoutBalance->currency) }}</p>
                </x-card>
            </div>

            <x-card>
                <h2 class="text-lg font-semibold text-neutral-950">Store</h2>
                @if ($vendor->banner_path)
                    <img src="{{ Storage::url($vendor->banner_path) }}" alt="{{ $vendor->display_name }} banner" class="mt-5 h-32 w-full rounded-md object-cover">
                @endif
                <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        'Business Name' => $vendor->business_name,
                        'Display Name' => $vendor->display_name,
                        'Slug' => $vendor->slug,
                        'Description' => $vendor->description,
                    ] as $label => $value)
                        <div>
                            <dt class="text-sm font-medium text-neutral-500">{{ $label }}</dt>
                            <dd class="mt-1 text-sm font-semibold text-neutral-950">{{ $value ?: 'Not provided' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>

            <x-card>
                <h2 class="text-lg font-semibold text-neutral-950">Contact & Business</h2>
                <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        'Business Email' => $vendor->email,
                        'Phone' => $vendor->phone,
                        'Website' => $vendor->website,
                        'Business Type' => $vendor->business_type?->label(),
                        'Tax Number' => $vendor->tax_number,
                        'Registration Number' => $vendor->registration_number,
                    ] as $label => $value)
                        <div>
                            <dt class="text-sm font-medium text-neutral-500">{{ $label }}</dt>
                            <dd class="mt-1 text-sm font-semibold text-neutral-950">{{ $value ?: 'Not provided' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>

            <x-card>
                <h2 class="text-lg font-semibold text-neutral-950">Address</h2>
                <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        'Country' => $vendor->country,
                        'City' => $vendor->city,
                        'Address Line 1' => $vendor->address_line_1,
                        'Address Line 2' => $vendor->address_line_2,
                        'Postal Code' => $vendor->postal_code,
                    ] as $label => $value)
                        <div>
                            <dt class="text-sm font-medium text-neutral-500">{{ $label }}</dt>
                            <dd class="mt-1 text-sm font-semibold text-neutral-950">{{ $value ?: 'Not provided' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>

            <x-card>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-neutral-950">Financial Summary</h2>
                        <p class="mt-1 text-sm text-neutral-600">Ledger-derived seller financials. Historical commissions and payouts remain immutable.</p>
                    </div>
                    <x-button :href="route('admin.vendors.financials', $vendor)" variant="secondary" size="sm">View Financials</x-button>
                </div>
                <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <div><dt class="text-neutral-500">Gross Sales</dt><dd class="mt-1 font-semibold text-neutral-950">{{ Money::format($financialSummary->grossSales, $financialSummary->currency) }}</dd></div>
                    <div><dt class="text-neutral-500">Commission</dt><dd class="mt-1 font-semibold text-neutral-950">{{ Money::format($financialSummary->commissionCharged, $financialSummary->currency) }}</dd></div>
                    <div><dt class="text-neutral-500">Refunds</dt><dd class="mt-1 font-semibold text-neutral-950">{{ Money::format($financialSummary->refunds, $financialSummary->currency) }}</dd></div>
                    <div><dt class="text-neutral-500">Pending Balance</dt><dd class="mt-1 font-semibold text-neutral-950">{{ Money::format($financialSummary->pendingBalance, $financialSummary->currency) }}</dd></div>
                    <div><dt class="text-neutral-500">Reserved Payouts</dt><dd class="mt-1 font-semibold text-neutral-950">{{ Money::format($payoutBalance->activePayoutReserved, $payoutBalance->currency) }}</dd></div>
                    <div><dt class="text-neutral-500">Paid Out</dt><dd class="mt-1 font-semibold text-neutral-950">{{ Money::format($payoutBalance->paidOutTotal, $payoutBalance->currency) }}</dd></div>
                </dl>
            </x-card>
        </div>

        <aside class="space-y-6">
            <x-card>
                <h2 class="text-lg font-semibold text-neutral-950">Owner</h2>
                <dl class="mt-6 space-y-4">
                    <div>
                        <dt class="text-sm font-medium text-neutral-500">Name</dt>
                        <dd class="mt-1 text-sm font-semibold text-neutral-950">{{ $vendor->user->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-neutral-500">Email</dt>
                        <dd class="mt-1 break-words text-sm font-semibold text-neutral-950">{{ $vendor->user->email }}</dd>
                    </div>
                </dl>
            </x-card>

            <x-card>
                <h2 class="text-lg font-semibold text-neutral-950">Application</h2>
                <dl class="mt-6 space-y-4">
                    @foreach ([
                        'Submitted' => $vendor->submitted_at?->format('M j, Y g:i A'),
                        'Approved' => $vendor->approved_at?->format('M j, Y g:i A'),
                        'Rejected' => $vendor->rejected_at?->format('M j, Y g:i A'),
                    ] as $label => $value)
                        <div>
                            <dt class="text-sm font-medium text-neutral-500">{{ $label }}</dt>
                            <dd class="mt-1 text-sm font-semibold text-neutral-950">{{ $value ?: 'Not recorded' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>

            <x-card>
                <h2 class="text-lg font-semibold text-neutral-950">Recent Payouts</h2>
                <div class="mt-5 space-y-3">
                    @forelse ($recentPayouts as $payout)
                        <a href="{{ route('admin.payouts.show', $payout) }}" class="block rounded-md border border-neutral-200 bg-neutral-50 p-3 text-sm hover:border-brand-200">
                            <span class="font-semibold text-neutral-950">{{ $payout->payout_number }}</span>
                            <span class="mt-1 flex items-center justify-between gap-3">
                                <span class="text-neutral-600">{{ Money::format($payout->amount, $payout->currency) }}</span>
                                <x-badge :tone="$payout->status->badgeTone()">{{ $payout->status->label() }}</x-badge>
                            </span>
                        </a>
                    @empty
                        <p class="text-sm text-neutral-600">No payout history yet.</p>
                    @endforelse
                </div>
            </x-card>

            @if ($vendor->status === \App\Enums\VendorStatus::Pending)
                <x-card>
                    <h2 class="text-lg font-semibold text-neutral-950">Reject Application</h2>
                    <form method="POST" action="{{ route('admin.vendors.reject', $vendor) }}" class="mt-5 space-y-4">
                        @csrf
                        <x-textarea label="Reason" name="rejection_reason" placeholder="Explain what needs to change.">{{ old('rejection_reason') }}</x-textarea>
                        <x-button type="submit" variant="danger" class="w-full">Reject</x-button>
                    </form>
                </x-card>
            @elseif ($vendor->status === \App\Enums\VendorStatus::Rejected && $vendor->rejection_reason)
                <x-alert type="danger">
                    <p class="font-medium">Rejection Reason</p>
                    <p class="mt-1">{{ $vendor->rejection_reason }}</p>
                </x-alert>
            @endif
        </aside>
    </div>
@endsection
