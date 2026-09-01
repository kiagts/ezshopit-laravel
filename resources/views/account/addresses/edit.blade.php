@extends('layouts.app')

@section('title', 'Edit Address')

@section('content')
    <x-container class="py-10 sm:py-14">
        <div class="grid gap-8 lg:grid-cols-[18rem_1fr]">
            <x-account.nav active="addresses" />

            <section>
                <div class="mb-8">
                    <x-badge tone="brand">Address Book</x-badge>
                    <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Edit Address</h1>
                    <p class="mt-2 text-sm leading-6 text-neutral-600">Update this private delivery address.</p>
                </div>

                <div class="rounded-lg border border-neutral-200 bg-white p-6 shadow-soft">
                    @include('account.addresses._form', [
                        'address' => $address,
                        'action' => route('account.addresses.update', $address),
                        'method' => 'PUT',
                        'countries' => $countries,
                        'submitLabel' => 'Update Address',
                    ])
                    <x-button :href="route('account.addresses.index')" variant="secondary" class="mt-4">Cancel</x-button>
                </div>
            </section>
        </div>
    </x-container>
@endsection
