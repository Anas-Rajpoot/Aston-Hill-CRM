@php
  $trail = session('breadcrumbs_trail', []);
@endphp

@if(count($trail) > 0)
  <nav class="mb-4 text-sm text-gray-600">
    <ol class="flex items-center flex-wrap gap-2">
      @foreach($trail as $item)
        @if(!$loop->first)
          <span class="text-gray-400">/</span>
        @endif

        @if(!$loop->last)
          <a href="{{ $item['url'] ?? '#' }}" class="text-indigo-600 hover:underline">
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
