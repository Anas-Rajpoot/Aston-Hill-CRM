@extends('layouts.app')

@section('content')
<div class="bg-white rounded-xl shadow p-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-semibold">Edit Expense</h2>
        <a href="{{ route('expenses.index') }}" class="text-sm text-gray-600 hover:underline">Back</a>
    </div>

    <form method="POST" action="{{ route('expenses.update', $expense) }}">
        @csrf
        @method('PUT')
        @include('expenses._form', ['expense' => $expense])

        <div class="mt-6 flex justify-end">
            <button class="bg-indigo-600 text-white px-4 py-2 rounded-md">Update</button>
        </div>
    </form>
</div>
@endsection
