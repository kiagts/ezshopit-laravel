@extends('layouts.vendor')

@section('title', 'Store Profile')

@section('content')
    <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <x-badge :tone="$vendor->status->badgeTone()">{{ $vendor->status->label() }}</x-badge>
            <h1 class="mt-4 text-2xl font-semibold tracking-normal text-neutral-950">Store Profile</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-neutral-600">
                Manage the public identity customers see on your marketplace storefront. Store layout, theme, and checkout behavior remain marketplace-controlled.
            </p>
        </div>

        @if ($vendor->isApproved())
            <x-button :href="route('storefront.stores.show', $vendor)" variant="secondary">View Public Store</x-button>
        @endif
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <form method="POST" action="{{ route('vendor.store.update') }}" enctype="multipart/form-data" class="rounded-lg border border-neutral-200 bg-white p-6 shadow-soft">
            @csrf
            @method('PUT')

            <div class="grid gap-5">
                <x-input label="Store Name" name="display_name" value="{{ old('display_name', $vendor->display_name) }}" required />

                <x-textarea label="Store Description" name="description" maxlength="2000" required>{{ old('description', $vendor->description) }}</x-textarea>

                <div class="grid gap-5 md:grid-cols-2">
                    <x-input label="Public Email" name="email" type="email" value="{{ old('email', $vendor->email) }}" required />
                    <x-input label="Public Phone" name="phone" value="{{ old('phone', $vendor->phone) }}" required />
                </div>

                <x-input label="Website" name="website" type="url" value="{{ old('website', $vendor->website) }}" placeholder="https://example.com" />

                <div class="grid gap-5 md:grid-cols-2">
                    <label class="block">
                        <span class="mb-2 block text-sm font-medium text-neutral-800">Logo</span>
                        <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-950 shadow-sm file:mr-4 file:rounded-md file:border-0 file:bg-neutral-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-neutral-800 focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
                        @error('logo')<span class="mt-2 block text-sm text-red-700">{{ $message }}</span>@enderror
                    </label>

                    <label class="block">
                        <span class="mb-2 block text-sm font-medium text-neutral-800">Banner</span>
                        <input type="file" name="banner" accept="image/jpeg,image/png,image/webp" class="block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-950 shadow-sm file:mr-4 file:rounded-md file:border-0 file:bg-neutral-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-neutral-800 focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
                        @error('banner')<span class="mt-2 block text-sm text-red-700">{{ $message }}</span>@enderror
                    </label>
                </div>
            </div>

            <div class="mt-6">
                <x-button type="submit">Save Store Profile</x-button>
            </div>
        </form>

        <aside class="space-y-6">
            <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
                <h2 class="font-semibold text-neutral-950">Public Preview</h2>
                <div class="mt-4 overflow-hidden rounded-lg border border-neutral-200 bg-neutral-50">
                    @if ($vendor->banner_path)
                        <img src="{{ Storage::url($vendor->banner_path) }}" alt="{{ $vendor->display_name }} banner" class="h-28 w-full object-cover">
                    @else
                        <div class="h-28 bg-neutral-200"></div>
                    @endif
                    <div class="p-4">
                        @if ($vendor->logo_path)
                            <img src="{{ Storage::url($vendor->logo_path) }}" alt="{{ $vendor->display_name }} logo" class="-mt-10 h-14 w-14 rounded-md border-4 border-white bg-white object-cover">
                        @endif
                        <p class="mt-3 font-semibold text-neutral-950">{{ $vendor->display_name }}</p>
                        <p class="mt-2 line-clamp-4 text-sm leading-6 text-neutral-600">{{ $vendor->description }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-soft">
                <h2 class="font-semibold text-neutral-950">Marketplace Controls</h2>
                <div class="mt-4 grid gap-3 text-sm">
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Store URL</span><span class="break-all text-right font-medium text-neutral-950">/store/{{ $vendor->slug }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Status</span><span class="font-medium text-neutral-950">{{ $vendor->status->label() }}</span></div>
                    <div class="flex justify-between gap-4"><span class="text-neutral-600">Theme</span><span class="font-medium text-neutral-950">Marketplace controlled</span></div>
                </div>
            </section>
        </aside>
    </div>
@endsection
