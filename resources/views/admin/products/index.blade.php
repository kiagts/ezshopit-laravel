@extends('layouts.admin')

@section('title', 'Products')

@section('content')
    <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <x-badge tone="brand">Product moderation</x-badge>
            <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Products</h1>
            <p class="mt-2 text-sm leading-6 text-neutral-600">Review submitted seller listings and monitor catalog lifecycle state.</p>
        </div>
    </div>

    @if (session('status'))
        <x-alert class="mb-6">{{ session('status') }}</x-alert>
    @endif

    <form method="GET" action="{{ route('admin.products.index') }}" class="mb-6 grid gap-3 rounded-lg border border-neutral-200 bg-white p-4 shadow-sm lg:grid-cols-[1fr_11rem_11rem_10rem_auto]">
        <input name="q" type="search" value="{{ $filters['q'] }}" placeholder="Search product, seller, or SKU" class="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
        <select name="approval" class="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
            <option value="">All approvals</option>
            @foreach ($approvals as $approval)
                <option value="{{ $approval->value }}" @selected($filters['approval'] === $approval->value)>{{ $approval->label() }}</option>
            @endforeach
        </select>
        <select name="status" class="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
            <option value="">All statuses</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
        <select name="type" class="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
            <option value="">All types</option>
            @foreach ($types as $type)
                <option value="{{ $type->value }}" @selected($filters['type'] === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
        <x-button type="submit" variant="secondary">Filter</x-button>
    </form>

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        @if ($products->isEmpty())
            <x-empty-state title="No products found" description="Submitted and seeded products will appear here for moderation." />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase text-neutral-500">
                        <tr>
                            <th class="px-4 py-3">Image</th>
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3">Vendor</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Brand</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Variants</th>
                            <th class="px-4 py-3">Price</th>
                            <th class="px-4 py-3">Approval</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Submitted</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($products as $product)
                            @php($displayImage = $product->displayImage())
                            <tr class="hover:bg-neutral-50">
                                <td class="px-4 py-4">
                                    @if ($displayImage)
                                        <img src="{{ $displayImage->url() }}" alt="{{ $displayImage->alt_text ?: $product->name }}" class="h-14 w-14 rounded-md object-cover">
                                    @else
                                        <div class="flex h-14 w-14 items-center justify-center rounded-md bg-neutral-100 text-xs font-semibold text-neutral-500">IMG</div>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <a href="{{ route('admin.products.show', $product) }}" class="font-semibold text-neutral-950 hover:text-brand-800">{{ $product->name }}</a>
                                    <p class="mt-1 text-xs text-neutral-500">{{ $product->slug }}</p>
                                </td>
                                <td class="px-4 py-4 text-neutral-700">{{ $product->vendor?->display_name }}</td>
                                <td class="px-4 py-4 text-neutral-700">{{ $product->category?->name }}</td>
                                <td class="px-4 py-4 text-neutral-700">{{ $product->brand?->name ?? 'Unbranded' }}</td>
                                <td class="px-4 py-4 text-neutral-700">{{ $product->type->label() }}</td>
                                <td class="px-4 py-4 text-neutral-700">{{ $product->variants_count }}</td>
                                <td class="px-4 py-4 text-neutral-700">
                                    @if ($product->min_base_price === null)
                                        -
                                    @elseif ($product->min_base_price === $product->max_base_price)
                                        {{ $money::format((int) $product->min_base_price) }}
                                    @else
                                        {{ $money::format((int) $product->min_base_price) }} - {{ $money::format((int) $product->max_base_price) }}
                                    @endif
                                </td>
                                <td class="px-4 py-4"><x-badge :tone="$product->approval_status->badgeTone()">{{ $product->approval_status->label() }}</x-badge></td>
                                <td class="px-4 py-4"><x-badge :tone="$product->status->badgeTone()">{{ $product->status->label() }}</x-badge></td>
                                <td class="px-4 py-4 text-neutral-700">{{ $product->submitted_at?->format('M j, Y') ?? '-' }}</td>
                                <td class="px-4 py-4">
                                    <x-button :href="route('admin.products.show', $product)" variant="secondary" size="sm">Review</x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="mt-6">
        {{ $products->links() }}
    </div>
@endsection
