@props(['step' => 1])

@php
  $items = [
    1 => 'Primary Info',
    2 => 'Service Category',
    3 => 'Service Type & Fields',
    4 => 'Upload Documents',
  ];
@endphp

<div class="flex flex-wrap gap-2">
  @foreach($items as $i => $label)
    <div class="px-3 py-2 rounded-lg text-sm border
      {{ $step == $i ? 'bg-brand-primary/10 border-brand-primary text-brand-dark' : 'bg-white border-gray-200 text-gray-600' }}">
      <span class="font-semibold">{{ $i }}</span> — {{ $label }}
    </div>
  @endforeach
</div>
