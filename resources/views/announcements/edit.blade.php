@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto bg-white rounded-xl shadow p-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-semibold">Edit Announcement</h2>
        <a href="{{ route('announcements.show', $row) }}" class="text-sm text-gray-600 hover:underline">Back</a>
    </div>

    <form method="POST" action="{{ route('announcements.update', $row) }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label class="text-sm font-medium">Title</label>
            <input name="title" value="{{ old('title', $row->title) }}" class="w-full border-gray-300 rounded-md" required>
            @error('title') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="text-sm font-medium">Text</label>
            <textarea name="body" rows="6" class="w-full border-gray-300 rounded-md">{{ old('body', $row->body) }}</textarea>
            @error('body') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
                <label class="text-sm font-medium">Replace Attachment</label>
                <input type="file" name="attachment" class="w-full border-gray-300 rounded-md">
                @error('attachment') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror

                @if($row->attachment_path)
                    <div class="mt-2 text-sm">
                        <p class="text-gray-500">Current: {{ $row->attachment_name }}</p>

                        @if(str_starts_with((string)$row->attachment_mime, 'image/'))
                            <img src="{{ $row->attachment_url }}" class="mt-2 max-h-40 rounded border" />
                        @else
                            <a href="{{ $row->attachment_url }}" target="_blank" class="text-indigo-600 hover:underline">
                                Download attachment
                            </a>
                        @endif

                        <label class="inline-flex items-center gap-2 mt-2">
                            <input type="checkbox" name="remove_attachment" value="1" class="rounded">
                            <span>Remove attachment</span>
                        </label>
                    </div>
                @endif
            </div>

            <div>
                <label class="text-sm font-medium">Publish At (optional)</label>
                <input type="datetime-local" name="published_at" class="w-full border-gray-300 rounded-md"
                       value="{{ old('published_at', $row->published_at ? $row->published_at->format('Y-m-d\TH:i') : '') }}">
                @error('published_at') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex items-center gap-4">
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="is_pinned" value="1" class="rounded" {{ $row->is_pinned ? 'checked' : '' }}>
                <span class="text-sm">Pinned</span>
            </label>

            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" class="rounded" {{ $row->is_active ? 'checked' : '' }}>
                <span class="text-sm">Active</span>
            </label>
        </div>

        <div class="flex gap-2">
            <button class="bg-indigo-600 text-white px-4 py-2 rounded-md">Update</button>
            <a href="{{ route('announcements.show', $row) }}" class="px-4 py-2 rounded-md bg-gray-100">Cancel</a>
        </div>
    </form>
</div>
@endsection
