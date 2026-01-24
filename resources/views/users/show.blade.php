@extends('layouts.app')

@section('content')
<div class="bg-white rounded-xl shadow p-6">
    <h2 class="text-2xl font-semibold mb-4">{{ $user->name }}</h2>

    <div class="space-y-4">
        <p><strong>Email:</strong> {{ $user->email }}</p>
        <p><strong>Phone:</strong> {{ $user->phone }}</p>
        <p><strong>Country:</strong> {{ $user->country }}</p>
        <p><strong>CNIC Number:</strong> {{ $user->cnic_number }}</p>
        <p><strong>Status:</strong> {{ ucfirst($user->status) }}</p>
    </div>

    @can('users.edit')
        <div class="mt-4">
            <a href="{{ route('users.edit', $user) }}" class="text-indigo-600">Edit User</a>
        </div>
    @endcan
</div>
@endsection
