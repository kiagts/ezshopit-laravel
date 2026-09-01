@extends('layouts.app')

@section('title', 'Seller Registration')

@section('content')
    <x-container size="lg" class="py-14 sm:py-20">
        <div class="mx-auto grid max-w-5xl overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-soft lg:grid-cols-[1fr_1.1fr]">
            <aside class="bg-neutral-950 px-8 py-10 text-white sm:px-10">
                <x-badge tone="brand">Vendor account</x-badge>
                <h1 class="mt-6 text-3xl font-semibold tracking-normal">Start your seller application.</h1>
                <p class="mt-4 text-sm leading-6 text-neutral-300">This creates a vendor-role user and a draft store profile. Admin approval comes after onboarding.</p>
            </aside>

            <section class="px-6 py-8 sm:px-10">
                <h2 class="text-2xl font-semibold text-neutral-950">Seller registration</h2>
                <p class="mt-2 text-sm text-neutral-500">Use a dedicated seller email address for this MVP.</p>

                <form method="POST" action="{{ route('vendor.register') }}" class="mt-8 space-y-5">
                    @csrf

                    <x-input label="Full Name" name="name" :value="old('name')" placeholder="Ayesha Merchant" autocomplete="name" />
                    <x-input label="Email" name="email" type="email" :value="old('email')" placeholder="seller@example.com" autocomplete="email" />
                    <x-input label="Phone" name="phone" :value="old('phone')" placeholder="+92 300 0000000" autocomplete="tel" />
                    <x-input label="Business Name" name="business_name" :value="old('business_name')" placeholder="Optional at this step" />

                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-input label="Password" name="password" type="password" placeholder="Create a password" autocomplete="new-password" />
                        <x-input label="Confirm Password" name="password_confirmation" type="password" placeholder="Repeat password" autocomplete="new-password" />
                    </div>

                    <x-button type="submit" class="w-full">Create Seller Account</x-button>
                </form>
            </section>
        </div>
    </x-container>
@endsection
