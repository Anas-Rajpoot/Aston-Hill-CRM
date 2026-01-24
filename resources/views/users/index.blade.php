@extends('layouts.app')

@section('content')
<div class="space-y-6">

    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Users</h1>
            <p class="text-sm text-gray-500">Manage users, statuses, and roles.</p>
        </div>

        <!-- <div class="flex items-center gap-2">
            <a href="{{ route('users.create') }}"
               class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                + Add User
            </a>
        </div> -->
    </div>

    {{-- Filters Card --}}
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('users.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3">

            {{-- Search --}}
            <div class="md:col-span-2">
                <label class="text-xs font-medium text-gray-600">Search</label>
                <input type="text" name="q" value="{{ request('q') }}"
                       placeholder="Search by name or email..."
                       class="mt-1 w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            {{-- Role --}}
            <div>
                <label class="text-xs font-medium text-gray-600">Role</label>
                <select name="role"
                        class="mt-1 w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Roles</option>
                    @foreach($roles ?? [] as $role)
                        <option value="{{ $role->name }}" {{ request('role') === $role->name ? 'selected' : '' }}>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Status --}}
            <div>
                <label class="text-xs font-medium text-gray-600">Status</label>
                <select name="status"
                        class="mt-1 w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Status</option>
                    <option value="pending"  {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>

            {{-- Buttons --}}
            <div class="md:col-span-4 flex items-center justify-end gap-2 pt-1">
                <a href="{{ route('users.index') }}"
                   class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Reset
                </a>
                <button type="submit"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Apply Filters
                </button>
            </div>
        </form>
    </div>

    {{-- Table Card --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">User</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Role</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Created</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600">Actions</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse($users as $user)
                        <tr class="hover:bg-gray-50">
                            {{-- User --}}
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900">{{ $user->name }}</div>
                                <div class="text-sm text-gray-500">{{ $user->email }}</div>
                            </td>

                            {{-- Role --}}
                            <td class="px-4 py-3 text-sm text-gray-700">
                                {{ $user->getRoleNames()->first() ?? '-' }}
                            </td>

                            {{-- Status badge --}}
                            <td class="px-4 py-3">
                                @php
                                    $status = $user->status ?? 'pending';
                                    $badge = match($status) {
                                        'approved' => 'bg-green-50 text-green-700 border-green-200',
                                        'rejected' => 'bg-red-50 text-red-700 border-red-200',
                                        default => 'bg-yellow-50 text-yellow-700 border-yellow-200',
                                    };
                                @endphp
                                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold {{ $badge }}">
                                    {{ ucfirst($status) }}
                                </span>
                            </td>

                            {{-- Created --}}
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ optional($user->created_at)->format('d M, Y') }}
                            </td>

                            {{-- Actions --}}
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('users.show', $user->id) }}"
                                       class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                                        View
                                    </a>

                                    <a href="{{ route('super-admin.users.review', $user->id) }}"
                                       class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700">
                                        Review
                                    </a>

                                    <a href="{{ route('users.edit', $user->id) }}"
                                       class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                                        Edit
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-500">
                                No users found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if(method_exists($users, 'links'))
            <div class="border-t border-gray-200 bg-white px-4 py-3">
                {{ $users->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
