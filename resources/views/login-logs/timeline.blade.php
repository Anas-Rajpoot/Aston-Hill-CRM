@extends('layouts.app')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold">Activity Timeline</h1>
        <p class="text-sm text-gray-500">{{ $user->name }} ({{ $user->email }})</p>
    </div>
    <a class="px-4 py-2 rounded bg-gray-800 text-white text-sm" href="{{ route('login-logs.index') }}">
        Back
    </a>
</div>

<div class="bg-white rounded-lg shadow p-4">
    <div class="space-y-3">
        @foreach($logs as $log)
            <div class="border rounded p-3">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-medium">
                        Login: {{ $log->login_at?->format('Y-m-d H:i:s') }}
                        <span class="text-gray-500">|</span>
                        Logout: {{ $log->logout_at?->format('Y-m-d H:i:s') ?? '— (Online)' }}
                    </div>
                    <div class="text-xs px-2 py-1 rounded {{ $log->logout_at ? 'bg-gray-200 text-gray-800' : 'bg-green-100 text-green-700' }}">
                        {{ $log->logout_at ? 'Offline' : 'Online' }}
                    </div>
                </div>

                <div class="mt-2 text-xs text-gray-600">
                    IP: {{ $log->ip ?? '-' }} |
                    Country: {{ $log->country ?? '-' }} |
                    Suspicious: {{ $log->is_suspicious ? 'YES' : 'NO' }}
                    @if($log->suspicious_reason)
                        | Reason: {{ $log->suspicious_reason }}
                    @endif
                </div>

                <div class="mt-2 text-xs text-gray-700">
                    Active Seconds: {{ $log->active_seconds }}
                </div>

                @if(!$log->logout_at)
                    <div class="mt-3 flex gap-2">
                        <form method="POST" action="{{ route('login-logs.force-logout-log', $log->id) }}"
                              onsubmit="return confirm('Force logout this session?')">
                            @csrf
                            <button class="px-3 py-1 rounded bg-red-600 text-white text-xs">Force Logout (Session)</button>
                        </form>

                        <form method="POST" action="{{ route('login-logs.force-logout-user', $user->id) }}"
                              onsubmit="return confirm('Force logout user from all sessions?')">
                            @csrf
                            <button class="px-3 py-1 rounded bg-red-800 text-white text-xs">Force Logout (User)</button>
                        </form>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-4">
        {{ $logs->links() }}
    </div>
</div>
@endsection
