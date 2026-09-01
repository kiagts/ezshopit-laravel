<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', 'Checkout') | {{ config('marketplace.name') }}</title>
        <meta name="description" content="@yield('description', 'Checkout for your marketplace cart.')">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-neutral-50 font-sans text-neutral-950 antialiased">
        <div class="flex min-h-screen flex-col">
            <header class="border-b border-neutral-200 bg-white">
                <x-container class="flex h-16 items-center justify-between gap-4">
                    <a href="{{ route('home') }}" class="text-lg font-semibold tracking-normal text-neutral-950">{{ config('marketplace.name') }}</a>
                    <div class="flex items-center gap-3 text-sm text-neutral-600">
                        <span>Checkout</span>
                        <span class="hidden sm:inline">{{ auth()->user()->email }}</span>
                    </div>
                </x-container>
            </header>

            @if (session('status'))
                <x-container class="pt-6">
                    <x-alert type="success">{{ session('status') }}</x-alert>
                </x-container>
            @endif

            @if ($errors->any())
                <x-container class="pt-6">
                    <x-alert type="danger">
                        <p class="font-medium">Please review the highlighted fields.</p>
                    </x-alert>
                </x-container>
            @endif

            <main class="flex-1">
                @yield('content')
            </main>

            <footer class="border-t border-neutral-200 bg-white py-6">
                <x-container class="flex flex-col gap-2 text-sm text-neutral-500 sm:flex-row sm:items-center sm:justify-between">
                    <p>&copy; {{ date('Y') }} {{ config('marketplace.name') }}</p>
                    <a href="{{ route('cart.index') }}" class="font-medium text-neutral-700 hover:text-neutral-950">Return to cart</a>
                </x-container>
            </footer>
        </div>

        @stack('scripts')
    </body>
</html>
