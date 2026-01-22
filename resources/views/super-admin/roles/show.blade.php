@extends('layouts.app')

@section('content')
<div class="bg-white rounded-xl shadow p-6 max-w-xl">
    <h2 class="text-xl font-semibold mb-4">Role Details</h2>

    <p><strong>Name:</strong> {{ $role->name }}</p>
    <p><strong>Guard:</strong> {{ $role->guard_name }}</p>

    <div class="mt-4 flex gap-2">
        <a href="{{ route('super-admin.roles.edit', $role) }}" class="bg-indigo-600 text-white px-4 py-2 rounded-md">Edit</a>
        <a href="{{ route('super-admin.roles.index') }}" class="px-4 py-2 rounded-md bg-gray-100">Back</a>
    </div>
</div>
@endsection
