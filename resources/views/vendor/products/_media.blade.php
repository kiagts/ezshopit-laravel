@php
    $mediaLocked = $product->approval_status === \App\Enums\ProductApprovalStatus::Pending || $product->status === \App\Enums\ProductStatus::Archived;
    $galleryImages = $product->galleryImages;
    $variantImageCount = $product->variants->sum(fn ($variant) => $variant->images->count());
@endphp

<section class="mt-8 space-y-8" id="media">
    <x-card>
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-neutral-950">Product Media</h2>
                <p class="mt-2 text-sm leading-6 text-neutral-600">Upload presentation-ready product images. Recommended: 1000 x 1000 or larger.</p>
            </div>
            <div class="flex flex-wrap gap-2 text-xs text-neutral-500">
                <x-badge tone="neutral">{{ $galleryImages->count() }} / {{ $mediaConfig['max_gallery_images'] }} gallery</x-badge>
                <x-badge tone="neutral">{{ $variantImageCount }} variant images</x-badge>
            </div>
        </div>

        @if ($mediaLocked)
            <x-alert type="warning" class="mt-6">Media changes are locked while a product is under review or archived.</x-alert>
        @elseif ($product->approval_status === \App\Enums\ProductApprovalStatus::Approved)
            <x-alert type="warning" class="mt-6">Changing media on an approved product will move it back to draft and require reapproval.</x-alert>
        @endif

        @error('images')
            <x-alert type="danger" class="mt-6">{{ $message }}</x-alert>
        @enderror
        @error('product_variant_id')
            <x-alert type="danger" class="mt-6">{{ $message }}</x-alert>
        @enderror

        <form method="POST" action="{{ route('vendor.products.images.store', $product) }}" enctype="multipart/form-data" class="mt-6 rounded-lg border border-neutral-200 bg-neutral-50 p-4" data-preview-form>
            @csrf
            <div class="grid gap-4 lg:grid-cols-[1fr_1fr_auto] lg:items-end">
                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-neutral-800">Product Gallery Images</span>
                    <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple @disabled($mediaLocked) class="block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm file:mr-4 file:rounded-md file:border-0 file:bg-neutral-900 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
                    @error('images.*')
                        <span class="mt-2 block text-sm text-red-700">{{ $message }}</span>
                    @enderror
                </label>
                <x-input label="Alt Text" name="alt_text" placeholder="Optional description" :readonly="$mediaLocked" />
                <x-button type="submit" :disabled="$mediaLocked">Upload</x-button>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-4" data-preview-root></div>
        </form>

        <div class="mt-6">
            <div class="flex items-center justify-between gap-4">
                <h3 class="text-sm font-semibold text-neutral-950">Product Gallery</h3>
                @if ($galleryImages->isNotEmpty() && ! $mediaLocked)
                    <form method="POST" action="{{ route('vendor.products.images.reorder', $product) }}" data-reorder-form>
                        @csrf
                        <div data-reorder-inputs>
                            @foreach ($galleryImages as $image)
                                <input type="hidden" name="images[]" value="{{ $image->id }}">
                            @endforeach
                        </div>
                        <x-button type="submit" variant="secondary" size="sm">Save Order</x-button>
                    </form>
                @endif
            </div>

            @if ($galleryImages->isEmpty())
                <div class="mt-4 rounded-lg border border-dashed border-neutral-300 bg-white px-6 py-10 text-center text-sm text-neutral-500">No product gallery images yet.</div>
            @else
                <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" data-gallery-grid>
                    @foreach ($galleryImages as $image)
                        <article class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm" data-image-card data-image-id="{{ $image->id }}">
                            <img src="{{ $image->url() }}" alt="{{ $image->alt_text ?: $product->name }}" class="aspect-square w-full object-cover">
                            <div class="space-y-3 p-4">
                                <div class="flex items-center justify-between gap-2">
                                    <x-badge :tone="$image->is_primary ? 'success' : 'neutral'">{{ $image->is_primary ? 'Primary' : 'Gallery' }}</x-badge>
                                    <span class="text-xs text-neutral-500">#{{ $image->sort_order }}</span>
                                </div>
                                <form method="POST" action="{{ route('vendor.products.images.update', [$product, $image]) }}" class="space-y-2">
                                    @csrf
                                    @method('PUT')
                                    <input name="alt_text" value="{{ $image->alt_text }}" placeholder="Alt text" @readonly($mediaLocked) class="h-10 w-full rounded-md border border-neutral-300 px-3 text-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
                                    <x-button type="submit" variant="secondary" size="sm" class="w-full" :disabled="$mediaLocked">Save Alt Text</x-button>
                                </form>
                                <div class="grid grid-cols-2 gap-2">
                                    <x-button type="button" variant="secondary" size="sm" data-move="-1" :disabled="$mediaLocked">Move Left</x-button>
                                    <x-button type="button" variant="secondary" size="sm" data-move="1" :disabled="$mediaLocked">Move Right</x-button>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    @if (! $image->is_primary)
                                        <form method="POST" action="{{ route('vendor.products.images.primary', [$product, $image]) }}">
                                            @csrf
                                            <x-button type="submit" variant="secondary" size="sm" class="w-full" :disabled="$mediaLocked">Set Primary</x-button>
                                        </form>
                                    @else
                                        <span></span>
                                    @endif
                                    <form method="POST" action="{{ route('vendor.products.images.destroy', [$product, $image]) }}" onsubmit="return confirm('Delete this product image?')">
                                        @csrf
                                        @method('DELETE')
                                        <x-button type="submit" variant="danger" size="sm" class="w-full" :disabled="$mediaLocked">Delete</x-button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </x-card>

    <x-card>
        <h2 class="text-lg font-semibold text-neutral-950">Variant Images</h2>
        <p class="mt-2 text-sm leading-6 text-neutral-600">Optional images can be assigned to one active variant. Variant default media is the first image by sort order.</p>

        <div class="mt-6 grid gap-5 xl:grid-cols-2">
            @foreach ($product->variants->where('is_active', true) as $variant)
                @php($variantLabel = $variant->attributeValues->pluck('value')->implode(' / ') ?: ($product->isSimple() ? 'Default Variant' : $variant->sku))
                <section class="rounded-lg border border-neutral-200 bg-white p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-neutral-950">{{ $variantLabel }}</h3>
                            <p class="mt-1 text-xs text-neutral-500">SKU: {{ $variant->sku }}</p>
                        </div>
                        <x-badge tone="neutral">{{ $variant->images->count() }} / {{ $mediaConfig['max_variant_images'] }}</x-badge>
                    </div>

                    <form method="POST" action="{{ route('vendor.products.images.store', $product) }}" enctype="multipart/form-data" class="mt-4 space-y-3" data-preview-form>
                        @csrf
                        <input type="hidden" name="product_variant_id" value="{{ $variant->id }}">
                        <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple @disabled($mediaLocked) class="block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm file:mr-4 file:rounded-md file:border-0 file:bg-neutral-900 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
                        <input name="alt_text" placeholder="Optional alt text" @readonly($mediaLocked) class="h-10 w-full rounded-md border border-neutral-300 px-3 text-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
                        <x-button type="submit" size="sm" :disabled="$mediaLocked">Upload Variant Images</x-button>
                        <div class="grid gap-3 sm:grid-cols-3" data-preview-root></div>
                    </form>

                    @if ($variant->images->isNotEmpty())
                        <div class="mt-4 grid gap-3 sm:grid-cols-3">
                            @foreach ($variant->images as $image)
                                <article class="overflow-hidden rounded-md border border-neutral-200">
                                    <img src="{{ $image->url() }}" alt="{{ $image->alt_text ?: $product->name.' - '.$variantLabel }}" class="aspect-square w-full object-cover">
                                    <div class="space-y-2 p-2">
                                        <p class="truncate text-xs text-neutral-500">{{ $image->alt_text ?: 'No alt text' }}</p>
                                        <form method="POST" action="{{ route('vendor.products.images.destroy', [$product, $image]) }}" onsubmit="return confirm('Delete this variant image?')">
                                            @csrf
                                            @method('DELETE')
                                            <x-button type="submit" variant="danger" size="sm" class="w-full" :disabled="$mediaLocked">Delete</x-button>
                                        </form>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endforeach
        </div>
    </x-card>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-preview-form]').forEach(form => {
        const input = form.querySelector('input[type="file"]');
        const root = form.querySelector('[data-preview-root]');
        input?.addEventListener('change', () => {
            root.innerHTML = '';
            Array.from(input.files || []).forEach(file => {
                const url = URL.createObjectURL(file);
                root.insertAdjacentHTML('beforeend', `<img src="${url}" alt="" class="aspect-square w-full rounded-md border border-neutral-200 object-cover">`);
            });
        });
    });

    const grid = document.querySelector('[data-gallery-grid]');
    const reorderForm = document.querySelector('[data-reorder-form]');
    const inputRoot = document.querySelector('[data-reorder-inputs]');
    const syncOrder = () => {
        if (!grid || !inputRoot) return;
        inputRoot.innerHTML = '';
        grid.querySelectorAll('[data-image-card]').forEach(card => {
            inputRoot.insertAdjacentHTML('beforeend', `<input type="hidden" name="images[]" value="${card.dataset.imageId}">`);
        });
    };

    grid?.addEventListener('click', event => {
        const button = event.target.closest('[data-move]');
        if (!button) return;
        const card = button.closest('[data-image-card]');
        const direction = Number(button.dataset.move);
        if (direction < 0 && card.previousElementSibling) {
            grid.insertBefore(card, card.previousElementSibling);
        }
        if (direction > 0 && card.nextElementSibling) {
            grid.insertBefore(card.nextElementSibling, card);
        }
        syncOrder();
    });

    reorderForm?.addEventListener('submit', syncOrder);
});
</script>
