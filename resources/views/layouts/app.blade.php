<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', config('marketplace.name')) | {{ config('marketplace.name') }}</title>
        <meta name="description" content="@yield('description', 'A modern multi-vendor marketplace platform.')">
        @stack('head')

        <!-- Favicon -->
        <link rel="icon" href="{{ asset('storage/frontstore/favicon.ico') }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ asset('storage/frontstore/favicon.ico') }}" type="image/x-icon">
        
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-neutral-50 font-sans text-neutral-950 antialiased">
        <div class="flex min-h-screen flex-col">
            <x-storefront.header :categories="$navigationCategories ?? collect()" />

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

            <x-storefront.footer />
        </div>

        @stack('modals')
        @stack('scripts')
    </body>
</html>
