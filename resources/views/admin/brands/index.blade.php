@extends('layouts.admin')

@section('title', 'Brands')

@section('content')
    <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <x-badge tone="brand">Marketplace brands</x-badge>
            <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Brands</h1>
            <p class="mt-2 text-sm leading-6 text-neutral-600">Manage global brands for future product assignment, filtering, search, and brand landing pages.</p>
        </div>

        <x-button :href="route('admin.brands.create')">Create Brand</x-button>
    </div>

    <form method="GET" action="{{ route('admin.brands.index') }}" class="mb-6 grid gap-3 rounded-lg border border-neutral-200 bg-white p-4 shadow-sm sm:grid-cols-[1fr_12rem_auto]">
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

        <x-button type="submit" variant="secondary">Filter</x-button>
    </form>

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        @if ($brands->isEmpty())
            <x-empty-state title="No brands yet" description="Create marketplace brands so products can be organized and filtered consistently.">
                <x-button :href="route('admin.brands.create')">Create Brand</x-button>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase text-neutral-500">
                        <tr>
                            <th class="px-4 py-3">Logo</th>
                            <th class="px-4 py-3">Brand</th>
                            <th class="px-4 py-3">Slug</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Sort</th>
                            <th class="px-4 py-3">Updated</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($brands as $brand)
                            <tr class="hover:bg-neutral-50">
                                <td class="px-4 py-4">
                                    @if ($brand->logo_path)
                                        <img src="{{ Storage::url($brand->logo_path) }}" alt="{{ $brand->name }} logo" class="h-10 w-10 rounded-md object-contain">
                                    @else
                                        <div class="flex h-10 w-10 items-center justify-center rounded-md bg-neutral-100 text-xs font-semibold text-neutral-500">
                                            {{ str($brand->name)->substr(0, 2)->upper() }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <a href="{{ route('admin.brands.edit', $brand) }}" class="font-semibold text-neutral-950 hover:text-brand-800">{{ $brand->name }}</a>
                                    <p class="mt-1 max-w-md text-xs text-neutral-500">{{ str($brand->description)->limit(90) }}</p>
                                </td>
                                <td class="px-4 py-4 text-neutral-700">{{ $brand->slug }}</td>
                                <td class="px-4 py-4">
                                    <x-badge :tone="$brand->is_active ? 'success' : 'neutral'">{{ $brand->is_active ? 'Active' : 'Inactive' }}</x-badge>
                                </td>
                                <td class="px-4 py-4 text-neutral-700">{{ $brand->sort_order }}</td>
                                <td class="px-4 py-4 text-neutral-700">{{ $brand->updated_at->format('M j, Y') }}</td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-2">
                                        <x-button :href="route('admin.brands.edit', $brand)" variant="secondary" size="sm">Edit</x-button>
                                        <form method="POST" action="{{ route('admin.brands.destroy', $brand) }}">
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
        {{ $brands->links() }}
    </div>
@endsection
