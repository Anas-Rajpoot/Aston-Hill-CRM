@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto bg-white rounded-xl shadow p-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-semibold">Add Announcement</h2>
        <a href="{{ route('announcements.index') }}" class="text-sm text-gray-600 hover:underline">Back</a>
    </div>

    <form method="POST" action="{{ route('announcements.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf

        <div>
            <label class="text-sm font-medium">Title</label>
            <input name="title" value="{{ old('title') }}" class="w-full border-gray-300 rounded-md" required>
            @error('title') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="text-sm font-medium">Text</label>
            <textarea name="body" rows="6" class="w-full border-gray-300 rounded-md"
                placeholder="Explain new feature / how to use...">{{ old('body') }}</textarea>
            @error('body') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
                <label class="text-sm font-medium">Attachment (image/file)</label>
                <input type="file" name="attachment" class="w-full border-gray-300 rounded-md">
                <p class="text-xs text-gray-500 mt-1">Max 10MB</p>
                @error('attachment') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-sm font-medium">Publish At (optional)</label>
                <input type="datetime-local" name="published_at" class="w-full border-gray-300 rounded-md"
                       value="{{ old('published_at') }}">
                @error('published_at') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex items-center gap-4">
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="is_pinned" value="1" class="rounded">
                <span class="text-sm">Pinned</span>
            </label>

            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" class="rounded" checked>
                <span class="text-sm">Active</span>
            </label>
        </div>

        <div class="flex gap-2">
            <button class="bg-indigo-600 text-white px-4 py-2 rounded-md">Save</button>
            <a href="{{ route('announcements.index') }}" class="px-4 py-2 rounded-md bg-gray-100">Cancel</a>
        </div>
    </form>
</div>
@endsection
