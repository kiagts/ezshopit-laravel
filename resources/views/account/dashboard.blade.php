@extends('layouts.app')

@section('title', 'Account')

@section('content')
    <x-container class="py-10 sm:py-14">
        <div class="grid gap-8 lg:grid-cols-[18rem_1fr]">
            <x-account.nav active="account" />

            <section>
                <div class="mb-8">
                    <x-badge tone="brand">{{ $user->role->value }}</x-badge>
                    <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Hi, {{ $user->name }}</h1>
                    <p class="mt-2 text-sm leading-6 text-neutral-600">
                        This is the foundation for the customer account area.
                    </p>
                </div>

                <div class="grid gap-6 md:grid-cols-3">
                    <x-card>
                        <p class="text-sm font-medium text-neutral-500">Full name</p>
                        <p class="mt-2 text-lg font-semibold text-neutral-950">{{ $user->name }}</p>
                    </x-card>

                    <x-card>
                        <p class="text-sm font-medium text-neutral-500">Email</p>
                        <p class="mt-2 break-words text-lg font-semibold text-neutral-950">{{ $user->email }}</p>
                    </x-card>

                    <x-card>
                        <p class="text-sm font-medium text-neutral-500">Role</p>
                        <p class="mt-2 text-lg font-semibold capitalize text-neutral-950">{{ $user->role->value }}</p>
                    </x-card>
                </div>

                <div class="mt-8 rounded-lg border border-neutral-200 bg-white p-6 shadow-soft">
                    <h2 class="text-lg font-semibold text-neutral-950">Delivery Addresses</h2>
                    <p class="mt-2 text-sm leading-6 text-neutral-600">Manage saved delivery addresses for checkout.</p>
                    <x-button :href="route('account.addresses.index')" variant="secondary" class="mt-5">Manage Addresses</x-button>
                </div>

                <div class="mt-6 rounded-lg border border-neutral-200 bg-white p-6 shadow-soft">
                    <h2 class="text-lg font-semibold text-neutral-950">Orders</h2>
                    <p class="mt-2 text-sm leading-6 text-neutral-600">Review pending marketplace orders and seller groups.</p>
                    <x-button :href="route('account.orders.index')" variant="secondary" class="mt-5">View Orders</x-button>
                </div>
            </section>
        </div>
    </x-container>
@endsection
