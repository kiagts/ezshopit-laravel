@php
    use App\Enums\HomepageSectionType;
    use App\Enums\HomepageSectionItemType;

    $currentType = old('type', $section->type?->value ?? HomepageSectionType::Products->value);
    $settings = old('settings', $section->settings ?? []);
    $selectedIds = collect(old('item_ids', $section->exists ? $section->items->pluck('item_id')->all() : []))->map(fn ($id) => (string) $id)->all();
    $contentItems = old('content_items', $section->exists ? $section->contentItems->map(fn ($item) => [
        'title' => $item->title,
        'body' => $item->body,
        'image_path' => $item->image_path,
        'mobile_image_path' => $item->mobile_image_path,
        'image_alt' => $item->image_alt,
        'icon_key' => $item->icon_key,
        'cta_label' => $item->cta_label,
        'cta_url' => $item->cta_url,
        'settings' => $item->settings ?? [],
    ])->all() : []);
    $contentItems = array_values(array_pad($contentItems, 8, []));
@endphp

<div class="grid gap-6">
    <div class="grid gap-4 md:grid-cols-2">
        @if (! $section->exists)
            <label class="grid gap-1 text-sm font-medium text-neutral-700">
                Section type
                <select name="type" data-homepage-type class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected($currentType === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </label>
        @else
            <input type="hidden" name="type" value="{{ $section->type->value }}">
            <div>
                <p class="text-sm font-medium text-neutral-700">Section type</p>
                <p class="mt-2"><x-badge tone="neutral">{{ $section->type->label() }}</x-badge></p>
            </div>
        @endif

        <label class="grid gap-1 text-sm font-medium text-neutral-700">
            Stable key
            <input name="key" value="{{ old('key', $section->key) }}" class="rounded-md border border-neutral-300 px-3 py-2 text-sm" placeholder="featured-products" required>
        </label>

        <label class="grid gap-1 text-sm font-medium text-neutral-700">
            Internal name
            <input name="name" value="{{ old('name', $section->name) }}" class="rounded-md border border-neutral-300 px-3 py-2 text-sm" required>
        </label>

        <label class="grid gap-1 text-sm font-medium text-neutral-700">
            Public title
            <input name="title" value="{{ old('title', $section->title) }}" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
        </label>

        <label class="grid gap-1 text-sm font-medium text-neutral-700 md:col-span-2">
            Public subtitle
            <textarea name="subtitle" rows="3" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">{{ old('subtitle', $section->subtitle) }}</textarea>
        </label>
    </div>

    <div class="grid gap-4 rounded-lg border border-neutral-200 bg-neutral-50 p-4 md:grid-cols-4">
        <label class="flex items-center gap-2 text-sm font-medium text-neutral-700">
            <input type="hidden" name="is_enabled" value="0">
            <input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $section->is_enabled ?? true)) class="rounded border-neutral-300">
            Enabled
        </label>
        <label class="grid gap-1 text-sm font-medium text-neutral-700">
            Sort order
            <input name="sort_order" type="number" min="0" value="{{ old('sort_order', $section->sort_order) }}" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
        </label>
        <label class="grid gap-1 text-sm font-medium text-neutral-700">
            Starts at
            <input name="starts_at" type="datetime-local" value="{{ old('starts_at', $section->starts_at?->format('Y-m-d\TH:i')) }}" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
        </label>
        <label class="grid gap-1 text-sm font-medium text-neutral-700">
            Ends at
            <input name="ends_at" type="datetime-local" value="{{ old('ends_at', $section->ends_at?->format('Y-m-d\TH:i')) }}" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
        </label>
    </div>

    <div data-type-panel data-types="hero" class="{{ $currentType === 'hero' ? '' : 'hidden' }}">
        <h2 class="font-semibold text-neutral-950">Hero settings</h2>
        <div class="mt-3 grid gap-4 md:grid-cols-2">
            <input name="settings[eyebrow]" value="{{ data_get($settings, 'eyebrow') }}" placeholder="Eyebrow" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
            <input name="settings[primary_cta_label]" value="{{ data_get($settings, 'primary_cta_label') }}" placeholder="Primary CTA label" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
            <input name="settings[primary_cta_url]" value="{{ data_get($settings, 'primary_cta_url') }}" placeholder="/search" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
            <input name="settings[secondary_cta_label]" value="{{ data_get($settings, 'secondary_cta_label') }}" placeholder="Secondary CTA label" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
            <input name="settings[secondary_cta_url]" value="{{ data_get($settings, 'secondary_cta_url') }}" placeholder="/sell" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
            <input name="settings[benefits][]" value="{{ data_get($settings, 'benefits.0') }}" placeholder="Benefit 1" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
            <input name="settings[benefits][]" value="{{ data_get($settings, 'benefits.1') }}" placeholder="Benefit 2" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
            <input name="settings[benefits][]" value="{{ data_get($settings, 'benefits.2') }}" placeholder="Benefit 3" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
        </div>
    </div>

    <div data-type-panel data-types="products product_showcase ready_to_grow categories brands vendors" class="{{ in_array($currentType, ['products', 'product_showcase', 'ready_to_grow', 'categories', 'brands', 'vendors'], true) ? '' : 'hidden' }}">
        <h2 class="font-semibold text-neutral-950">Merchandising settings</h2>
        <div class="mt-3 grid gap-4 md:grid-cols-4">
            <select name="settings[source]" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
                @foreach (['manual' => 'Manual', 'featured' => 'Featured products', 'latest' => 'Latest', 'on_sale' => 'On sale', 'category' => 'Category products'] as $value => $label)
                    <option value="{{ $value }}" @selected(data_get($settings, 'source', 'latest') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <input name="settings[limit]" type="number" min="1" max="24" value="{{ data_get($settings, 'limit', 8) }}" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
            <select data-product-setting name="settings[category_id]" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
                <option value="">Category source</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) data_get($settings, 'category_id') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <input name="settings[view_all_url]" value="{{ data_get($settings, 'view_all_url') }}" placeholder="View all URL" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
            <input data-product-setting name="settings[cta_label]" value="{{ data_get($settings, 'cta_label') }}" placeholder="CTA label" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
            <input data-product-setting name="settings[cta_url]" value="{{ data_get($settings, 'cta_url') }}" placeholder="CTA URL" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
            <input data-product-setting name="settings[background_image]" value="{{ data_get($settings, 'background_image') }}" placeholder="Background image path" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
            <input data-product-setting name="settings[image_alt]" value="{{ data_get($settings, 'image_alt') }}" placeholder="Image alt text" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
        </div>
    </div>

    <div data-type-panel data-types="products product_showcase categories brands vendors ready_to_grow" class="{{ in_array($currentType, ['products', 'product_showcase', 'categories', 'brands', 'vendors', 'ready_to_grow'], true) ? '' : 'hidden' }}">
        <h2 class="font-semibold text-neutral-950">Manual entity selection</h2>
        <p class="mt-1 text-xs text-neutral-500">Used when source is Manual. Saved selections appear first in their curated order. Public eligibility still applies on the storefront.</p>
        <select name="item_ids[]" multiple size="10" class="mt-3 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm">
            @php
                $optionGroups = [
                    'Products' => $products->map(fn ($product) => ['id' => $product->id, 'label' => $product->name.' · '.$product->vendor?->display_name]),
                    'Categories' => $categories->map(fn ($category) => ['id' => $category->id, 'label' => $category->name]),
                    'Brands' => $brands->map(fn ($brand) => ['id' => $brand->id, 'label' => $brand->name]),
                    'Stores' => $vendors->map(fn ($vendor) => ['id' => $vendor->id, 'label' => $vendor->display_name]),
                ];
            @endphp
            @foreach ($optionGroups as $group => $options)
                @php
                    $entityTypes = match ($group) {
                        'Products' => 'products product_showcase ready_to_grow',
                        'Categories' => 'categories', 'Brands' => 'brands', 'Stores' => 'vendors',
                    };
                @endphp
                <optgroup label="{{ $group }}" data-entity-types="{{ $entityTypes }}">
                    @foreach ($options as $option)
                        <option value="{{ $option['id'] }}" @selected(in_array((string) $option['id'], $selectedIds, true))>{{ $option['label'] }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
    </div>

    <div data-type-panel data-types="hero promotion_grid banner_slider benefits" class="{{ in_array($currentType, ['hero', 'promotion_grid', 'banner_slider', 'benefits'], true) ? '' : 'hidden' }}">
        <h2 class="font-semibold text-neutral-950">Content items</h2>
        <p class="mt-1 text-xs text-neutral-500">Hero slides use group 0, 1, or 2. Promotion grid uses the first three items. Benefits use icon keys.</p>
        <div class="mt-3 grid gap-4">
            @foreach ($contentItems as $index => $item)
                <div class="rounded-lg border border-neutral-200 p-4">
                    <div class="grid gap-3 md:grid-cols-3">
                        <input name="content_items[{{ $index }}][title]" value="{{ data_get($item, 'title') }}" placeholder="Title" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
                        <input name="content_items[{{ $index }}][body]" value="{{ data_get($item, 'body') }}" placeholder="Body/subtitle" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
                        <input name="content_items[{{ $index }}][cta_url]" value="{{ data_get($item, 'cta_url') }}" placeholder="CTA URL" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
                        <input name="content_items[{{ $index }}][cta_label]" value="{{ data_get($item, 'cta_label') }}" placeholder="CTA label" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
                        <input name="content_items[{{ $index }}][image_path]" value="{{ data_get($item, 'image_path') }}" placeholder="Existing image path or HTTPS URL" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
                        <input name="content_items[{{ $index }}][image_alt]" value="{{ data_get($item, 'image_alt') }}" placeholder="Image alt" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
                        <input name="content_items[{{ $index }}][icon_key]" value="{{ data_get($item, 'icon_key') }}" placeholder="Icon key" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
                        <input name="content_items[{{ $index }}][settings][group]" value="{{ data_get($item, 'settings.group') }}" placeholder="Hero group" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
                        <input name="content_items[{{ $index }}][settings][eyebrow]" value="{{ data_get($item, 'settings.eyebrow') }}" placeholder="Banner eyebrow" class="rounded-md border border-neutral-300 px-3 py-2 text-sm">
                        <label class="text-xs font-medium text-neutral-600">
                            Desktop image upload
                            <input type="file" name="content_items[{{ $index }}][image_file]" accept=".jpg,.jpeg,.png,.webp" class="mt-1 block w-full text-xs">
                        </label>
                        <label class="text-xs font-medium text-neutral-600">Mobile image upload
                            <input type="file" name="content_items[{{ $index }}][mobile_image_file]" accept=".jpg,.jpeg,.png,.webp" class="mt-1 block w-full text-xs">
                        </label>
                        <input type="hidden" name="content_items[{{ $index }}][mobile_image_path]" value="{{ data_get($item, 'mobile_image_path') }}">
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @if ($errors->any())
        <x-alert type="error">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    <div class="flex justify-end gap-3 border-t border-neutral-200 pt-5">
        <a href="{{ route('admin.homepage.index') }}" class="inline-flex h-10 items-center rounded-md border border-neutral-300 bg-white px-4 text-sm font-semibold text-neutral-800 hover:bg-neutral-50">Cancel</a>
        <x-button type="submit">Save section</x-button>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const typeSelect = document.querySelector('[data-homepage-type]');
            const panels = document.querySelectorAll('[data-type-panel]');

            const syncPanels = () => {
                const type = typeSelect?.value || @json($currentType);

                panels.forEach((panel) => {
                    const types = (panel.dataset.types || '').split(' ');
                    const active = types.includes(type);
                    panel.classList.toggle('hidden', !active);
                    panel.querySelectorAll('input, select, textarea').forEach((field) => {
                        field.disabled = !active;
                    });
                });
                const productType = ['products', 'product_showcase', 'ready_to_grow'].includes(type);
                document.querySelectorAll('[data-product-setting]').forEach((field) => {
                    field.disabled = !productType;
                    field.classList.toggle('hidden', !productType);
                });
                const source = document.querySelector('[name="settings[source]"]');
                source.querySelectorAll('option').forEach((option) => {
                    option.disabled = !productType && !['manual', 'latest'].includes(option.value);
                    option.hidden = option.disabled;
                });
                if (source.selectedOptions[0]?.disabled) source.value = 'latest';
                document.querySelectorAll('[data-entity-types]').forEach((group) => {
                    const active = group.dataset.entityTypes.split(' ').includes(type);
                    group.disabled = !active;
                    group.hidden = !active;
                    group.querySelectorAll('option').forEach((option) => { option.disabled = !active; });
                });
            };

            typeSelect?.addEventListener('change', syncPanels);
            syncPanels();
        });
    </script>
@endpush
