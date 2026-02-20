<script setup>
/**
 * Default filters for Field Submissions: Status, Product.
 */
defineProps({
  filters: { type: Object, required: true },
  filterOptions: {
    type: Object,
    default: () => ({ statuses: [], products: [], emirates: [] }),
  },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['apply', 'reset'])
</script>

<template>
  <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-3">
    <label class="sr-only">Status</label>
    <select
      v-model="filters.status"
      class="flex-1 min-w-0 rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
      :disabled="loading"
    >
      <option value="">Status</option>
      <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">
        {{ s.label }}
      </option>
    </select>

    <label class="sr-only">Product</label>
    <select
      v-model="filters.product"
      class="flex-1 min-w-0 rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
      :disabled="loading"
    >
      <option value="">Product</option>
      <option v-for="p in filterOptions.products" :key="p" :value="p">{{ p }}</option>
    </select>

    <label class="sr-only">Emirates</label>
    <select
      v-model="filters.emirates"
      class="flex-1 min-w-0 rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
      :disabled="loading"
    >
      <option value="">Emirates</option>
      <option v-for="e in filterOptions.emirates" :key="e" :value="e">{{ e }}</option>
    </select>

    <div class="flex shrink-0 items-center gap-2">
      <button
        type="button"
        class="inline-flex items-center rounded bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700 focus:ring-2 focus:ring-green-500 disabled:opacity-50"
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
        class="rounded border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
        :disabled="loading"
        @click="$emit('reset')"
      >
        Reset
      </button>
    </div>
    <div class="flex shrink-0 items-center gap-2">
      <slot name="after-reset" />
    </div>
  </div>
</template>
