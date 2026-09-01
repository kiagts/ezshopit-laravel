@extends('layouts.admin')

@section('title', 'Product Attributes')

@section('content')
    <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <x-badge tone="brand">Catalog attributes</x-badge>
            <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Product Attributes</h1>
            <p class="mt-2 text-sm leading-6 text-neutral-600">Define marketplace-owned options such as Color and Size, plus product specifications such as Processor or Material.</p>
        </div>

        <x-button :href="route('admin.attributes.create')">Create Attribute</x-button>
    </div>

    <form method="GET" action="{{ route('admin.attributes.index') }}" class="mb-6 grid gap-3 rounded-lg border border-neutral-200 bg-white p-4 shadow-sm lg:grid-cols-[1fr_11rem_12rem_11rem_auto]">
        <input
            type="search"
            name="q"
            value="{{ $filters['q'] }}"
            placeholder="Search name or code"
            class="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100"
        >

        <select name="type" class="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
            <option value="">All types</option>
            @foreach ($types as $type)
                <option value="{{ $type->value }}" @selected($filters['type'] === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>

        <select name="input_type" class="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
            <option value="">All inputs</option>
            @foreach ($inputTypes as $inputType)
                <option value="{{ $inputType->value }}" @selected($filters['input_type'] === $inputType->value)>{{ $inputType->label() }}</option>
            @endforeach
        </select>

        <select name="status" class="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
            <option value="">All statuses</option>
            <option value="active" @selected($filters['status'] === 'active')>Active</option>
            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
        </select>

        <x-button type="submit" variant="secondary">Filter</x-button>
    </form>

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        @if ($attributes->isEmpty())
            <x-empty-state title="No product attributes yet" description="Create attributes such as Color, Size and Storage to prepare the marketplace catalog.">
                <x-button :href="route('admin.attributes.create')">Create Attribute</x-button>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase text-neutral-500">
                        <tr>
                            <th class="px-4 py-3">Attribute</th>
                            <th class="px-4 py-3">Code</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Input</th>
                            <th class="px-4 py-3">Values</th>
                            <th class="px-4 py-3">Filterable</th>
                            <th class="px-4 py-3">Required</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Sort</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($attributes as $attribute)
                            <tr class="hover:bg-neutral-50">
                                <td class="px-4 py-4">
                                    <a href="{{ route('admin.attributes.edit', $attribute) }}" class="font-semibold text-neutral-950 hover:text-brand-800">{{ $attribute->name }}</a>
                                </td>
                                <td class="px-4 py-4 text-neutral-700">{{ $attribute->code }}</td>
                                <td class="px-4 py-4">
                                    <x-badge :tone="$attribute->isVariant() ? 'warning' : 'brand'">{{ $attribute->type->label() }}</x-badge>
                                </td>
                                <td class="px-4 py-4 text-neutral-700">{{ $attribute->input_type->label() }}</td>
                                <td class="px-4 py-4 text-neutral-700">{{ $attribute->values_count }}</td>
                                <td class="px-4 py-4"><x-badge :tone="$attribute->is_filterable ? 'success' : 'neutral'">{{ $attribute->is_filterable ? 'Yes' : 'No' }}</x-badge></td>
                                <td class="px-4 py-4"><x-badge :tone="$attribute->is_required ? 'warning' : 'neutral'">{{ $attribute->is_required ? 'Yes' : 'No' }}</x-badge></td>
                                <td class="px-4 py-4"><x-badge :tone="$attribute->is_active ? 'success' : 'neutral'">{{ $attribute->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                                <td class="px-4 py-4 text-neutral-700">{{ $attribute->sort_order }}</td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-2">
                                        <x-button :href="route('admin.attributes.edit', $attribute)" variant="secondary" size="sm">Edit</x-button>
                                        <form method="POST" action="{{ route('admin.attributes.destroy', $attribute) }}">
                                            @csrf
                                            @method('DELETE')
                                            <x-button type="submit" variant="danger" size="sm">Delete</x-button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="mt-6">
        {{ $attributes->links() }}
    </div>
@endsection
