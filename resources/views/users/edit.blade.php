@extends('layouts.app')

@section('content')
<div class="bg-white rounded-xl shadow p-6">
    <h2 class="text-2xl font-semibold mb-4">Edit User: {{ $user->name }}</h2>

    <form method="POST" action="{{ route('users.update', $user) }}">
        @csrf
        @method('PUT')

        <div class="space-y-4">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                <input type="text" name="name" id="name" class="mt-1 block w-full" value="{{ old('name', $user->name) }}" required>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" name="email" id="email" class="mt-1 block w-full" value="{{ old('email', $user->email) }}" required>
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
                <input type="text" name="phone" id="phone" class="mt-1 block w-full" value="{{ old('phone', $user->phone) }}" required>
            </div>

            <div>
                <label for="country" class="block text-sm font-medium text-gray-700">Country</label>
                <input type="text" name="country" id="country" class="mt-1 block w-full" value="{{ old('country', $user->country) }}" required>
            </div>

            <div>
                <label for="cnic_number" class="block text-sm font-medium text-gray-700">CNIC Number</label>
                <input type="text" name="cnic_number" id="cnic_number" class="mt-1 block w-full" value="{{ old('cnic_number', $user->cnic_number) }}" required>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" name="password" id="password" class="mt-1 block w-full" placeholder="Leave empty if not changing">
            </div>

            <div>
                <label for="roles" class="block text-sm font-medium text-gray-700">Roles</label>
                <select name="roles[]" id="roles" multiple class="mt-1 block w-full">
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" 
                            {{ in_array($role->id, old('roles', $user->roles->pluck('id')->toArray())) ? 'selected' : '' }}>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <button type="submit" class="mt-4 bg-indigo-600 text-white px-4 py-2 rounded-md">Update User</button>
    </form>
</div>
@endsection
