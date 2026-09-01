@extends('layouts.app')

@section('title', 'Addresses')

@section('content')
    <x-container class="py-10 sm:py-14">
        <div class="grid gap-8 lg:grid-cols-[18rem_1fr]">
            <x-account.nav active="addresses" />

            <section class="space-y-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <x-badge tone="brand">Address Book</x-badge>
                        <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Your Addresses</h1>
                        <p class="mt-2 text-sm leading-6 text-neutral-600">Save delivery addresses for future checkout. You can keep up to {{ $maxAddresses }} addresses.</p>
                    </div>
                </div>

                <section class="rounded-lg border border-neutral-200 bg-white p-6 shadow-soft">
                    <h2 class="text-lg font-semibold text-neutral-950">Add Address</h2>
                    <div class="mt-5">
                        @include('account.addresses._form', [
                            'action' => route('account.addresses.store'),
                            'countries' => $countries,
                            'submitLabel' => 'Add Address',
                        ])
                    </div>
                </section>

                @if ($addresses->isEmpty())
                    <div class="rounded-lg border border-dashed border-neutral-300 bg-white p-10 text-center shadow-soft">
                        <h2 class="text-xl font-semibold text-neutral-950">No addresses yet.</h2>
                        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-neutral-600">Add your first delivery address above. It will become your default automatically.</p>
                    </div>
                @else
                    <div class="grid gap-4 md:grid-cols-2">
                        @foreach ($addresses as $address)
                            <x-account.address-card :address="$address">
                                <a href="{{ route('account.addresses.edit', $address) }}" class="font-medium text-neutral-700 hover:text-neutral-950">Edit</a>

                                @unless ($address->is_default)
                                    <form method="POST" action="{{ route('account.addresses.default', $address) }}">
                                        @csrf
                                        @method('PUT')
                                        <button type="submit" class="font-medium text-neutral-700 hover:text-neutral-950">Set as default</button>
                                    </form>
                                @endunless

                                <form method="POST" action="{{ route('account.addresses.destroy', $address) }}" onsubmit="return confirm('Delete this address?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="font-medium text-red-700 hover:text-red-800">Delete</button>
                                </form>
                            </x-account.address-card>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </x-container>
@endsection
