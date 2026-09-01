@extends('layouts.vendor')

@section('title', 'Application Status')

@section('content')
    <div class="mx-auto max-w-3xl">
        <x-card padding="lg">
            <x-badge :tone="$vendor->status->badgeTone()">{{ $vendor->status->label() }}</x-badge>

            @if ($vendor->status === \App\Enums\VendorStatus::Pending)
                <h1 class="mt-5 text-3xl font-semibold tracking-normal text-neutral-950">Application Under Review</h1>
                <p class="mt-3 text-sm leading-6 text-neutral-600">Your store application has been submitted. A marketplace admin is reviewing your details.</p>
            @elseif ($vendor->status === \App\Enums\VendorStatus::Rejected)
                <h1 class="mt-5 text-3xl font-semibold tracking-normal text-neutral-950">Application Needs Changes</h1>
                <p class="mt-3 text-sm leading-6 text-neutral-600">Review the admin feedback, update your store application, and submit it again.</p>
                <x-alert type="danger" class="mt-6">{{ $vendor->rejection_reason }}</x-alert>
                <div class="mt-6">
                    <x-button :href="route('vendor.onboarding.edit')">Edit & Resubmit</x-button>
                </div>
            @elseif ($vendor->status === \App\Enums\VendorStatus::Suspended)
                <h1 class="mt-5 text-3xl font-semibold tracking-normal text-neutral-950">Store Suspended</h1>
                <p class="mt-3 text-sm leading-6 text-neutral-600">Your seller operations are temporarily restricted. You can still sign in and view this status.</p>
            @endif

            <dl class="mt-8 grid gap-4 border-t border-neutral-200 pt-6 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-medium text-neutral-500">Store</dt>
                    <dd class="mt-1 text-sm font-semibold text-neutral-950">{{ $vendor->display_name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-neutral-500">Submitted</dt>
                    <dd class="mt-1 text-sm font-semibold text-neutral-950">{{ $vendor->submitted_at?->format('M j, Y') ?? 'Not submitted' }}</dd>
                </div>
            </dl>
        </x-card>
    </div>
@endsection
