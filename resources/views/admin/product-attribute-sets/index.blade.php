@extends('layouts.admin')

@section('title', 'Attribute Sets')

@section('content')
    <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <x-badge tone="brand">Catalog configuration</x-badge>
            <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Attribute Sets</h1>
            <p class="mt-2 text-sm leading-6 text-neutral-600">Create reusable product-type configurations that connect categories to the right variant and specification attributes.</p>
        </div>

        <x-button :href="route('admin.attribute-sets.create')">Create Attribute Set</x-button>
    </div>

    @error('attribute_set')
        <x-alert type="danger" class="mb-6">{{ $message }}</x-alert>
    @enderror

    <form method="GET" action="{{ route('admin.attribute-sets.index') }}" class="mb-6 grid gap-3 rounded-lg border border-neutral-200 bg-white p-4 shadow-sm sm:grid-cols-[1fr_12rem_auto]">
        <input
            type="search"
            name="q"
            value="{{ $filters['q'] }}"
            placeholder="Search name or code"
            class="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100"
        >

        <select name="status" class="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
            <option value="">All statuses</option>
            <option value="active" @selected($filters['status'] === 'active')>Active</option>
            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
        </select>

        <x-button type="submit" variant="secondary">Filter</x-button>
    </form>

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        @if ($attributeSets->isEmpty())
            <x-empty-state title="No attribute sets yet" description="Create reusable sets such as Smartphone, Laptop or Footwear to prepare category product forms.">
                <x-button :href="route('admin.attribute-sets.create')">Create Attribute Set</x-button>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase text-neutral-500">
                        <tr>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Code</th>
                            <th class="px-4 py-3">Attributes</th>
                            <th class="px-4 py-3">Variants</th>
                            <th class="px-4 py-3">Specifications</th>
                            <th class="px-4 py-3">Categories</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Sort</th>
                            <th class="px-4 py-3">Updated</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($attributeSets as $attributeSet)
                            <tr class="hover:bg-neutral-50">
                                <td class="px-4 py-4">
                                    <a href="{{ route('admin.attribute-sets.edit', $attributeSet) }}" class="font-semibold text-neutral-950 hover:text-brand-800">{{ $attributeSet->name }}</a>
                                    <p class="mt-1 max-w-md text-xs text-neutral-500">{{ str($attributeSet->description)->limit(90) }}</p>
                                </td>
                                <td class="px-4 py-4 text-neutral-700">{{ $attributeSet->code }}</td>
                                <td class="px-4 py-4 text-neutral-700">{{ $attributeSet->attributes_count }}</td>
                                <td class="px-4 py-4 text-neutral-700">{{ $attributeSet->variant_attributes_count }}</td>
                                <td class="px-4 py-4 text-neutral-700">{{ $attributeSet->specification_attributes_count }}</td>
                                <td class="px-4 py-4 text-neutral-700">{{ $attributeSet->categories_count }}</td>
                                <td class="px-4 py-4"><x-badge :tone="$attributeSet->is_active ? 'success' : 'neutral'">{{ $attributeSet->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                                <td class="px-4 py-4 text-neutral-700">{{ $attributeSet->sort_order }}</td>
                                <td class="px-4 py-4 text-neutral-700">{{ $attributeSet->updated_at->format('M j, Y') }}</td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-2">
                                        <x-button :href="route('admin.attribute-sets.edit', $attributeSet)" variant="secondary" size="sm">Edit</x-button>
                                        <form method="POST" action="{{ route('admin.attribute-sets.destroy', $attributeSet) }}">
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
        {{ $attributeSets->links() }}
    </div>
@endsection
