@extends('layouts.app')

@section('content')
<div class="bg-white rounded-xl shadow p-6 max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-semibold">Edit Note</h2>
            <p class="text-sm text-gray-500">Update your personal note or to-do.</p>
        </div>

        <a href="{{ route('personal-notes.show', $note) }}"
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

    <form method="POST" action="{{ route('personal-notes.update', $note) }}" class="space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm font-medium text-gray-700">Title</label>
            <input name="title" value="{{ old('title', $note->title) }}"
                   class="mt-1 w-full border-gray-300 rounded-md shadow-sm"
                   required>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Details (optional)</label>
            <textarea name="body" rows="6"
                      class="mt-1 w-full border-gray-300 rounded-md shadow-sm"
                      placeholder="Write details here...">{{ old('body', $note->body) }}</textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-sm font-medium text-gray-700">Status</label>
                <select name="status" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                    <option value="pending" {{ old('status', $note->status) === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="done" {{ old('status', $note->status) === 'done' ? 'selected' : '' }}>Done</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Priority</label>
                <select name="priority" class="mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                    <option value="low" {{ old('priority', $note->priority) === 'low' ? 'selected' : '' }}>Low</option>
                    <option value="medium" {{ old('priority', $note->priority) === 'medium' ? 'selected' : '' }}>Medium</option>
                    <option value="high" {{ old('priority', $note->priority) === 'high' ? 'selected' : '' }}>High</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Due Date</label>
                <input type="date" name="due_date"
                       value="{{ old('due_date', optional($note->due_date)->format('Y-m-d')) }}"
                       class="mt-1 w-full border-gray-300 rounded-md shadow-sm">
            </div>
        </div>

        @if($note->completed_at)
            <div class="rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">
                Completed at: <strong>{{ $note->completed_at->format('d-M-Y h:i A') }}</strong>
            </div>
        @endif

        <div class="flex items-center justify-end gap-2 pt-2">
            <a href="{{ route('personal-notes.show', $note) }}"
               class="px-4 py-2 rounded-md bg-gray-100 text-gray-700 text-sm hover:bg-gray-200">
                Cancel
            </a>

            <button type="submit"
                    class="px-4 py-2 rounded-md bg-indigo-600 text-white text-sm hover:bg-indigo-700">
                Update Note
            </button>
        </div>
    </form>
</div>
@endsection
