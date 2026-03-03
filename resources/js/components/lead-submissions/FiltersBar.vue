<script setup>
/**
 * Default filters – always visible: Service Category, Status.
 */
import HorizontalScrollToolbar from '@/components/common/HorizontalScrollToolbar.vue'

const props = defineProps({
  filters: { type: Object, required: true },
  filterOptions: { type: Object, default: () => ({ categories: [], types: [], statuses: [] }) },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['apply', 'reset'])

</script>

<template>
  <div class="rounded-lg border border-gray-200 bg-white px-2 py-2">
    <HorizontalScrollToolbar>
      <select
        v-model="filters.submission_type"
        class="w-[170px] shrink-0 rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
        :disabled="loading"
      >
        <option value="">Request Type</option>
        <option value="new">New Submission</option>
        <option value="resubmission">Resubmission</option>
      </select>

      <select
        v-model="filters.service_category_id"
        class="w-[190px] shrink-0 rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
        :disabled="loading"
      >
        <option :value="null">Service Categories</option>
        <option v-for="c in filterOptions.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>

      <select
        v-model="filters.status"
        class="w-[140px] shrink-0 rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
        :disabled="loading"
      >
        <option value="">Status</option>
        <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
      </select>

      <slot name="before-apply" />
      <button
        type="button"
        class="inline-flex shrink-0 items-center rounded bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
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

    <div class="ml-2 flex shrink-0 items-center gap-2">
      <slot name="after-reset" />
    </div>
    </HorizontalScrollToolbar>
  </div>
</template>
