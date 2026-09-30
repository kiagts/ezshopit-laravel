@extends('account.layout')

@section('title', 'Security')
@section('account_active', 'security')
@section('account_badge', 'Security')
@section('account_title', 'Password & Security')
@section('account_description', 'Change your password using your current password for confirmation.')

@section('account_content')
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <form method="POST" action="{{ route('account.security.password.update') }}" class="rounded-lg border border-neutral-200 bg-white p-6 shadow-soft">
            @csrf
            @method('PUT')

            <div class="grid gap-5">
                <x-input
                    label="Current Password"
                    name="current_password"
                    type="password"
                    autocomplete="current-password"
                    required
                />

                <x-input
                    label="New Password"
                    name="password"
                    type="password"
                    autocomplete="new-password"
                    required
                />

                <x-input
                    label="Confirm New Password"
                    name="password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                />
            </div>

            <div class="mt-6">
                <x-button type="submit">Update Password</x-button>
            </div>
        </form>

        <aside class="rounded-lg border border-neutral-200 bg-white p-6 shadow-soft">
            <h2 class="font-semibold text-neutral-950">Session Status</h2>
            <p class="mt-2 text-sm leading-6 text-neutral-600">Changing your password keeps your current session active so you can continue managing your account.</p>
            <form method="POST" action="{{ route('logout') }}" class="mt-5">
                @csrf
                <x-button type="submit" variant="secondary">Logout</x-button>
            </form>
        </aside>
    </div>
@endsection
