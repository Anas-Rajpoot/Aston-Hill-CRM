<aside class="w-64 bg-white border-r hidden md:flex md:flex-col">
    <div class="h-16 flex items-center px-6 border-b">
        <span class="text-lg font-semibold text-gray-800">Super Admin</span>
    </div>

    <nav class="p-4 space-y-1">
        <a href="{{ route('super-admin.dashboard') }}"
           class="block px-3 py-2 rounded-md text-sm font-medium
           {{ request()->routeIs('super-admin.dashboard') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}">
            Dashboard
        </a>

        <a href="{{ route('super-admin.users.index') }}"
           class="block px-3 py-2 rounded-md text-sm font-medium
           {{ request()->routeIs('super-admin.users.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}">
            Users
        </a>

        <a href="{{ route('accounts.index') }}"
           class="block px-3 py-2 rounded-md text-sm font-medium
           {{ request()->routeIs('accounts.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}">
            Account
        </a>
    </nav>

    <div class="mt-auto p-4 border-t">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="w-full text-sm px-3 py-2 rounded-md bg-gray-900 text-white hover:bg-gray-800">
                Logout
            </button>
        </form>
    </div>
</aside>
