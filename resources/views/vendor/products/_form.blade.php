@php
    $locked = $product->exists && in_array($product->approval_status, [\App\Enums\ProductApprovalStatus::Pending], true);
    $configured = $product->exists && ($product->variants()->exists() || $product->specifications()->exists());
    $currentType = old('type', $product->type?->value ?? \App\Enums\ProductType::Simple->value);
    $defaultVariant = $product->defaultVariant ?: $product->variants->first();
    $existingVariants = $product->variants->map(function ($variant) use ($money) {
        return [
            'id' => $variant->id,
            'sku' => $variant->sku,
            'name' => $variant->name,
            'base_price' => $money::input($variant->base_price),
            'sale_price' => $money::input($variant->sale_price),
            'is_active' => $variant->is_active,
            'is_default' => $variant->is_default,
            'sort_order' => $variant->sort_order,
            'attribute_value_ids' => $variant->attributeValues->mapWithKeys(fn ($value) => [(string) $value->pivot->product_attribute_id => $value->id])->all(),
        ];
    })->values();
    $existingSpecs = $product->specifications->mapWithKeys(function ($spec) {
        return [(string) $spec->product_attribute_id => [
            'text_value' => $spec->text_value,
            'number_value' => $spec->number_value,
            'boolean_value' => $spec->boolean_value,
            'value_ids' => $spec->values->pluck('id')->all(),
        ]];
    });
@endphp

<form method="POST" action="{{ $action }}" class="space-y-8" data-product-form
    data-config-url-template="{{ url('/vendor/catalog/categories/__CATEGORY__/configuration') }}"
    data-existing-variants='@json($existingVariants)'
    data-existing-specifications='@json($existingSpecs)'
