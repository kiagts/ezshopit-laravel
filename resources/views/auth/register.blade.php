@extends('layouts.app')

@section('title', 'Create Account')

@section('content')
    <x-container size="lg" class="py-14 sm:py-20">
        <div class="mx-auto grid max-w-5xl overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-soft lg:grid-cols-[1fr_1.1fr]">
            <aside class="bg-neutral-950 px-8 py-10 text-white sm:px-10">
                <x-badge tone="brand">Customer account</x-badge>
                <h1 class="mt-6 text-3xl font-semibold tracking-normal">Create your customer account.</h1>
                <p class="mt-4 text-sm leading-6 text-neutral-300">
                    Public registration creates customer accounts only. Vendor onboarding will be added later.
                </p>
            </aside>

            <section class="px-6 py-8 sm:px-10">
                <div class="mb-8">
                    <h2 class="text-2xl font-semibold text-neutral-950">Get started</h2>
                    <p class="mt-2 text-sm text-neutral-500">Use your real details so your future account experience is ready.</p>
                </div>

                <form method="POST" action="{{ route('register') }}" class="space-y-5">
                    @csrf

                    <x-input
                        label="Full Name"
                        name="name"
                        :value="old('name')"
                        placeholder="John Customer"
                        autocomplete="name"
                        autofocus
                    />

                    <x-input
                        label="Email Address"
                        name="email"
                        type="email"
                        :value="old('email')"
                        placeholder="you@example.com"
                        autocomplete="email"
                    />

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div data-password-field>
                            <div class="relative">
                                <x-input
                                    label="Password"
                                    name="password"
                                    type="password"
                                    placeholder="Create a password"
                                    autocomplete="new-password"
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

                        <x-input
                            label="Confirm Password"
                            name="password_confirmation"
                            type="password"
                            placeholder="Repeat password"
                            autocomplete="new-password"
                        />
                    </div>

                    <x-button type="submit" class="w-full">Create Account</x-button>
                </form>

                <p class="mt-6 text-center text-sm text-neutral-500">
                    Already have an account?
                    <a href="{{ route('login') }}" class="font-medium text-brand-700 hover:text-brand-900">Sign in</a>
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
