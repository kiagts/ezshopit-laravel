@extends('account.layout')

@section('title', 'Profile')
@section('account_active', 'profile')
@section('account_badge', 'Profile')
@section('account_title', 'Profile Details')
@section('account_description', 'Update the name and email address used for your customer account.')

@section('account_content')
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <form method="POST" action="{{ route('account.profile.update') }}" class="rounded-lg border border-neutral-200 bg-white p-6 shadow-soft">
            @csrf
            @method('PUT')

            <div class="grid gap-5">
                <x-input
                    label="Full Name"
                    name="name"
                    value="{{ old('name', auth()->user()->name) }}"
                    placeholder="Your full name"
                    required
                />

                <x-input
                    label="Email Address"
                    name="email"
                    type="email"
                    value="{{ old('email', auth()->user()->email) }}"
                    placeholder="you@example.com"
                    required
                />
            </div>

            <div class="mt-6">
                <x-button type="submit">Save Profile</x-button>
            </div>
        </form>

        <aside class="rounded-lg border border-neutral-200 bg-white p-6 shadow-soft">
            <h2 class="font-semibold text-neutral-950">Account Role</h2>
            <p class="mt-2 text-sm leading-6 text-neutral-600">This area is reserved for customer accounts. Role changes are not managed from your profile.</p>
            <div class="mt-4 rounded-md bg-neutral-50 p-3 text-sm font-medium capitalize text-neutral-800">
                {{ str(auth()->user()->role->value)->title() }}
            </div>
        </aside>
    </div>
@endsection