>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    @if ($errors->any() && ! $errors->has('readiness'))
        <x-alert type="danger">Please review the highlighted fields and try again.</x-alert>
    @endif

    <x-card>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-neutral-950">General</h2>
                <p class="mt-2 text-sm leading-6 text-neutral-600">Choose where this product belongs, then describe the customer-facing listing.</p>
            </div>
            @if ($configured)
                <x-badge tone="neutral">Category locked after configuration</x-badge>
            @endif
        </div>

        <div class="mt-6 grid gap-5 md:grid-cols-2">
            <x-select label="Category" name="category_id" data-category-select :disabled="$locked || $configured">
                <option value="">Choose a leaf category</option>
                @foreach ($categories as $category)
                    <option value="{{ $category['id'] }}" @selected((int) old('category_id', $product->category_id) === $category['id'])>{{ $category['label'] }}</option>
                @endforeach
            </x-select>
            @if ($configured)
                <input type="hidden" name="category_id" value="{{ $product->category_id }}">
            @endif

            <x-select label="Brand" name="brand_id" :disabled="$locked">
                <option value="">No Brand / Unbranded</option>
                @foreach ($brands as $brand)
                    <option value="{{ $brand->id }}" @selected((int) old('brand_id', $product->brand_id) === $brand->id)>{{ $brand->name }}</option>
                @endforeach
            </x-select>

            @if ($product->exists)
                <div>
                    <span class="mb-2 block text-sm font-medium text-neutral-800">Product Type</span>
                    <div class="flex h-11 items-center rounded-md border border-neutral-200 bg-neutral-50 px-3 text-sm text-neutral-700">{{ $product->type->label() }}</div>
                    <input type="hidden" name="type" value="{{ $product->type->value }}">
                </div>
            @else
                <x-select label="Product Type" name="type" data-type-select :disabled="$locked">
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected($currentType === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </x-select>
            @endif

            <x-input label="Product Name" name="name" :value="old('name', $product->name)" placeholder="Apple iPhone 17 Pro" :readonly="$locked" />
            <x-input label="Slug" name="slug" :value="old('slug', $product->slug)" placeholder="Leave blank to generate" :readonly="$locked" />

            <div class="md:col-span-2">
                <x-textarea label="Short Description" name="short_description" rows="3" placeholder="Short customer-facing summary" :readonly="$locked">{{ old('short_description', $product->short_description) }}</x-textarea>
            </div>
            <div class="md:col-span-2">
                <x-textarea label="Description" name="description" rows="7" placeholder="Describe the product clearly enough for marketplace review" :readonly="$locked">{{ old('description', $product->description) }}</x-textarea>
                <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 md:col-span-2"><p class="text-sm font-semibold">Translations</p><div class="mt-3 grid gap-3 md:grid-cols-2"><x-input label="Urdu name" name="translations[ur][name]" :readonly="$locked" :value="old('translations.ur.name', data_get($product->translations,'ur.name'))" /><x-input label="Arabic name" name="translations[ar][name]" :readonly="$locked" :value="old('translations.ar.name', data_get($product->translations,'ar.name'))" /></div></div>
            </div>
        </div>
    </x-card>

    <x-card>
        <h2 class="text-lg font-semibold text-neutral-950">Selling Information</h2>
        <p class="mt-2 text-sm leading-6 text-neutral-600">Prices are entered in {{ $money::currencyCode() }} and stored internally as integer minor units.</p>

        <div class="mt-6 space-y-6" data-simple-section>
            <div class="grid gap-5 md:grid-cols-3">
                <x-input label="SKU" name="variants[0][sku]" :value="old('variants.0.sku', $defaultVariant?->sku)" placeholder="SKU-001" :readonly="$locked" />
                <x-input label="Regular Price" name="variants[0][base_price]" :value="old('variants.0.base_price', $money::input($defaultVariant?->base_price))" placeholder="1299.99" :readonly="$locked" />
                <x-input label="Sale Price" name="variants[0][sale_price]" :value="old('variants.0.sale_price', $money::input($defaultVariant?->sale_price))" placeholder="Optional" :readonly="$locked" />
            </div>
        </div>

        <div class="mt-6 space-y-6" data-variant-section hidden>
            <div>
                <h3 class="text-sm font-semibold text-neutral-950">Variant Options</h3>
                <div class="mt-4 grid gap-4 lg:grid-cols-2" data-variant-options></div>
            </div>

            <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4">
                <div class="grid gap-3 md:grid-cols-[1fr_1fr_auto_auto]">
                    <input type="text" data-bulk-base placeholder="Regular price for all" class="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100" @readonly($locked)>
                    <input type="text" data-bulk-sale placeholder="Sale price for all" class="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100" @readonly($locked)>
                    <x-button type="button" variant="secondary" data-apply-prices :disabled="$locked">Apply Prices</x-button>
                    <x-button type="button" variant="secondary" data-generate-skus :disabled="$locked">Generate SKUs</x-button>
                </div>
            </div>

            <div class="overflow-x-auto rounded-lg border border-neutral-200">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase text-neutral-500">
                        <tr>
                            <th class="px-4 py-3">Variant</th>
                            <th class="px-4 py-3">SKU</th>
                            <th class="px-4 py-3">Regular Price</th>
                            <th class="px-4 py-3">Sale Price</th>
                            <th class="px-4 py-3">Default</th>
                            <th class="px-4 py-3">Active</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 bg-white" data-variant-rows>
                        <tr><td colspan="6" class="px-4 py-6 text-sm text-neutral-500">Choose variant option values to generate combinations.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </x-card>

    <x-card>
        <h2 class="text-lg font-semibold text-neutral-950">Product Specifications</h2>
        <p class="mt-2 text-sm leading-6 text-neutral-600">Specification fields appear from the selected category configuration.</p>
        <div class="mt-6 grid gap-5 md:grid-cols-2" data-specifications>
            <p class="text-sm text-neutral-500 md:col-span-2">Choose a category to load specifications.</p>
        </div>
    </x-card>

    <x-card>
        <h2 class="text-lg font-semibold text-neutral-950">Product Media</h2>
        <p class="mt-2 text-sm leading-6 text-neutral-600">
            @if ($product->exists)
                Manage product gallery and variant images below after saving product information.
            @else
                Save the product first to add images.
            @endif
        </p>
    </x-card>

    <div class="flex flex-col gap-3 border-t border-neutral-200 pt-6 sm:flex-row sm:justify-end">
        <x-button :href="route('vendor.products.index')" variant="secondary">Cancel</x-button>
        <x-button type="submit" :disabled="$locked || $product->status === \App\Enums\ProductStatus::Archived">{{ $submitLabel }}</x-button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-product-form]');
    if (!form) return;

    const category = form.querySelector('[data-category-select]');
    const type = form.querySelector('[data-type-select]') || form.querySelector('input[name="type"]');
    const simpleSection = form.querySelector('[data-simple-section]');
    const variantSection = form.querySelector('[data-variant-section]');
    const optionsRoot = form.querySelector('[data-variant-options]');
    const rowsRoot = form.querySelector('[data-variant-rows]');
    const specsRoot = form.querySelector('[data-specifications]');
    const locked = @json($locked);
    const existingVariants = JSON.parse(form.dataset.existingVariants || '[]');
    const existingSpecs = JSON.parse(form.dataset.existingSpecifications || '{}');
    let config = { variant_attributes: [], specification_attributes: [] };
    let firstRender = true;

    const keyFor = pairs => Object.keys(pairs).sort((a, b) => Number(a) - Number(b)).map(id => `${id}:${pairs[id]}`).join('|') || 'default';
    const existingByKey = new Map(existingVariants.map(variant => [keyFor(variant.attribute_value_ids || {}), variant]));

    const setTypeVisibility = () => {
        const isVariant = type.value === 'variant';
        simpleSection.hidden = isVariant;
        variantSection.hidden = !isVariant;
        simpleSection.querySelectorAll('input, select, textarea').forEach(input => input.disabled = isVariant);
        variantSection.querySelectorAll('input, select, textarea, button').forEach(input => {
            if (!locked) input.disabled = !isVariant;
        });
    };

    const loadConfig = async () => {
        setTypeVisibility();
        if (!category.value) return;
        const url = form.dataset.configUrlTemplate.replace('__CATEGORY__', category.value);
        const response = await window.axios.get(url);
        config = response.data;
        renderOptions();
        renderSpecs();
        firstRender = false;
    };

    const selectedValuesByAttribute = () => {
        const selected = {};
        optionsRoot.querySelectorAll('input[type="checkbox"]:checked').forEach(input => {
            selected[input.dataset.attributeId] ||= [];
            selected[input.dataset.attributeId].push({
                id: input.value,
                label: input.dataset.label,
                code: input.dataset.code,
            });
        });
        return selected;
    };

    const cartesian = groups => groups.reduce((acc, group) => acc.flatMap(row => group.map(value => [...row, value])), [[]]);

    const renderOptions = () => {
        optionsRoot.innerHTML = '';
        if (!config.variant_attributes.length) {
            optionsRoot.innerHTML = '<p class="text-sm text-neutral-500">This category has no variant options.</p>';
            renderRows();
            return;
        }

        const existingSelected = new Set();
        if (firstRender) {
            existingVariants.forEach(variant => Object.values(variant.attribute_value_ids || {}).forEach(id => existingSelected.add(String(id))));
        }

        config.variant_attributes.forEach(attribute => {
            const block = document.createElement('div');
            block.className = 'rounded-lg border border-neutral-200 p-4';
            block.innerHTML = `<div class="font-medium text-neutral-950">${attribute.name}${attribute.is_required ? ' <span class="text-red-600">*</span>' : ''}</div><div class="mt-3 grid gap-2"></div>`;
            const list = block.querySelector('div:last-child');
            attribute.values.forEach(value => {
                const checked = existingSelected.has(String(value.id));
                list.insertAdjacentHTML('beforeend', `
                    <label class="inline-flex items-center gap-2 text-sm text-neutral-700">
                        <input type="checkbox" value="${value.id}" data-attribute-id="${attribute.id}" data-label="${value.value}" data-code="${value.code}" ${checked ? 'checked' : ''} ${locked ? 'disabled' : ''} class="h-4 w-4 rounded border-neutral-300 text-brand-700 focus:ring-brand-600">
                        <span>${value.value}</span>
                    </label>
                `);
            });
            optionsRoot.appendChild(block);
        });
        renderRows();
    };

    const renderRows = () => {
        rowsRoot.innerHTML = '';
        const selected = selectedValuesByAttribute();
        const groups = Object.entries(selected).map(([attributeId, values]) => values.map(value => ({ attributeId, ...value }))).filter(group => group.length);
        if (!groups.length) {
            rowsRoot.innerHTML = '<tr><td colspan="6" class="px-4 py-6 text-sm text-neutral-500">Choose variant option values to generate combinations.</td></tr>';
            return;
        }
        cartesian(groups).forEach((combo, index) => {
            const pairs = Object.fromEntries(combo.map(value => [value.attributeId, value.id]));
            const key = keyFor(pairs);
            const existing = existingByKey.get(key) || {};
            const label = combo.map(value => value.label).join(' / ');
            const attrs = combo.map(value => `<input type="hidden" name="variants[${index}][attribute_value_ids][${value.attributeId}]" value="${value.id}">`).join('');
            rowsRoot.insertAdjacentHTML('beforeend', `
                <tr>
                    <td class="px-4 py-3 font-medium text-neutral-950">${label}${attrs}<input type="hidden" name="variants[${index}][name]" value="${label}"></td>
                    <td class="px-4 py-3"><input name="variants[${index}][sku]" value="${existing.sku || ''}" ${locked ? 'readonly' : ''} class="h-10 w-44 rounded-md border border-neutral-300 px-3 text-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100"></td>
                    <td class="px-4 py-3"><input name="variants[${index}][base_price]" value="${existing.base_price || ''}" ${locked ? 'readonly' : ''} class="h-10 w-32 rounded-md border border-neutral-300 px-3 text-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100"></td>
                    <td class="px-4 py-3"><input name="variants[${index}][sale_price]" value="${existing.sale_price || ''}" ${locked ? 'readonly' : ''} class="h-10 w-32 rounded-md border border-neutral-300 px-3 text-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100"></td>
                    <td class="px-4 py-3 text-center"><input type="radio" name="default_variant_index" value="${index}" ${existing.is_default || index === 0 ? 'checked' : ''} ${locked ? 'disabled' : ''} class="h-4 w-4 border-neutral-300 text-brand-700 focus:ring-brand-600"></td>
                    <td class="px-4 py-3 text-center"><input type="hidden" name="variants[${index}][is_active]" value="0"><input type="checkbox" name="variants[${index}][is_active]" value="1" ${existing.is_active === false ? '' : 'checked'} ${locked ? 'disabled' : ''} class="h-4 w-4 rounded border-neutral-300 text-brand-700 focus:ring-brand-600"></td>
                </tr>
            `);
        });
        syncDefault();
    };

    const syncDefault = () => {
        rowsRoot.querySelectorAll('input[name="default_variant_index"]').forEach(radio => {
            const index = radio.value;
            let hidden = rowsRoot.querySelector(`input[name="variants[${index}][is_default]"]`);
            if (!hidden) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = `variants[${index}][is_default]`;
                radio.closest('tr').appendChild(hidden);
            }
            hidden.value = radio.checked ? '1' : '0';
        });
    };

    const renderSpecs = () => {
        specsRoot.innerHTML = '';
        if (!config.specification_attributes.length) {
            specsRoot.innerHTML = '<p class="text-sm text-neutral-500 md:col-span-2">This category has no structured specifications.</p>';
            return;
        }
        config.specification_attributes.forEach(attribute => {
            const existing = existingSpecs[String(attribute.id)] || {};
            const label = `${attribute.name}${attribute.is_required ? ' *' : ''}`;
            let field = '';
            if (attribute.input_type === 'select') {
                field = `<select name="specifications[${attribute.id}][product_attribute_value_id]" ${locked ? 'disabled' : ''} class="block h-11 w-full rounded-md border border-neutral-300 bg-white px-3 text-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100"><option value="">Choose ${attribute.name}</option>${attribute.values.map(value => `<option value="${value.id}" ${(existing.value_ids || []).includes(value.id) ? 'selected' : ''}>${value.value}</option>`).join('')}</select>`;
            } else if (attribute.input_type === 'multiselect') {
                field = `<div class="grid gap-2 rounded-md border border-neutral-200 p-3">${attribute.values.map(value => `<label class="inline-flex items-center gap-2 text-sm text-neutral-700"><input type="checkbox" name="specifications[${attribute.id}][product_attribute_value_ids][]" value="${value.id}" ${(existing.value_ids || []).includes(value.id) ? 'checked' : ''} ${locked ? 'disabled' : ''} class="h-4 w-4 rounded border-neutral-300 text-brand-700 focus:ring-brand-600">${value.value}</label>`).join('')}</div>`;
            } else if (attribute.input_type === 'boolean') {
                field = `<select name="specifications[${attribute.id}][boolean_value]" ${locked ? 'disabled' : ''} class="block h-11 w-full rounded-md border border-neutral-300 bg-white px-3 text-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100"><option value="">Choose</option><option value="1" ${existing.boolean_value === true ? 'selected' : ''}>Yes</option><option value="0" ${existing.boolean_value === false ? 'selected' : ''}>No</option></select>`;
            } else if (attribute.input_type === 'number') {
                field = `<input name="specifications[${attribute.id}][number_value]" value="${existing.number_value || ''}" ${locked ? 'readonly' : ''} type="number" step="0.0001" class="block h-11 w-full rounded-md border border-neutral-300 px-3 text-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">`;
            } else {
                field = `<input name="specifications[${attribute.id}][text_value]" value="${existing.text_value || ''}" ${locked ? 'readonly' : ''} class="block h-11 w-full rounded-md border border-neutral-300 px-3 text-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">`;
            }
            specsRoot.insertAdjacentHTML('beforeend', `<label class="block"><span class="mb-2 block text-sm font-medium text-neutral-800">${label}</span>${field}</label>`);
        });
    };

    optionsRoot.addEventListener('change', renderRows);
    rowsRoot.addEventListener('change', event => {
        if (event.target.name === 'default_variant_index') syncDefault();
    });
    form.querySelector('[data-apply-prices]')?.addEventListener('click', () => {
        const base = form.querySelector('[data-bulk-base]').value;
        const sale = form.querySelector('[data-bulk-sale]').value;
        rowsRoot.querySelectorAll('input[name$="[base_price]"]').forEach(input => { if (base) input.value = base; });
        rowsRoot.querySelectorAll('input[name$="[sale_price]"]').forEach(input => { if (sale) input.value = sale; });
    });
    form.querySelector('[data-generate-skus]')?.addEventListener('click', () => {
        const prefix = (form.querySelector('input[name="slug"]').value || form.querySelector('input[name="name"]').value || 'product').toUpperCase().replace(/[^A-Z0-9]+/g, '-').replace(/^-|-$/g, '');
        rowsRoot.querySelectorAll('tr').forEach((row, index) => {
            const sku = row.querySelector('input[name$="[sku]"]');
            if (sku && !sku.value) sku.value = `${prefix}-${index + 1}`;
        });
    });
    category?.addEventListener('change', () => {
        if (!firstRender && !confirm('Changing category reloads variant options and specifications. Continue?')) return;
        loadConfig();
    });
    type?.addEventListener('change', setTypeVisibility);
    loadConfig();
});
</script>
