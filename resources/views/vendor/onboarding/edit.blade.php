@extends('layouts.vendor')

@section('title', 'Store Application')

@section('content')
    <div class="mb-8">
        <x-badge :tone="$vendor->status->badgeTone()">{{ $vendor->status->label() }}</x-badge>
        <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Store application</h1>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-neutral-600">
            Complete the details admins need to review your seller account. You can save a draft before submitting.
        </p>
    </div>

    @if ($vendor->status === \App\Enums\VendorStatus::Rejected && $vendor->rejection_reason)
        <x-alert type="danger" class="mb-6">
            <p class="font-medium">Application needs changes</p>
            <p class="mt-1">{{ $vendor->rejection_reason }}</p>
        </x-alert>
    @endif

    <form method="POST" action="{{ route('vendor.onboarding.update') }}" enctype="multipart/form-data" class="space-y-8">
        @csrf
        @method('PATCH')

        <x-card>
            <h2 class="text-lg font-semibold text-neutral-950">Store Information</h2>
            <div class="mt-6 grid gap-5 md:grid-cols-2">
                <x-input label="Business Name" name="business_name" :value="old('business_name', $vendor->business_name)" placeholder="Registered business name" />
                <x-input label="Store Display Name" name="display_name" :value="old('display_name', $vendor->display_name)" placeholder="Marketplace store name" />
                <x-input label="Store Slug" name="slug" :value="old('slug', $vendor->slug)" placeholder="tech-verse" />
                <x-select label="Business Type" name="business_type">
                    <option value="">Select type</option>
                    @foreach (\App\Enums\VendorBusinessType::cases() as $type)
                        <option value="{{ $type->value }}" @selected(old('business_type', $vendor->business_type?->value) === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </x-select>
                <div class="md:col-span-2">
                    <x-textarea label="Store Description" name="description" placeholder="What does your store sell?">{{ old('description', $vendor->description) }}</x-textarea>
                </div>
            </div>
        </x-card>

        <x-card>
            <h2 class="text-lg font-semibold text-neutral-950">Contact Information</h2>
            <div class="mt-6 grid gap-5 md:grid-cols-3">
                <x-input label="Business Email" name="email" type="email" :value="old('email', $vendor->email)" placeholder="store@example.com" />
                <x-input label="Phone" name="phone" :value="old('phone', $vendor->phone)" placeholder="+92 300 0000000" />
                <x-input label="Website" name="website" :value="old('website', $vendor->website)" placeholder="https://example.com" />
            </div>
        </x-card>

        <x-card>
            <h2 class="text-lg font-semibold text-neutral-950">Address</h2>
            <div class="mt-6 grid gap-5 md:grid-cols-2">
                <x-input label="Country" name="country" :value="old('country', $vendor->country)" placeholder="Pakistan" />
                <x-input label="City" name="city" :value="old('city', $vendor->city)" placeholder="Karachi" />
                <x-input label="Address Line 1" name="address_line_1" :value="old('address_line_1', $vendor->address_line_1)" placeholder="Street address" />
                <x-input label="Address Line 2" name="address_line_2" :value="old('address_line_2', $vendor->address_line_2)" placeholder="Suite, building, area" />
                <x-input label="Postal Code" name="postal_code" :value="old('postal_code', $vendor->postal_code)" placeholder="Optional" />
            </div>
        </x-card>

        <x-card>
            <h2 class="text-lg font-semibold text-neutral-950">Business Information</h2>
            <div class="mt-6 grid gap-5 md:grid-cols-2">
                <x-input label="Tax Number" name="tax_number" :value="old('tax_number', $vendor->tax_number)" placeholder="Optional" />
                <x-input label="Registration Number" name="registration_number" :value="old('registration_number', $vendor->registration_number)" placeholder="Optional" />
            </div>
        </x-card>

        <x-card>
            <h2 class="text-lg font-semibold text-neutral-950">Branding</h2>
            <div class="mt-6 grid gap-5 md:grid-cols-2">
                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-neutral-800">Store Logo</span>
                    @if ($vendor->logo_path)
                        <img src="{{ Storage::url($vendor->logo_path) }}" alt="{{ $vendor->display_name }} logo" class="mb-3 h-16 w-16 rounded-md object-cover">
                    @endif
                    <input type="file" name="logo" accept="image/*" class="block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm file:mr-4 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-800 focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
                    @error('logo')<span class="mt-2 block text-sm text-red-700">{{ $message }}</span>@enderror
                </label>

                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-neutral-800">Store Banner</span>
                    @if ($vendor->banner_path)
                        <img src="{{ Storage::url($vendor->banner_path) }}" alt="{{ $vendor->display_name }} banner" class="mb-3 h-20 w-full rounded-md object-cover">
                    @endif
                    <input type="file" name="banner" accept="image/*" class="block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm shadow-sm file:mr-4 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-800 focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-100">
                    <span class="mt-2 block text-xs text-neutral-500">Recommended banner: 1600 x 400.</span>
                    @error('banner')<span class="mt-2 block text-sm text-red-700">{{ $message }}</span>@enderror
                </label>
            </div>
        </x-card>

        <div class="flex flex-col gap-3 border-t border-neutral-200 pt-6 sm:flex-row sm:justify-end">
            <x-button type="submit" variant="secondary">Save Draft</x-button>
            <x-button type="submit" formaction="{{ route('vendor.onboarding.submit') }}" formmethod="POST" name="_method" value="POST">Submit Application</x-button>
        </div>
    </form>
@endsection
