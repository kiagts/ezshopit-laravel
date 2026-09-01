@extends('layouts.vendor')

@section('title', 'Edit Product')

@section('content')
    <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <x-badge :tone="$product->approval_status->badgeTone()">{{ $product->approval_status->label() }}</x-badge>
            <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Edit Product</h1>
            <p class="mt-2 text-sm leading-6 text-neutral-600">{{ $product->name }}</p>
        </div>

        <x-button :href="route('vendor.products.index')" variant="secondary">Back to Products</x-button>
    </div>

    @if (session('status'))
        <x-alert class="mb-6">{{ session('status') }}</x-alert>
    @endif

    @if ($errors->has('readiness'))
        <x-alert type="danger" class="mb-6">
            <p class="font-semibold">This product is not ready for review.</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->get('readiness') as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    @if ($product->approval_status === \App\Enums\ProductApprovalStatus::Rejected && $product->rejection_reason)
        <x-alert type="danger" class="mb-6">
            <span class="font-semibold">Needs changes:</span> {{ $product->rejection_reason }}
        </x-alert>
    @endif

    @if ($product->approval_status === \App\Enums\ProductApprovalStatus::Pending)
        <x-alert type="warning" class="mb-6">This product is currently under marketplace review and cannot be edited.</x-alert>
    @endif

    @include('vendor.products._form', [
        'action' => route('vendor.products.update', $product),
        'method' => 'PUT',
        'submitLabel' => 'Save Draft',
    ])

    @include('vendor.products._inventory')

    @include('vendor.products._media')

    <div class="mt-6 flex flex-wrap justify-end gap-3 border-t border-neutral-200 pt-6">
        @if ($product->approval_status !== \App\Enums\ProductApprovalStatus::Pending && $product->status !== \App\Enums\ProductStatus::Archived)
            <form method="POST" action="{{ route('vendor.products.submit', $product) }}" onsubmit="return confirm('Submit this product for marketplace review?')">
                @csrf
                <x-button type="submit">Submit for Approval</x-button>
            </form>
            <form method="POST" action="{{ route('vendor.products.destroy', $product) }}" onsubmit="return confirm('Archive this product?')">
                @csrf
                @method('DELETE')
                <x-button type="submit" variant="danger">Archive</x-button>
            </form>
        @endif
    </div>
@endsection
