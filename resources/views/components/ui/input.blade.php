@props(['label' => null, 'name', 'type' => 'text', 'value' => null, 'placeholder' => null])

<div class="space-y-1">
    @if($label)
        <label class="text-xs font-medium text-gray-600">{{ $label }}</label>
    @endif

    <input
        type="{{ $type }}"
        name="{{ $name }}"
        value="{{ old($name, $value) }}"
        placeholder="{{ $placeholder }}"
        class="w-full rounded-md border-gray-300 focus:border-brand-primary focus:ring-brand-primary"
    />

    @error($name)
        <p class="text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
