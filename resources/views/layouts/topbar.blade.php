<header class="h-16 bg-white border-b flex items-center justify-between px-4 sm:px-6">
    <div>
        <h1 class="text-sm font-semibold text-gray-800">
            @yield('page-title', 'Super Admin Panel')
        </h1>
        <p class="text-xs text-gray-500">@yield('page-desc')</p>
    </div>

    <div class="text-sm text-gray-700">
        {{ auth()->user()->name ?? 'Admin' }}
    </div>
</header>
