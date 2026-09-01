@extends('layouts.admin')

@section('title', 'Commission Rules')

@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <x-badge tone="brand">Commission Rules</x-badge>
            <h1 class="mt-4 text-2xl font-semibold tracking-normal text-neutral-950">Marketplace Commission Rules</h1>
            <p class="mt-2 text-sm leading-6 text-neutral-600">Percentage rules used for future vendor-order commission recognition.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[20rem_minmax(0,1fr)] lg:items-start">
        <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
            <h2 class="font-semibold text-neutral-950">Global Default</h2>
            <div class="mt-4 grid gap-2 text-sm">
                <div class="flex justify-between gap-4"><span class="text-neutral-600">Rate</span><span class="font-medium">{{ $calculator->formatRate($globalRule->rate_basis_points) }}</span></div>
                <div class="flex justify-between gap-4"><span class="text-neutral-600">Shipping included</span><span class="font-medium">{{ $globalRule->include_shipping ? 'Yes' : 'No' }}</span></div>
            </div>
            <form method="POST" action="{{ route('admin.commission-rules.global.update') }}" class="mt-5 space-y-3">
                @csrf
                @method('PUT')
                <div>
                    <label for="global-rate" class="text-sm font-medium text-neutral-800">Rate</label>
                    <input id="global-rate" name="rate" value="{{ old('rate', rtrim(rtrim(number_format($globalRule->rate_basis_points / 100, 2, '.', ''), '0'), '.')) }}" class="mt-2 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm shadow-sm focus:border-neutral-950 focus:outline-none focus:ring-2 focus:ring-neutral-950/10" placeholder="10 or 12.25">
                </div>
                <label class="flex items-center gap-2 text-sm text-neutral-700">
                    <input type="checkbox" name="include_shipping" value="1" @checked(old('include_shipping', $globalRule->include_shipping)) class="rounded border-neutral-300 text-brand-700 focus:ring-brand-600">
                    Include shipping in future commissions
                </label>
                <x-button type="submit" size="sm">Update Global Rule</x-button>
            </form>
        </section>

        <div class="space-y-6">
            <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
                <h2 class="font-semibold text-neutral-950">Add Vendor Override</h2>
                <form method="POST" action="{{ route('admin.commission-rules.vendors.store') }}" class="mt-4 grid gap-3 md:grid-cols-[1fr_8rem_auto_auto] md:items-end">
                    @csrf
                    <div>
                        <label for="vendor-id" class="text-sm font-medium text-neutral-800">Vendor</label>
                        <select id="vendor-id" name="vendor_id" class="mt-2 h-11 w-full rounded-md border border-neutral-300 bg-white px-3 text-sm text-neutral-950 shadow-sm focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
                            <option value="">Choose vendor</option>
                            @foreach ($vendors as $vendor)
                                <option value="{{ $vendor->id }}">{{ $vendor->display_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="vendor-rate" class="text-sm font-medium text-neutral-800">Rate</label>
                        <input id="vendor-rate" name="rate" class="mt-2 h-11 w-full rounded-md border border-neutral-300 px-3 text-sm shadow-sm focus:border-neutral-950 focus:outline-none focus:ring-2 focus:ring-neutral-950/10" placeholder="8">
                    </div>
                    <label class="flex h-11 items-center gap-2 text-sm text-neutral-700">
                        <input type="checkbox" name="include_shipping" value="1" class="rounded border-neutral-300 text-brand-700 focus:ring-brand-600">
                        Shipping
                    </label>
                    <x-button type="submit">Save Override</x-button>
                </form>
            </section>

            <section class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-soft">
                <div class="flex flex-col gap-3 border-b border-neutral-100 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-semibold text-neutral-950">Vendor Overrides</h2>
                        <p class="mt-1 text-sm text-neutral-600">Removing an override returns future orders to the global default.</p>
                    </div>
                    <form method="GET" action="{{ route('admin.commission-rules.index') }}" class="flex gap-2">
                        <x-input name="q" :value="$filters['q']" placeholder="Search vendor" />
                        <x-button type="submit" variant="secondary">Search</x-button>
                    </form>
                </div>
                <div class="divide-y divide-neutral-100">
                    @forelse ($vendorRules as $rule)
                        <article class="grid gap-4 p-5 md:grid-cols-[1.2fr_1fr_1fr_auto] md:items-center">
                            <div>
                                <p class="font-semibold text-neutral-950">{{ $rule->vendor->display_name }}</p>
                                <p class="mt-1 text-sm text-neutral-500">{{ $rule->vendor->email }}</p>
                            </div>
                            <p class="text-sm font-medium text-neutral-950">{{ $calculator->formatRate($rule->rate_basis_points) }}</p>
                            <p class="text-sm text-neutral-600">Shipping {{ $rule->include_shipping ? 'included' : 'excluded' }}</p>
                            <form method="POST" action="{{ route('admin.commission-rules.vendors.destroy', $rule->vendor) }}" onsubmit="return confirm('Remove this vendor commission override?')">
                                @csrf
                                @method('DELETE')
                                <x-button type="submit" variant="secondary" size="sm">Remove</x-button>
                            </form>
                        </article>
                    @empty
                        <x-empty-state title="No vendor overrides" description="Vendors without overrides use the global default." class="m-5" />
                    @endforelse
                </div>
            </section>

            <div>{{ $vendorRules->links() }}</div>
        </div>
    </div>
@endsection
