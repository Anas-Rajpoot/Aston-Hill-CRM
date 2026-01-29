@props(['label' => null, 'name', 'value' => null, 'placeholder' => null])

<div class="space-y-1">
    @if($label)
        <label class="text-xs font-medium text-gray-600">{{ $label }}</label>
    @endif

    <textarea
        name="{{ $name }}"
        placeholder="{{ $placeholder }}"
        class="w-full rounded-md border-gray-300 focus:border-brand-primary focus:ring-brand-primary"
        rows="4"
    >{{ old($name, $value) }}</textarea>

    @error($name)
        <p class="text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
