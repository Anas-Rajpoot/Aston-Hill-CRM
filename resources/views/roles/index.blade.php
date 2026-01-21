@extends('layouts.app')

@section('content')
<div class="bg-white rounded-xl shadow p-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-semibold">Roles</h2>
        <a href="{{ route('roles.create') }}"
           class="bg-indigo-600 text-white px-4 py-2 rounded-md">Add Role</a>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left bg-gray-50">
                <tr>
                    <th class="p-3">Name</th>
                    <th class="p-3">Guard</th>
                    <th class="p-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($roles as $role)
                    <tr class="border-t">
                        <td class="p-3">{{ $role->name }}</td>
                        <td class="p-3">{{ $role->guard_name }}</td>
                        <td class="p-3">
                            <div class="flex justify-end gap-2">
                                <a class="px-3 py-1 rounded bg-gray-100"
                                   href="{{ route('roles.show', $role) }}">View</a>

                                <a class="px-3 py-1 rounded bg-indigo-100 text-indigo-700"
                                   href="{{ route('roles.edit', $role) }}">Edit</a>

                                <form method="POST" action="{{ route('roles.destroy', $role) }}"
                                      onsubmit="return confirm('Delete this role?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="px-3 py-1 rounded bg-red-100 text-red-700">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td class="p-3" colspan="3">No roles found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $roles->links() }}
    </div>
</div>
@endsection
