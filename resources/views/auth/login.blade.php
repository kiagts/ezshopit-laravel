@extends('layouts.app')

@section('title', 'Sign In')

@section('content')
    <x-container size="lg" class="py-14 sm:py-20">
        <div class="mx-auto grid max-w-5xl overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-soft lg:grid-cols-[1fr_1.1fr]">
            <aside class="bg-neutral-950 px-8 py-10 text-white sm:px-10">
                <x-badge tone="brand">Secure access</x-badge>
                <h1 class="mt-6 text-3xl font-semibold tracking-normal">Sign in to your marketplace account.</h1>
                <p class="mt-4 text-sm leading-6 text-neutral-300">
                    One protected login opens the correct workspace for customers, vendors, and admins.
                </p>
            </aside>

            <section class="px-6 py-8 sm:px-10">
                <div class="mb-8">
                    <h2 class="text-2xl font-semibold text-neutral-950">Welcome back</h2>
                    <p class="mt-2 text-sm text-neutral-500">Enter your account details to continue.</p>
                </div>

                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf

                    <x-input
                        label="Email Address"
                        name="email"
                        type="email"
                        :value="old('email')"
                        placeholder="you@example.com"
                        autocomplete="email"
                        autofocus
                    />

                    <div data-password-field>
                        <div class="relative">
                            <x-input
                                label="Password"
                                name="password"
                                type="password"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                data-password-input
                            />
                            <button
                                type="button"
                                class="absolute right-3 top-9 text-sm font-medium text-brand-700 hover:text-brand-900"
                                data-password-toggle
                            >
                                Show
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <label class="inline-flex items-center gap-2 text-sm text-neutral-600">
                            <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-neutral-300 text-brand-700 focus:ring-brand-600">
                            Remember me
                        </label>
                    </div>

                    <x-button type="submit" class="w-full">Sign In</x-button>
                </form>

                <p class="mt-6 text-center text-sm text-neutral-500">
                    Don't have an account?
                    <a href="{{ route('register') }}" class="font-medium text-brand-700 hover:text-brand-900">Create one</a>
                </p>
            </section>
        </div>
    </x-container>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-password-field]').forEach((field) => {
            const input = field.querySelector('[data-password-input]');
            const toggle = field.querySelector('[data-password-toggle]');

            toggle?.addEventListener('click', () => {
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                toggle.textContent = isPassword ? 'Hide' : 'Show';
            });
        });
    </script>
@endpush
