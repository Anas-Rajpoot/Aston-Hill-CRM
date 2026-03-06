<script setup>
/**
 * Default filters: Status, Category.
 */
import HorizontalScrollToolbar from '@/components/common/HorizontalScrollToolbar.vue'

defineProps({
  filters: { type: Object, required: true },
  filterOptions: {
    type: Object,
    default: () => ({ statuses: [], categories: [] }),
  },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['apply', 'reset'])
</script>

<template>
  <div class="rounded-lg border border-gray-200 bg-white px-2 py-2">
    <HorizontalScrollToolbar>
    <label class="sr-only">Status</label>
    <select
      v-model="filters.status"
      class="w-[180px] shrink-0 rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
      :disabled="loading"
    >
      <option value="">By Default</option>
      <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">
        {{ s.label }}
      </option>
    </select>

    <label class="sr-only">Category</label>
    <select
      v-model="filters.category"
      class="w-[200px] shrink-0 rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
      :disabled="loading"
    >
      <option value="">Category</option>
      <option v-for="c in filterOptions.categories" :key="c" :value="c">
        {{ c }}
      </option>
    </select>

    <div class="ml-auto flex shrink-0 items-center gap-2">
      <button
        type="button"
        class="inline-flex shrink-0 items-center rounded bg-brand-primary px-4 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover focus:ring-2 focus:ring-brand-primary disabled:opacity-50"
        :disabled="loading"
        @click="$emit('apply')"
      >
        Apply
      </button>
      <button
        type="button"
        class="shrink-0 rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
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
