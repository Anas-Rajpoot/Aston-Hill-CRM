<script setup>
/**
 * Default filters for Field Submissions: Status, Product.
 */
import HorizontalScrollToolbar from '@/components/common/HorizontalScrollToolbar.vue'

function toReadableLabel(value) {
  const s = String(value ?? '').trim()
  if (!s) return ''
  if (s.includes('_') || s.includes('-')) {
    return s
      .replace(/[_-]+/g, ' ')
      .replace(/\b\w/g, (char) => char.toUpperCase())
  }
  return s
}

function optionValue(option) {
  if (option && typeof option === 'object') {
    return option.value ?? option.id ?? option.key ?? option.label ?? ''
  }
  return option ?? ''
}

function optionLabel(option) {
  if (option && typeof option === 'object') {
    const value = option.value ?? option.id ?? option.key ?? ''
    return option.label ?? option.name ?? toReadableLabel(value)
  }
  return toReadableLabel(option)
}

defineProps({
  filters: { type: Object, required: true },
  filterOptions: {
    type: Object,
    default: () => ({ field_statuses: [], products: [], emirates: [] }),
  },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['apply', 'reset'])
</script>

<template>
  <div class="rounded-lg border border-gray-200 bg-white px-2 py-2">
    <HorizontalScrollToolbar>
    <label class="sr-only">Field Status</label>
    <select
      v-model="filters.field_status"
      class="w-[150px] shrink-0 rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
      :disabled="loading"
    >
      <option value="">Field Status</option>
      <option value="unassigned">Unassigned</option>
      <option v-for="s in filterOptions.field_statuses" :key="String(optionValue(s))" :value="optionValue(s)">
        {{ optionLabel(s) }}
      </option>
    </select>

    <label class="sr-only">Product</label>
    <select
      v-model="filters.product"
      class="w-[170px] shrink-0 rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
      :disabled="loading"
    >
      <option value="">Product</option>
      <option v-for="p in filterOptions.products" :key="String(optionValue(p))" :value="optionValue(p)">{{ optionLabel(p) }}</option>
    </select>

    <label class="sr-only">Emirates</label>
    <select
      v-model="filters.emirates"
      class="w-[160px] shrink-0 rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
      :disabled="loading"
    >
      <option value="">Emirates</option>
      <option v-for="e in filterOptions.emirates" :key="String(optionValue(e))" :value="optionValue(e)">{{ optionLabel(e) }}</option>
    </select>

    <div class="ml-auto flex shrink-0 items-center gap-2">
      <slot name="before-apply" />
      <button
        type="button"
        class="inline-flex shrink-0 items-center rounded bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700 focus:ring-2 focus:ring-green-500 disabled:opacity-50"
        :disabled="loading"
        @click="$emit('apply')"
      >
        <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        Apply
      </button>
      <button
        type="button"
        class="shrink-0 rounded border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
        :disabled="loading"
        @click="$emit('reset')"
      >
        Reset
      </button>
    </div>
    <div class="ml-2 flex shrink-0 items-center gap-2">
      <slot name="after-reset" />
    </div>
    </HorizontalScrollToolbar>
  </div>
</template>
