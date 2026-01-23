@php
  $tabId = request()->query('__tab') ?? request()->cookie('__tab');
  $trail = $tabId ? session("breadcrumbs_trail.$tabId", []) : [];
@endphp

@if(count($trail))
  <nav class="mb-3 text-sm text-gray-600">
    <ol class="flex flex-wrap items-center gap-2">
      @foreach($trail as $item)
        @if(!$loop->first)
          <span class="text-gray-400">/</span>
        @endif

        @if(!$loop->last && !empty($item['url']))
          <a href="{{ $item['url'] }}" class="text-indigo-600 hover:underline">
            {{ $item['label'] ?? '...' }}
          </a>
        @else
          <span class="font-medium text-gray-900">
            {{ $item['label'] ?? '...' }}
          </span>
        @endif
      @endforeach
    </ol>
  </nav>
@endif
