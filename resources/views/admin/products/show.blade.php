@extends('layouts.admin')

@section('title', $product->name)

@section('content')
    <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <div class="flex flex-wrap gap-2">
                <x-badge :tone="$product->approval_status->badgeTone()">{{ $product->approval_status->label() }}</x-badge>
                <x-badge :tone="$product->status->badgeTone()">{{ $product->status->label() }}</x-badge>
            </div>
            <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">{{ $product->name }}</h1>
            <p class="mt-2 text-sm leading-6 text-neutral-600">{{ $product->slug }}</p>
        </div>

        <x-button :href="route('admin.products.index')" variant="secondary">Back to Products</x-button>
    </div>

    @if (session('status'))
        <x-alert class="mb-6">{{ session('status') }}</x-alert>
    @endif

    @if ($errors->any())
        <x-alert type="danger" class="mb-6">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
        <div class="space-y-6">
            <x-card>
                <h2 class="text-lg font-semibold text-neutral-950">Product Gallery</h2>
                @if ($product->galleryImages->isEmpty())
                    <p class="mt-4 text-sm text-neutral-500">No product gallery images uploaded.</p>
                @else
                    @php($primary = $product->displayImage())
                    @if ($primary)
                        <div class="mt-6 overflow-hidden rounded-lg border border-neutral-200">
                            <img src="{{ $primary->url() }}" alt="{{ $primary->alt_text ?: $product->name }}" class="max-h-[28rem] w-full object-cover">
                        </div>
                    @endif
                    <div class="mt-4 grid gap-3 sm:grid-cols-3 xl:grid-cols-5">
                        @foreach ($product->galleryImages as $image)
                            <div class="overflow-hidden rounded-md border border-neutral-200 bg-white">
                                <img src="{{ $image->url() }}" alt="{{ $image->alt_text ?: $product->name }}" class="aspect-square w-full object-cover">
                                <div class="flex items-center justify-between gap-2 p-2 text-xs text-neutral-500">
                                    <span>{{ $image->alt_text ?: 'No alt text' }}</span>
                                    @if ($image->is_primary)
                                        <x-badge tone="success">Primary</x-badge>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>

            <x-card>
                <h2 class="text-lg font-semibold text-neutral-950">Overview</h2>
                <dl class="mt-6 grid gap-4 text-sm md:grid-cols-2">
                    <div><dt class="text-neutral-500">Vendor</dt><dd class="mt-1 font-medium text-neutral-950">{{ $product->vendor->display_name }}</dd></div>
                    <div><dt class="text-neutral-500">Category</dt><dd class="mt-1 font-medium text-neutral-950">{{ $product->category->name }}</dd></div>
                    <div><dt class="text-neutral-500">Brand</dt><dd class="mt-1 font-medium text-neutral-950">{{ $product->brand?->name ?? 'Unbranded' }}</dd></div>
                    <div><dt class="text-neutral-500">Type</dt><dd class="mt-1 font-medium text-neutral-950">{{ $product->type->label() }}</dd></div>
                </dl>
            </x-card>

            <x-card>
                <h2 class="text-lg font-semibold text-neutral-950">Description</h2>
                <p class="mt-4 text-sm font-medium text-neutral-800">{{ $product->short_description ?: 'No short description provided.' }}</p>
                <p class="mt-4 whitespace-pre-line text-sm leading-6 text-neutral-600">{{ $product->description ?: 'No description provided.' }}</p>
            </x-card>

            <x-card>
                <h2 class="text-lg font-semibold text-neutral-950">Inventory</h2>
                <p class="mt-2 text-sm text-neutral-600">Read-only stock by sellable variant. Inventory changes do not require moderation approval.</p>
                <div class="mt-6 overflow-x-auto rounded-lg border border-neutral-200">
                    <table class="min-w-full divide-y divide-neutral-200 text-sm">
                        <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase text-neutral-500">
                            <tr>
                                <th class="px-4 py-3">Variant</th>
                                <th class="px-4 py-3">SKU</th>
                                <th class="px-4 py-3">On Hand</th>
                                <th class="px-4 py-3">Reserved</th>
                                <th class="px-4 py-3">Available</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @foreach ($product->variants as $variant)
                                @php($inventory = $variant->inventory)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-neutral-950">{{ $variant->displayName() }}</td>
                                    <td class="px-4 py-3 font-mono text-xs text-neutral-600">{{ $variant->sku }}</td>
                                    <td class="px-4 py-3 text-neutral-700">{{ $inventory?->on_hand ?? 0 }}</td>
                                    <td class="px-4 py-3 text-neutral-700">{{ $inventory?->reserved ?? 0 }}</td>
                                    <td class="px-4 py-3 font-semibold text-neutral-950">{{ $inventory?->availableQuantity() ?? 0 }}</td>
                                    <td class="px-4 py-3">
                                        @if ($inventory)
                                            <x-badge :tone="$inventory->stockStatusTone()">{{ $inventory->stockStatusLabel() }}</x-badge>
                                        @else
                                            <x-badge tone="neutral">Not Initialized</x-badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            <x-card>
                <h2 class="text-lg font-semibold text-neutral-950">Variants & Pricing</h2>
                <div class="mt-6 overflow-x-auto rounded-lg border border-neutral-200">
                    <table class="min-w-full divide-y divide-neutral-200 text-sm">
                        <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase text-neutral-500">
                            <tr>
                                <th class="px-4 py-3">Variant</th>
                                <th class="px-4 py-3">SKU</th>
                                <th class="px-4 py-3">Regular Price</th>
                                <th class="px-4 py-3">Sale Price</th>
                                <th class="px-4 py-3">Active</th>
                                <th class="px-4 py-3">Default</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @foreach ($product->variants as $variant)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-neutral-950">
                                        @if ($product->isSimple())
                                            Default
                                        @else
                                            {{ $variant->attributeValues->pluck('value')->implode(' / ') ?: $variant->name }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-neutral-700">{{ $variant->sku }}</td>
                                    <td class="px-4 py-3 text-neutral-700">{{ $money::format($variant->base_price) }}</td>
                                    <td class="px-4 py-3 text-neutral-700">{{ $money::format($variant->sale_price) }}</td>
                                    <td class="px-4 py-3">{{ $variant->is_active ? 'Yes' : 'No' }}</td>
                                    <td class="px-4 py-3">{{ $variant->is_default ? 'Yes' : 'No' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            <x-card>
                <h2 class="text-lg font-semibold text-neutral-950">Variant Images</h2>
                @php($variantsWithImages = $product->variants->filter(fn ($variant) => $variant->images->isNotEmpty()))
                @if ($variantsWithImages->isEmpty())
                    <p class="mt-4 text-sm text-neutral-500">No variant-specific images uploaded.</p>
                @else
                    <div class="mt-6 space-y-5">
                        @foreach ($variantsWithImages as $variant)
                            @php($variantLabel = $variant->attributeValues->pluck('value')->implode(' / ') ?: ($product->isSimple() ? 'Default Variant' : $variant->sku))
                            <section>
                                <h3 class="text-sm font-semibold text-neutral-950">{{ $variantLabel }}</h3>
                                <p class="mt-1 text-xs text-neutral-500">SKU: {{ $variant->sku }}</p>
                                <div class="mt-3 grid gap-3 sm:grid-cols-4">
                                    @foreach ($variant->images as $image)
                                        <div class="overflow-hidden rounded-md border border-neutral-200">
                                            <img src="{{ $image->url() }}" alt="{{ $image->alt_text ?: $product->name.' - '.$variantLabel }}" class="aspect-square w-full object-cover">
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    </div>
                @endif
            </x-card>

            <x-card>
                <h2 class="text-lg font-semibold text-neutral-950">Specifications</h2>
                @if ($product->specifications->isEmpty())
                    <p class="mt-4 text-sm text-neutral-500">No structured specifications provided.</p>
                @else
                    <dl class="mt-6 grid gap-4 text-sm md:grid-cols-2">
                        @foreach ($product->specifications as $specification)
                            <div>
                                <dt class="text-neutral-500">{{ $specification->attribute->name }}</dt>
                                <dd class="mt-1 font-medium text-neutral-950">
                                    @if ($specification->values->isNotEmpty())
                                        {{ $specification->values->pluck('value')->implode(', ') }}
                                    @elseif ($specification->boolean_value !== null)
                                        {{ $specification->boolean_value ? 'Yes' : 'No' }}
                                    @else
                                        {{ $specification->text_value ?? $specification->number_value }}
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </x-card>
        </div>

        <aside class="space-y-6">
            <x-card>
                <h2 class="text-lg font-semibold text-neutral-950">Moderation</h2>
                <dl class="mt-5 space-y-3 text-sm">
                    <div><dt class="text-neutral-500">Submitted</dt><dd class="mt-1 font-medium text-neutral-950">{{ $product->submitted_at?->format('M j, Y g:i A') ?? '-' }}</dd></div>
                    <div><dt class="text-neutral-500">Approved</dt><dd class="mt-1 font-medium text-neutral-950">{{ $product->approved_at?->format('M j, Y g:i A') ?? '-' }}</dd></div>
                    <div><dt class="text-neutral-500">Rejected</dt><dd class="mt-1 font-medium text-neutral-950">{{ $product->rejected_at?->format('M j, Y g:i A') ?? '-' }}</dd></div>
                </dl>

                @if ($product->rejection_reason)
                    <div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $product->rejection_reason }}</div>
                @endif

                @if ($product->approval_status === \App\Enums\ProductApprovalStatus::Pending)
                    <div class="mt-6 flex flex-col gap-3">
                        <form method="POST" action="{{ route('admin.products.approve', $product) }}">
                            @csrf
                            <x-button type="submit" class="w-full">Approve Product</x-button>
                        </form>
                        <form method="POST" action="{{ route('admin.products.reject', $product) }}" class="space-y-3">
                            @csrf
                            <x-textarea label="Rejection Reason" name="rejection_reason" rows="4" placeholder="Explain what the seller needs to fix">{{ old('rejection_reason') }}</x-textarea>
                            <x-button type="submit" variant="danger" class="w-full">Reject Product</x-button>
                        </form>
                    </div>
                @endif
            </x-card>

            <x-card>
                <h2 class="text-lg font-semibold text-neutral-950">Media Review</h2>
                <p class="mt-2 text-sm leading-6 text-neutral-600">Admin media access is read-only. Vendors own uploads and media corrections.</p>
            </x-card>
        </aside>
    </div>
@endsection
