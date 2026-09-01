@extends('layouts.admin')

@section('title', 'Categories')

@section('content')
    <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <x-badge tone="brand">Marketplace taxonomy</x-badge>
            <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Categories</h1>
            <p class="mt-2 text-sm leading-6 text-neutral-600">Organize the marketplace taxonomy for future products, navigation, filters, and SEO pages.</p>
        </div>

        <x-button :href="route('admin.categories.create')">Create Category</x-button>
    </div>

    @error('category')
        <x-alert type="danger" class="mb-6">{{ $message }}</x-alert>
    @enderror

    <form method="GET" action="{{ route('admin.categories.index') }}" class="mb-6 grid gap-3 rounded-lg border border-neutral-200 bg-white p-4 shadow-sm lg:grid-cols-[1fr_12rem_16rem_auto]">
        <input
            type="search"
            name="q"
            value="{{ $filters['q'] }}"
            placeholder="Search name or slug"
            class="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100"
        >

        <select name="status" class="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
            <option value="">All statuses</option>
            <option value="active" @selected($filters['status'] === 'active')>Active</option>
            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
        </select>

        <select name="parent_id" class="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
            <option value="">All parents</option>
            @foreach ($parentOptions as $option)
                <option value="{{ $option['id'] }}" @selected((string) $filters['parent_id'] === (string) $option['id'])>{{ $option['label'] }}</option>
            @endforeach
        </select>

        <x-button type="submit" variant="secondary">Filter</x-button>
    </form>

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        @if ($categories->isEmpty())
            <x-empty-state title="No categories yet" description="Create your first category to begin organizing marketplace products.">
                <x-button :href="route('admin.categories.create')">Create Category</x-button>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase text-neutral-500">
                        <tr>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Parent</th>
                            <th class="px-4 py-3">Attribute Set</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Sort</th>
                            <th class="px-4 py-3">Subcategories</th>
                            <th class="px-4 py-3">Updated</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($categories as $category)
                            <tr class="hover:bg-neutral-50">
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-3" style="padding-left: {{ ($depths[$category->id] ?? 0) * 1.5 }}rem">
                                        @if ($category->image_path)
                                            <img src="{{ Storage::url($category->image_path) }}" alt="{{ $category->name }}" class="h-10 w-10 rounded-md object-cover">
                                        @else
                                            <div class="flex h-10 w-10 items-center justify-center rounded-md bg-neutral-100 text-xs font-semibold text-neutral-500">
                                                {{ str($category->name)->substr(0, 2)->upper() }}
                                            </div>
                                        @endif
                                        <div>
                                            <a href="{{ route('admin.categories.edit', $category) }}" class="font-semibold text-neutral-950 hover:text-brand-800">{{ $category->name }}</a>
                                            <p class="mt-1 text-xs text-neutral-500">{{ $category->slug }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-neutral-700">{{ $category->parent?->name ?? 'Root' }}</td>
                                <td class="px-4 py-4 text-neutral-700">{{ $category->productAttributeSet?->name ?? '-' }}</td>
                                <td class="px-4 py-4">
                                    <x-badge :tone="$category->is_active ? 'success' : 'neutral'">{{ $category->is_active ? 'Active' : 'Inactive' }}</x-badge>
                                </td>
                                <td class="px-4 py-4 text-neutral-700">{{ $category->sort_order }}</td>
                                <td class="px-4 py-4 text-neutral-700">{{ $category->children_count }}</td>
                                <td class="px-4 py-4 text-neutral-700">{{ $category->updated_at->format('M j, Y') }}</td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-2">
                                        <x-button :href="route('admin.categories.edit', $category)" variant="secondary" size="sm">Edit</x-button>
                                        <form method="POST" action="{{ route('admin.categories.destroy', $category) }}">
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
        {{ $categories->links() }}
    </div>
@endsection
