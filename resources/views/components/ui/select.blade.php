@props(['label' => null, 'name', 'options' => [], 'value' => null, 'placeholder' => 'Select'])

<div class="space-y-1">
    @if($label)
        <label class="text-xs font-medium text-gray-600">{{ $label }}</label>
    @endif

    <select
        name="{{ $name }}"
        class="w-full rounded-md border-gray-300 focus:border-brand-primary focus:ring-brand-primary"
    >
        <option value="">{{ $placeholder }}</option>
        @foreach($options as $k => $v)
            <option value="{{ $k }}" @selected(old($name, $value)==$k)>{{ $v }}</option>
        @endforeach
    </select>

    @error($name)
        <p class="text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
