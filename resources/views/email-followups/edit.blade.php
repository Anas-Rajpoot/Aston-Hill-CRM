@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto bg-white rounded-xl shadow p-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-semibold">Edit Email Follow Up</h2>
        <a href="{{ route('email-followups.show', $row) }}" class="text-sm text-gray-600 hover:underline">Back</a>
    </div>

    <form method="POST" action="{{ route('email-followups.update', $row) }}" class="space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label class="text-sm font-medium">Email Date</label>
            <input type="date" name="email_date" value="{{ old('email_date', optional($row->email_date)->toDateString()) }}"
                   class="w-full border-gray-300 rounded-md" required>
            @error('email_date') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="text-sm font-medium">Subject</label>
            <input type="text" name="subject" value="{{ old('subject', $row->subject) }}"
                   class="w-full border-gray-300 rounded-md" required>
            @error('subject') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="text-sm font-medium">Category</label>
            <input type="text" name="category" value="{{ old('category', $row->category) }}"
                   class="w-full border-gray-300 rounded-md" required>
            @error('category') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
                <label class="text-sm font-medium">Request From</label>
                <input type="text" name="request_from" value="{{ old('request_from', $row->request_from) }}"
                       class="w-full border-gray-300 rounded-md">
                @error('request_from') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-sm font-medium">Sent To</label>
                <input type="text" name="sent_to" value="{{ old('sent_to', $row->sent_to) }}"
                       class="w-full border-gray-300 rounded-md">
                @error('sent_to') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="text-sm font-medium">Comment</label>
            <textarea name="comment" rows="4" class="w-full border-gray-300 rounded-md">{{ old('comment', $row->comment) }}</textarea>
            @error('comment') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="flex gap-2">
            <button class="bg-indigo-600 text-white px-4 py-2 rounded-md">Update</button>
            <a href="{{ route('email-followups.show', $row) }}" class="px-4 py-2 rounded-md bg-gray-100">Cancel</a>
        </div>
    </form>
</div>
@endsection
