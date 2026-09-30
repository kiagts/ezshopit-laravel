@extends('layouts.admin')

@section('title', 'Homepage')

@section('content')
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-neutral-950">Homepage merchandising</h1>
            <p class="mt-1 text-sm text-neutral-600">Manage the landing page sections, ordering, schedules, content, and curated marketplace entities.</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.homepage.preview') }}" target="_blank" class="inline-flex h-10 items-center rounded-md border border-neutral-300 bg-white px-4 text-sm font-semibold text-neutral-800 hover:bg-neutral-50">Preview</a>
            <a href="{{ route('admin.homepage.sections.create') }}" class="inline-flex h-10 items-center rounded-md bg-neutral-950 px-4 text-sm font-semibold text-white hover:bg-neutral-800">New section</a>
        </div>
    </div>

    <div class="mb-6 grid gap-3 sm:grid-cols-4">
        @foreach (['Active', 'Scheduled', 'Expired', 'Disabled'] as $status)
            <div class="rounded-lg border border-neutral-200 bg-white p-4">
                <p class="text-xs font-medium uppercase text-neutral-500">{{ $status }}</p>
                <p class="mt-2 text-2xl font-semibold text-neutral-950">{{ number_format((int) ($counts[$status] ?? 0)) }}</p>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('admin.homepage.sections.reorder') }}" class="rounded-lg border border-neutral-200 bg-white">
        @csrf
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Order</th>
                        <th class="px-4 py-3">Section</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Schedule</th>
                        <th class="px-4 py-3">Items</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @foreach ($sections as $section)
                        <tr>
                            <td class="px-4 py-4">
                                <input type="hidden" name="sections[{{ $loop->index }}][id]" value="{{ $section->id }}">
                                <input name="sections[{{ $loop->index }}][sort_order]" value="{{ $section->sort_order }}" class="w-20 rounded-md border border-neutral-300 px-2 py-1 text-sm">
                            </td>
                            <td class="px-4 py-4">
                                <p class="font-semibold text-neutral-950">{{ $section->name }}</p>
                                <p class="text-xs text-neutral-500">{{ $section->key }}</p>
                                @if ($section->title)
                                    <p class="mt-1 text-xs text-neutral-600">{{ $section->title }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-4"><x-badge tone="neutral">{{ $section->type->label() }}</x-badge></td>
                            <td class="px-4 py-4">{{ $section->derivedStatus() }}</td>
                            <td class="px-4 py-4 text-xs text-neutral-600">
                                <div>Starts: {{ $section->starts_at?->format('M j, Y g:i A') ?? 'Immediate' }}</div>
                                <div>Ends: {{ $section->ends_at?->format('M j, Y g:i A') ?? 'No end' }}</div>
                            </td>
                            <td class="px-4 py-4 text-neutral-600">{{ $section->items_count }} entity / {{ $section->content_items_count }} content</td>
                            <td class="px-4 py-4">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('admin.homepage.sections.edit', $section) }}" class="rounded-md border border-neutral-300 px-3 py-1.5 text-xs font-semibold text-neutral-800 hover:bg-neutral-50">Edit</a>
                                    <button form="toggle-section-{{ $section->id }}" class="rounded-md border border-neutral-300 px-3 py-1.5 text-xs font-semibold text-neutral-800 hover:bg-neutral-50">
                                        {{ $section->is_enabled ? 'Disable' : 'Enable' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex justify-between border-t border-neutral-200 px-4 py-3">
            <x-button type="submit" variant="secondary">Save order</x-button>
            {{ $sections->links() }}
        </div>
    </form>

    @foreach ($sections as $section)
        <form id="toggle-section-{{ $section->id }}" method="POST" action="{{ route('admin.homepage.sections.toggle', $section) }}" class="hidden">
            @csrf
        </form>
    @endforeach
@endsection
