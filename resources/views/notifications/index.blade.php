@extends('layouts.app')

@section('content')
<div class="bg-white rounded-xl shadow p-6">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-semibold">Notifications</h2>
            <p class="text-sm text-gray-500">All announcements and reminders.</p>
        </div>

        <form method="POST" action="{{ route('notifications.readAll') }}">
            @csrf
            <button class="px-4 py-2 rounded bg-gray-900 text-white text-sm">
                Mark all read
            </button>
        </form>
    </div>

    {{-- Filters --}}
    <form class="bg-gray-50 border rounded-lg p-4 mb-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
                <label class="text-xs font-medium text-gray-600">Type</label>
                <select name="kind" class="w-full border-gray-300 rounded-md">
                    <option value="">All</option>
                    <option value="announcement" @selected(request('kind')==='announcement')>Announcements</option>
                    <option value="email_followup" @selected(request('kind')==='email_followup')>Email Follow Ups</option>
                    <option value="personal_note" @selected(request('kind')==='personal_note')>Personal Notes</option>
                </select>
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600">Status</label>
                <select name="status" class="w-full border-gray-300 rounded-md">
                    <option value="">All</option>
                    <option value="unread" @selected(request('status')==='unread')>Unread</option>
                    <option value="read" @selected(request('status')==='read')>Read</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button class="px-4 py-2 rounded bg-gray-800 text-white text-sm">Apply</button>
                <a href="{{ route('notifications.index') }}" class="px-4 py-2 rounded bg-gray-200 text-gray-900 text-sm">
                    Reset
                </a>
            </div>
        </div>
    </form>

    <div class="divide-y border rounded-xl overflow-hidden">
        @forelse($notifications as $n)
            @php
                $data = $n->data ?? [];
                $title = $data['title'] ?? 'Notification';
                $message = $data['message'] ?? '';
                $url = $data['url'] ?? null;
            @endphp

            <div class="p-4 flex items-start justify-between gap-4 {{ is_null($n->read_at) ? 'bg-indigo-50/40' : 'bg-white' }}">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <p class="font-medium text-gray-800">{{ $title }}</p>
                        @if(is_null($n->read_at))
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700">Unread</span>
                        @endif
                    </div>

                    @if($message)
                        <p class="text-sm text-gray-600 mt-1">{{ $message }}</p>
                    @endif

                    <p class="text-xs text-gray-400 mt-2">{{ $n->created_at->format('d-M-Y h:i A') }}</p>

                    @if($url)
                        <a href="{{ $url }}" class="text-sm text-indigo-600 hover:underline mt-2 inline-block">Open</a>
                    @endif
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    @if(is_null($n->read_at))
                        <form method="POST" action="{{ route('notifications.read', $n->id) }}">
                            @csrf
                            <button class="px-3 py-1 rounded bg-gray-900 text-white text-xs">Mark read</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('notifications.unread', $n->id) }}">
                            @csrf
                            <button class="px-3 py-1 rounded bg-gray-200 text-gray-900 text-xs">Mark unread</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="p-6 text-sm text-gray-500">No notifications found.</div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $notifications->links() }}
    </div>
</div>
@endsection
