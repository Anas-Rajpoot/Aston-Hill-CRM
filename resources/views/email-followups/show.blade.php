@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto bg-white rounded-xl shadow p-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-xl font-semibold">Email Follow Up Details</h2>
            <p class="text-sm text-gray-500">Created by: {{ $row->creator?->name }} ({{ $row->creator?->email }})</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('email-followups.index') }}" class="px-4 py-2 rounded-md bg-gray-100">Back</a>
            @can('emails_followup.edit')
            <a href="{{ route('email-followups.edit', $row) }}" class="px-4 py-2 rounded-md bg-indigo-600 text-white">Edit</a>
            @endcan
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="p-4 bg-gray-50 border rounded-lg">
            <p class="text-xs text-gray-500">Email Date</p>
            <p class="font-medium">{{ optional($row->email_date)->format('d-M-Y') }}</p>
        </div>

        <div class="p-4 bg-gray-50 border rounded-lg">
            <p class="text-xs text-gray-500">Category</p>
            <p class="font-medium">{{ $row->category }}</p>
        </div>

        <div class="p-4 bg-gray-50 border rounded-lg md:col-span-2">
            <p class="text-xs text-gray-500">Subject</p>
            <p class="font-medium">{{ $row->subject }}</p>
        </div>

        <div class="p-4 bg-gray-50 border rounded-lg">
            <p class="text-xs text-gray-500">Request From</p>
            <p class="font-medium">{{ $row->request_from ?: '—' }}</p>
        </div>

        <div class="p-4 bg-gray-50 border rounded-lg">
            <p class="text-xs text-gray-500">Sent To</p>
            <p class="font-medium">{{ $row->sent_to ?: '—' }}</p>
        </div>

        <div class="p-4 bg-gray-50 border rounded-lg md:col-span-2">
            <p class="text-xs text-gray-500">Comment</p>
            <p class="font-medium whitespace-pre-wrap">{{ $row->comment ?: '—' }}</p>
        </div>
    </div>

    @can('emails_followup.delete')
    <form method="POST" action="{{ route('email-followups.destroy', $row) }}"
          onsubmit="return confirm('Delete this record?')" class="mt-6">
        @csrf
        @method('DELETE')
        <button class="bg-red-600 text-white px-4 py-2 rounded-md">Delete</button>
    </form>
    @endcan
</div>
@endsection
