@extends('layouts.app')

@section('content')
<div class="bg-white rounded-xl shadow p-6 max-w-4xl mx-auto">
    <div class="flex items-start justify-between gap-4 mb-5">
        <div>
            <h2 class="text-xl font-semibold">{{ $note->title }}</h2>
            <p class="text-sm text-gray-500">
                Created: {{ $note->created_at?->format('d-M-Y h:i A') }}
                @if($note->due_date)
                    • Due: {{ $note->due_date->format('d-M-Y') }}
                @endif
            </p>
        </div>

        <div class="flex gap-2 flex-wrap justify-end">
            <a href="{{ route('personal-notes.index') }}"
               class="px-4 py-2 rounded-md bg-gray-100 text-gray-700 text-sm hover:bg-gray-200">
                Back
            </a>

            @can('personal_notes.edit')
            <a href="{{ route('personal-notes.edit', $note) }}"
               class="px-4 py-2 rounded-md bg-indigo-600 text-white text-sm hover:bg-indigo-700">
                Edit
            </a>

            <form method="POST" action="{{ route('personal-notes.toggle', $note) }}">
                @csrf
                @method('PUT')
                <button class="px-4 py-2 rounded-md bg-emerald-600 text-white text-sm hover:bg-emerald-700">
                    {{ $note->status === 'done' ? 'Mark Pending' : 'Mark Done' }}
                </button>
            </form>
            @endcan

            @can('personal_notes.delete')
            <form method="POST" action="{{ route('personal-notes.destroy', $note) }}"
                  onsubmit="return confirm('Delete this note?')">
                @csrf
                @method('DELETE')
                <button class="px-4 py-2 rounded-md bg-red-600 text-white text-sm hover:bg-red-700">
                    Delete
                </button>
            </form>
            @endcan
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="border rounded-lg p-4">
            <p class="text-xs text-gray-500 mb-1">Status</p>
            <p class="text-sm font-semibold">
                {{ $note->status === 'done' ? 'Done' : 'Pending' }}
            </p>
            @if($note->completed_at)
                <p class="text-xs text-gray-500 mt-2">Completed:</p>
                <p class="text-sm">{{ $note->completed_at->format('d-M-Y h:i A') }}</p>
            @endif
        </div>

        <div class="border rounded-lg p-4">
            <p class="text-xs text-gray-500 mb-1">Priority</p>
            <p class="text-sm font-semibold">{{ ucfirst($note->priority) }}</p>
        </div>

        <div class="border rounded-lg p-4">
            <p class="text-xs text-gray-500 mb-1">Due Date</p>
            <p class="text-sm font-semibold">{{ $note->due_date?->format('d-M-Y') ?? '—' }}</p>
        </div>
    </div>

    <div class="border rounded-lg p-4 bg-gray-50">
        <p class="text-xs text-gray-500 mb-2">Details</p>
        @if($note->body)
            <div class="text-sm text-gray-800 whitespace-pre-line">{{ $note->body }}</div>
        @else
            <p class="text-sm text-gray-500">No details added.</p>
        @endif
    </div>
</div>
@endsection
