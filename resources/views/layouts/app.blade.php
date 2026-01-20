<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Aston Hill') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen flex">

            {{-- Sidebar (fixed width) --}}
            <aside class="w-64 shrink-0">
                @include('layouts.sidebar')
            </aside>

            {{-- Right Side (Topbar + Content) --}}
            <div class="flex-1 flex flex-col min-w-0">

                {{-- Topbar --}}
                <header class="shrink-0">
                    @include('layouts.topbar')
                </header>

                {{-- Page Content --}}
                <main class="flex-1 p-4 sm:p-6 overflow-y-auto">
                    <div class="max-w-5xl mx-auto">
                        @yield('content')
                    </div>
                </main>

                {{-- Footer --}}
                <footer class="shrink-0">
                    @include('layouts.footer')
                </footer>

            </div>
        </div>
    </body>
</html>
