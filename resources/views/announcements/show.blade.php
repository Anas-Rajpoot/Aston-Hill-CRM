@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto bg-white rounded-xl shadow p-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-xl font-semibold">{{ $row->title }}</h2>
            <p class="text-sm text-gray-500">
                Posted: {{ $row->created_at?->format('d-M-Y h:i A') }}
                • By: {{ $row->creator?->name }} ({{ $row->creator?->email }})
                @if($row->is_pinned) • 📌 Pinned @endif
                @if(!$row->is_active) • Inactive @endif
            </p>
        </div>

        <div class="flex gap-2">
            <a href="{{ route('announcements.index') }}" class="px-4 py-2 rounded-md bg-gray-100">Back</a>

            @can('announcements.edit')
            <a href="{{ route('announcements.edit', $row) }}" class="px-4 py-2 rounded-md bg-indigo-600 text-white">Edit</a>
            @endcan
        </div>
    </div>

    @if($row->body)
        <div class="prose max-w-none">
            {!! nl2br(e($row->body)) !!}
        </div>
    @else
        <p class="text-gray-500">—</p>
    @endif

    @if($row->attachment_path)
        <div class="mt-6 p-4 bg-gray-50 border rounded-lg">
            <p class="text-sm font-medium mb-2">Attachment</p>

            @if(str_starts_with((string)$row->attachment_mime, 'image/'))
                <img src="{{ $row->attachment_url }}" class="max-h-[480px] rounded border" />
            @else
                <a href="{{ $row->attachment_url }}" target="_blank"
                   class="text-indigo-600 hover:underline">
                    Download: {{ $row->attachment_name }}
                </a>
                <p class="text-xs text-gray-500 mt-1">
                    {{ $row->attachment_mime }} • {{ number_format(($row->attachment_size ?? 0)/1024, 2) }} KB
                </p>
            @endif
        </div>
    @endif

    @can('announcements.delete')
    <form method="POST" action="{{ route('announcements.destroy', $row) }}"
          onsubmit="return confirm('Delete this announcement?')" class="mt-6">
        @csrf
        @method('DELETE')
        <button class="bg-red-600 text-white px-4 py-2 rounded-md">Delete</button>
    </form>
    @endcan
</div>
@endsection
