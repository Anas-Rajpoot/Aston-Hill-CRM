<template>
  <div class="relative mt-1 block w-full">
    <input
      type="text"
      readonly
      :value="displayValue"
      :placeholder="placeholder"
      class="block w-full rounded border border-gray-300 bg-white px-3 py-2 pr-10 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500 cursor-pointer"
      :class="inputClass"
      :disabled="disabled"
      :aria-label="`Select date (${placeholder})`"
      @click="openPicker"
    />
    <!-- Calendar button: clear way to open picker -->
    <button
      type="button"
      class="absolute right-0 top-0 bottom-0 flex w-10 items-center justify-center rounded-r border-0 border-l border-gray-300 bg-transparent text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-inset"
      aria-label="Open calendar"
      :disabled="disabled"
      @click.stop="openPicker"
    >
      <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
      </svg>
    </button>
    <!-- Hidden native date input used only to invoke picker -->
    <input
      ref="dateInputRef"
      type="date"
      :value="modelValue || ''"
      class="absolute opacity-0 pointer-events-none w-0 h-0"
      :disabled="disabled"
      tabindex="-1"
      @change="onDateChange"
      @input="onDateChange"
    />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { toDdMonYyyyDash } from '@/lib/dateFormat'

const props = defineProps({
  modelValue: { type: String, default: '' },
  inputClass: { type: String, default: '' },
  placeholder: { type: String, default: 'dd-Mon-yyyy' },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const dateInputRef = ref(null)

const displayValue = computed(() => {
  if (!props.modelValue || typeof props.modelValue !== 'string') return ''
  return toDdMonYyyyDash(props.modelValue.trim().slice(0, 10)) || props.modelValue
})

function openPicker() {
  if (props.disabled) return
  const el = dateInputRef.value
  if (!el) return
  if (typeof el.showPicker === 'function') {
    try {
      el.showPicker()
      return
    } catch {
      // Fallback for browsers that throw when showPicker is unavailable.
    }
  }
  el.click()
}

function onDateChange(e) {
  const val = e.target?.value
  if (val != null) emit('update:modelValue', val)
}
</script>
