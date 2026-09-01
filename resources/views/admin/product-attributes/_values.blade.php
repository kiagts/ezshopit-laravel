<x-card>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-neutral-950">Attribute Values</h2>
            <p class="mt-2 text-sm leading-6 text-neutral-600">Predefined options sellers will eventually choose from for select-based attributes.</p>
        </div>
        <x-badge :tone="$attribute->usesPredefinedValues() ? 'success' : 'neutral'">
            {{ $attribute->input_type->label() }}
        </x-badge>
    </div>

    @if (! $attribute->usesPredefinedValues())
        <div class="mt-6 rounded-lg border border-neutral-200 bg-neutral-50 p-5 text-sm text-neutral-600">
            This input type accepts product-specific values and does not use predefined options.
        </div>
    @else
        <form method="POST" action="{{ route('admin.attributes.values.store', $attribute) }}" class="mt-6 grid gap-3 rounded-lg border border-neutral-200 bg-neutral-50 p-4 lg:grid-cols-[1fr_1fr_8rem_10rem_8rem_auto]">
            @csrf
            <input name="value" value="{{ old('value') }}" placeholder="Black" class="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
            <input name="code" value="{{ old('code') }}" placeholder="Code auto-generates" class="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
            <input name="sort_order" value="{{ old('sort_order', 0) }}" type="number" min="0" class="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
            <input name="hex_color" value="{{ old('hex_color') }}" placeholder="#000000" class="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
            <label class="inline-flex h-10 items-center gap-2 text-sm font-medium text-neutral-700">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" checked class="h-4 w-4 rounded border-neutral-300 text-brand-700 focus:ring-brand-600">
                Active
            </label>
            <x-button type="submit">Add Value</x-button>
            @foreach (['value', 'code', 'sort_order', 'hex_color'] as $field)
                @error($field)<span class="text-sm text-red-700 lg:col-span-6">{{ $message }}</span>@enderror
            @endforeach
        </form>

        <div class="mt-6 overflow-hidden rounded-lg border border-neutral-200">
            @if ($attribute->values->isEmpty())
                <x-empty-state title="No values configured" description="Add the options sellers will be able to choose from." />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-200 text-sm">
                        <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase text-neutral-500">
                            <tr>
                                <th class="px-4 py-3">Value</th>
                                <th class="px-4 py-3">Code</th>
                                <th class="px-4 py-3">Sort</th>
                                <th class="px-4 py-3">Color</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @foreach ($attribute->values as $value)
                                @php($formId = 'attribute-value-form-'.$value->id)
                                <tr>
                                    <td class="px-4 py-4">
                                        <form id="{{ $formId }}" method="POST" action="{{ route('admin.attributes.values.update', [$attribute, $value]) }}">
                                            @csrf
                                            @method('PUT')
                                        </form>
                                        <input form="{{ $formId }}" name="value" value="{{ old("values.{$value->id}.value", $value->value) }}" class="h-10 w-40 rounded-md border border-neutral-300 bg-white px-3 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
                                    </td>
                                    <td class="px-4 py-4"><input form="{{ $formId }}" name="code" value="{{ old("values.{$value->id}.code", $value->code) }}" class="h-10 w-40 rounded-md border border-neutral-300 bg-white px-3 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100"></td>
                                    <td class="px-4 py-4"><input form="{{ $formId }}" name="sort_order" type="number" min="0" value="{{ old("values.{$value->id}.sort_order", $value->sort_order) }}" class="h-10 w-24 rounded-md border border-neutral-300 bg-white px-3 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100"></td>
                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-2">
                                            @if ($value->colorHex())
                                                <span class="h-5 w-5 rounded-full border border-neutral-300" style="background-color: {{ $value->colorHex() }}"></span>
                                            @endif
                                            <input form="{{ $formId }}" name="hex_color" value="{{ old("values.{$value->id}.hex_color", $value->colorHex()) }}" placeholder="#000000" class="h-10 w-32 rounded-md border border-neutral-300 bg-white px-3 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <input form="{{ $formId }}" type="hidden" name="is_active" value="0">
                                        <label class="inline-flex items-center gap-2 text-sm font-medium text-neutral-700">
                                            <input form="{{ $formId }}" type="checkbox" name="is_active" value="1" @checked($value->is_active) class="h-4 w-4 rounded border-neutral-300 text-brand-700 focus:ring-brand-600">
                                            {{ $value->is_active ? 'Active' : 'Inactive' }}
                                        </label>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-2">
                                            <x-button form="{{ $formId }}" type="submit" variant="secondary" size="sm">Save</x-button>
                                        </div>
                                        <form method="POST" action="{{ route('admin.attributes.values.destroy', [$attribute, $value]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <x-button type="submit" variant="danger" size="sm" class="mt-2">Delete</x-button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</x-card>
