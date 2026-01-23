@extends('layouts.app')

@section('content')
<div class="bg-white rounded-xl shadow p-6 max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-semibold">Add Personal Note</h2>
            <p class="text-sm text-gray-500">Create a note or to-do for yourself.</p>
        </div>

        <a href="{{ route('personal-notes.index') }}"
           class="px-4 py-2 rounded-md bg-gray-100 text-gray-700 text-sm hover:bg-gray-200">
            Back
        </a>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('personal-notes.store') }}" class="space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700">Title</label>
            <input name="title" value="{{ old('title') }}"
                   class="mt-1 w-full border-gray-300 rounded-md shadow-sm"
                   placeholder="e.g. Pay electricity bill, Meeting notes..." required>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Details (optional)</label>
            <textarea name="body" rows="5"
                      class="mt-1 w-full border-gray-300 rounded-md shadow-sm"
                      placeholder="Write details here...">{{ old('body') }}</textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-sm font-medium text-gray-700">Priority</label>
                <select name="priority" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                    <option value="low" {{ old('priority') === 'low' ? 'selected' : '' }}>Low</option>
                    <option value="medium" {{ old('priority','medium') === 'medium' ? 'selected' : '' }}>Medium</option>
                    <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>High</option>
                </select>
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Due Date (optional)</label>
                <input type="date" name="due_date" value="{{ old('due_date') }}"
                       class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
                <p class="text-xs text-gray-500 mt-1">Leave blank if no due date.</p>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
            <a href="{{ route('personal-notes.index') }}"
               class="px-4 py-2 rounded-md bg-gray-100 text-gray-700 text-sm hover:bg-gray-200">
                Cancel
            </a>

            <button type="submit"
                    class="px-4 py-2 rounded-md bg-indigo-600 text-white text-sm hover:bg-indigo-700">
                Save Note
            </button>
        </div>
    </form>
</div>
@endsection
