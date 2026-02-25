<script setup>
/**
 * Default filters: Status, Category.
 */
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
  <div class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3">
    <label class="sr-only">Status</label>
    <select
      v-model="filters.status"
      class="min-w-[180px] rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
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
      class="min-w-[200px] rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
      :disabled="loading"
    >
      <option value="">Category</option>
      <option v-for="c in filterOptions.categories" :key="c" :value="c">
        {{ c }}
      </option>
    </select>

    <div class="flex w-full lg:w-auto flex-wrap items-center gap-2 lg:ml-auto">
      <button
        type="button"
        class="inline-flex items-center rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 focus:ring-2 focus:ring-green-500 disabled:opacity-50"
        :disabled="loading"
        @click="$emit('apply')"
      >
        Apply
      </button>
      <button
        type="button"
        class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
        :disabled="loading"
        @click="$emit('reset')"
      >
        Reset
      </button>
    </div>
    <div class="ml-0 flex items-center gap-2 lg:ml-2">
      <slot name="after-reset" />
    </div>
  </div>
</template>
