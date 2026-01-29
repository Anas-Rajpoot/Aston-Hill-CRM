@props(['step'])

@php
$steps = [
  1 => 'Primary Info',
  2 => 'Service Category',
  3 => 'Service Details',
  4 => 'Documents',
];
@endphp

<div class="mb-6">
  <div class="flex items-center justify-between">
    @foreach($steps as $i => $label)
      <div class="flex-1 flex items-center">
        <div class="flex items-center gap-2">
          <div class="
            w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold
            {{ $step >= $i ? 'bg-brand-primary text-white' : 'bg-gray-200 text-gray-600' }}
          ">
            {{ $i }}
          </div>
          <span class="text-sm {{ $step >= $i ? 'text-brand-text' : 'text-gray-400' }}">
            {{ $label }}
          </span>
        </div>

        @if(!$loop->last)
          <div class="flex-1 h-[2px] mx-3 {{ $step > $i ? 'bg-brand-primary' : 'bg-gray-200' }}"></div>
        @endif
      </div>
    @endforeach
  </div>
</div>
