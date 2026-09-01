@php
    $submittedAssignments = old('attributes', $assignments);
    $groupedAttributes = $attributes->groupBy(fn ($attribute) => $attribute->type->value);
@endphp

<form method="POST" action="{{ $action }}" class="space-y-8">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <x-card>
        <h2 class="text-lg font-semibold text-neutral-950">Basic Information</h2>
        <div class="mt-6 grid gap-5 md:grid-cols-2">
            <x-input label="Name" name="name" :value="old('name', $attributeSet->name)" placeholder="Smartphone" />
            <x-input label="Code" name="code" :value="old('code', $attributeSet->code)" placeholder="Leave blank to generate" />
            <div class="md:col-span-2">
                <x-textarea label="Description" name="description" placeholder="Describe when this set should be used">{{ old('description', $attributeSet->description) }}</x-textarea>
            </div>
        </div>
        <p class="mt-3 text-sm text-neutral-500">Codes are stable snake_case identifiers such as smartphone or fashion_clothing.</p>
    </x-card>

    <x-card>
        <h2 class="text-lg font-semibold text-neutral-950">Configuration</h2>
        <div class="mt-6 grid gap-5 md:grid-cols-2">
            <input type="hidden" name="is_active" value="0">
            <label class="inline-flex items-center gap-3 text-sm font-medium text-neutral-700">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $attributeSet->exists ? $attributeSet->is_active : true)) class="h-4 w-4 rounded border-neutral-300 text-brand-700 focus:ring-brand-600">
                Active attribute set
            </label>

            <x-input label="Sort Order" name="sort_order" type="number" min="0" :value="old('sort_order', $attributeSet->sort_order ?? 0)" />
        </div>
        <p class="mt-2 text-sm text-neutral-500">Inactive sets remain stored but are not offered for new category assignments.</p>
    </x-card>

    <x-card>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-neutral-950">Attributes</h2>
                <p class="mt-2 text-sm leading-6 text-neutral-600">Choose the variant options and specifications that belong to this product type. Required and order are contextual to this set.</p>
            </div>
            <x-badge tone="neutral">{{ $attributes->count() }} available</x-badge>
        </div>

        @error('attributes')
            <x-alert type="danger" class="mt-5">{{ $message }}</x-alert>
        @enderror

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            @foreach ([
                \App\Enums\ProductAttributeType::Variant->value => 'Variant Options',
                \App\Enums\ProductAttributeType::Specification->value => 'Specifications',
            ] as $type => $heading)
                <div class="rounded-lg border border-neutral-200">
                    <div class="border-b border-neutral-200 bg-neutral-50 px-4 py-3">
                        <h3 class="text-sm font-semibold text-neutral-950">{{ $heading }}</h3>
                    </div>
                    <div class="divide-y divide-neutral-100">
                        @forelse ($groupedAttributes->get($type, collect()) as $attribute)
                            @php
                                $row = $submittedAssignments[$attribute->id] ?? [];
                                $selected = filled($row['product_attribute_id'] ?? null);
                                $required = old("attributes.{$attribute->id}.is_required", $row['is_required'] ?? $attribute->is_required);
                                $sortOrder = old("attributes.{$attribute->id}.sort_order", $row['sort_order'] ?? $attribute->sort_order);
                            @endphp
                            <div class="grid gap-3 p-4 md:grid-cols-[1fr_8rem_7rem] md:items-center">
                                <label class="flex items-start gap-3">
                                    <input type="checkbox" name="attributes[{{ $attribute->id }}][product_attribute_id]" value="{{ $attribute->id }}" @checked($selected) class="mt-1 h-4 w-4 rounded border-neutral-300 text-brand-700 focus:ring-brand-600">
                                    <span>
                                        <span class="block font-medium text-neutral-950">{{ $attribute->name }}</span>
                                        <span class="mt-1 block text-xs text-neutral-500">{{ $attribute->code }} / {{ $attribute->input_type->label() }}{{ $attribute->is_active ? '' : ' / inactive' }}</span>
                                    </span>
                                </label>

                                <input type="hidden" name="attributes[{{ $attribute->id }}][is_required]" value="0">
                                <label class="inline-flex items-center gap-2 text-sm font-medium text-neutral-700">
                                    <input type="checkbox" name="attributes[{{ $attribute->id }}][is_required]" value="1" @checked($required) class="h-4 w-4 rounded border-neutral-300 text-brand-700 focus:ring-brand-600">
                                    Required
                                </label>

                                <input name="attributes[{{ $attribute->id }}][sort_order]" type="number" min="0" value="{{ $sortOrder }}" class="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
                            </div>
                        @empty
                            <div class="p-4 text-sm text-neutral-500">No {{ strtolower($heading) }} are available.</div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </x-card>

    <div class="flex flex-col gap-3 border-t border-neutral-200 pt-6 sm:flex-row sm:justify-end">
        <x-button :href="route('admin.attribute-sets.index')" variant="secondary">Cancel</x-button>
        <x-button type="submit">{{ $submitLabel }}</x-button>
    </div>
</form>
