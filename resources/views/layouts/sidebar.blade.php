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

        @role('superadmin')
            <a href="{{ route('super-admin.users.index') }}"
                class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                {{ request()->routeIs('super-admin.users.*')
                    ? 'bg-indigo-50 text-indigo-700'
                    : 'text-gray-700 hover:bg-gray-50' }}">
                    👥 <span>Users</span>
            </a>
        @endrole

        @role('superadmin')
            <a href="{{ route('super-admin.roles.index') }}"
                class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                {{ request()->routeIs('super-admin.roles.*')
                    ? 'bg-indigo-50 text-indigo-700'
                    : 'text-gray-700 hover:bg-gray-50' }}">
                     <span>Roles</span>
            </a>
        @endrole

        @role('superadmin')
            <a href="{{ route('super-admin.permissions.index') }}"
                class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                {{ request()->routeIs('super-admin.permissions.*')
                    ? 'bg-indigo-50 text-indigo-700'
                    : 'text-gray-700 hover:bg-gray-50' }}">
                    <span>Permissions</span>
            </a>
        @endrole
        <a href="{{ route('accounts.index') }}"
           class="block px-3 py-2 rounded-md text-sm font-medium
           {{ request()->routeIs('accounts.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}">
            Account
        </a>

        <a href="{{ route('expenses.index') }}"
            class="block px-3 py-2 rounded-md text-sm font-medium
            {{ request()->routeIs('expenses.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}">
            Expenses
        </a>


        <a href="{{ route('login-logs.index') }}"
           class="block px-3 py-2 rounded-md text-sm font-medium
           {{ request()->routeIs('login-logs.*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}">
            Login Logs
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
