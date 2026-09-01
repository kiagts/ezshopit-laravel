@extends('layouts.admin')

@section('title', 'Vendors')

@section('content')
    <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <x-badge tone="brand">Vendor review</x-badge>
            <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Vendors</h1>
            <p class="mt-2 text-sm leading-6 text-neutral-600">Review seller applications and manage marketplace access.</p>
        </div>

        <form method="GET" action="{{ route('admin.vendors.index') }}" class="flex flex-col gap-3 sm:flex-row">
            <input
                type="search"
                name="search"
                value="{{ $search }}"
                placeholder="Search vendors"
                class="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100"
            >
            <x-button type="submit" variant="secondary">Search</x-button>
        </form>
    </div>

    <div class="mb-6 flex flex-wrap gap-2">
        <a href="{{ route('admin.vendors.index', ['search' => $search]) }}" class="{{ blank($activeStatus) ? 'bg-brand-700 text-white' : 'bg-white text-neutral-700 ring-neutral-200' }} rounded-full px-3 py-1.5 text-sm font-medium ring-1 ring-inset">All</a>
        @foreach ($statuses as $status)
            <a href="{{ route('admin.vendors.index', ['status' => $status->value, 'search' => $search]) }}" class="{{ $activeStatus === $status->value ? 'bg-brand-700 text-white' : 'bg-white text-neutral-700 ring-neutral-200' }} rounded-full px-3 py-1.5 text-sm font-medium ring-1 ring-inset">{{ $status->label() }}</a>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        @if ($vendors->isEmpty())
            <x-empty-state title="No vendors found" description="Try changing the search or status filter." />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase text-neutral-500">
                        <tr>
                            <th class="px-4 py-3">Store</th>
                            <th class="px-4 py-3">Owner</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Phone</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Submitted</th>
                            <th class="px-4 py-3">Joined</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($vendors as $vendor)
                            <tr class="hover:bg-neutral-50">
                                <td class="px-4 py-4">
                                    <a href="{{ route('admin.vendors.show', $vendor) }}" class="font-semibold text-neutral-950 hover:text-brand-800">{{ $vendor->display_name }}</a>
                                    <p class="mt-1 text-xs text-neutral-500">{{ $vendor->business_name }}</p>
                                </td>
                                <td class="px-4 py-4 text-neutral-700">{{ $vendor->user->name }}</td>
                                <td class="px-4 py-4 text-neutral-700">{{ $vendor->email }}</td>
                                <td class="px-4 py-4 text-neutral-700">{{ $vendor->phone ?? 'Not provided' }}</td>
                                <td class="px-4 py-4"><x-badge :tone="$vendor->status->badgeTone()">{{ $vendor->status->label() }}</x-badge></td>
                                <td class="px-4 py-4 text-neutral-700">{{ $vendor->submitted_at?->format('M j, Y') ?? 'Not submitted' }}</td>
                                <td class="px-4 py-4 text-neutral-700">{{ $vendor->created_at->format('M j, Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="mt-6">
        {{ $vendors->links() }}
    </div>
@endsection
