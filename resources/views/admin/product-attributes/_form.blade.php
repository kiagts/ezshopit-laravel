<form method="POST" action="{{ $action }}" class="space-y-8">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <x-card>
        <h2 class="text-lg font-semibold text-neutral-950">Basic Information</h2>
        <div class="mt-6 grid gap-5 md:grid-cols-2">
            <x-input label="Name" name="name" :value="old('name', $attribute->name)" placeholder="Color" />
            <x-input label="Code" name="code" :value="old('code', $attribute->code)" placeholder="Leave blank to generate" />
        </div>
        <p class="mt-3 text-sm text-neutral-500">Use a stable lowercase code such as color, screen_size or battery_capacity. Changing the name will not change an existing code.</p>
    </x-card>

    <x-card>
        <h2 class="text-lg font-semibold text-neutral-950">Behavior</h2>
        <div class="mt-6 grid gap-5 md:grid-cols-2">
            <x-select label="Attribute Type" name="type">
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(old('type', $attribute->type?->value) === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </x-select>

            <x-select label="Input Type" name="input_type">
                @foreach ($inputTypes as $inputType)
                    <option value="{{ $inputType->value }}" @selected(old('input_type', $attribute->input_type?->value) === $inputType->value)>{{ $inputType->label() }}</option>
                @endforeach
            </x-select>
        </div>

        <div class="mt-5 grid gap-4 md:grid-cols-2">
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                <h3 class="text-sm font-semibold text-amber-950">Variant Attribute</h3>
                <p class="mt-1 text-sm leading-6 text-amber-900">Creates sellable product combinations such as Color, Size or Storage. Variant attributes must use select values.</p>
            </div>
            <div class="rounded-lg border border-brand-100 bg-brand-50 p-4">
                <h3 class="text-sm font-semibold text-brand-950">Specification Attribute</h3>
                <p class="mt-1 text-sm leading-6 text-brand-900">Stores descriptive product information such as Processor, Material or Screen Size.</p>
            </div>
        </div>
    </x-card>

    <x-card>
        <h2 class="text-lg font-semibold text-neutral-950">Marketplace Configuration</h2>
        <div class="mt-6 grid gap-5 md:grid-cols-2">
            <input type="hidden" name="is_filterable" value="0">
            <label class="inline-flex items-center gap-3 text-sm font-medium text-neutral-700">
                <input type="checkbox" name="is_filterable" value="1" @checked(old('is_filterable', $attribute->is_filterable)) class="h-4 w-4 rounded border-neutral-300 text-brand-700 focus:ring-brand-600">
                Filterable in future storefront
            </label>

            <input type="hidden" name="is_required" value="0">
            <label class="inline-flex items-center gap-3 text-sm font-medium text-neutral-700">
                <input type="checkbox" name="is_required" value="1" @checked(old('is_required', $attribute->is_required)) class="h-4 w-4 rounded border-neutral-300 text-brand-700 focus:ring-brand-600">
                Required in future product setup
            </label>

            <input type="hidden" name="is_active" value="0">
            <label class="inline-flex items-center gap-3 text-sm font-medium text-neutral-700">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $attribute->exists ? $attribute->is_active : true)) class="h-4 w-4 rounded border-neutral-300 text-brand-700 focus:ring-brand-600">
                Active attribute
            </label>

            <x-input label="Sort Order" name="sort_order" type="number" min="0" :value="old('sort_order', $attribute->sort_order ?? 0)" />
        </div>
        <p class="mt-3 text-sm text-neutral-500">Required is a future catalog rule, not a global requirement across every product.</p>
    </x-card>

    <div class="flex flex-col gap-3 border-t border-neutral-200 pt-6 sm:flex-row sm:justify-end">
        <x-button :href="route('admin.attributes.index')" variant="secondary">Cancel</x-button>
        <x-button type="submit">{{ $submitLabel }}</x-button>
    </div>
</form>
