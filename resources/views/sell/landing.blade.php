@extends('layouts.app')

@section('title', 'Sell on Marketplace')

@section('content')
    <x-container class="py-14 sm:py-20">
        <div class="grid gap-10 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
            <div>
                <x-badge tone="brand">Seller onboarding</x-badge>
                <h1 class="mt-5 text-4xl font-semibold tracking-normal text-neutral-950 sm:text-5xl">Build your marketplace presence with a seller account.</h1>
                <p class="mt-5 max-w-2xl text-lg leading-8 text-neutral-600">
                    Create a vendor profile, submit your store application, and get ready for the seller tools that will power products, orders, and earnings in future modules.
                </p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <x-button :href="route('vendor.register')" size="lg">Become a Seller</x-button>
                    <x-button :href="route('login')" variant="secondary" size="lg">Sign In</x-button>
                </div>
            </div>

            <x-card padding="lg">
                <h2 class="text-lg font-semibold text-neutral-950">How selling will work</h2>
                <div class="mt-6 grid gap-5">
                    @foreach (['Create a secure seller account', 'Complete your store application', 'Wait for admin review', 'Access the seller dashboard after approval'] as $step)
                        <div class="flex gap-3">
                            <span class="mt-1 h-2.5 w-2.5 rounded-full bg-brand-600"></span>
                            <p class="text-sm leading-6 text-neutral-600">{{ $step }}</p>
                        </div>
                    @endforeach
                </div>
            </x-card>
        </div>

        <div class="mt-14 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach (['Reach More Customers', 'Manage Products Easily', 'Track Orders', 'Secure Payments', 'Seller Dashboard', 'Marketplace Support'] as $benefit)
                <x-card>
                    <h2 class="text-base font-semibold text-neutral-950">{{ $benefit }}</h2>
                    <p class="mt-3 text-sm leading-6 text-neutral-600">A foundation area for future seller capabilities, shown here without unfinished marketplace functionality.</p>
                </x-card>
            @endforeach
        </div>
    </x-container>
@endsection
