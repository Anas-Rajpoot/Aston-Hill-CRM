@extends('layouts.app')

@section('content')
<div class="bg-white rounded-xl shadow p-6 max-w-xl">
    <h2 class="text-xl font-semibold mb-4">Create Role</h2>

    <form method="POST" action="{{ route('super-admin.roles.store') }}">
        @csrf

        <label class="block font-medium mb-1">Role Name</label>
        <input name="name" value="{{ old('name') }}"
               class="w-full border rounded-md px-3 py-2" placeholder="e.g. manager" required>

        @error('name')
            <p class="text-red-600 text-sm mt-2">{{ $message }}</p>
        @enderror

        <div class="mt-4 flex gap-2">
            <button class="bg-indigo-600 text-white px-4 py-2 rounded-md">Save</button>
            <a href="{{ route('super-admin.roles.index') }}" class="px-4 py-2 rounded-md bg-gray-100">Cancel</a>
        </div>
    </form>
</div>
@endsection
