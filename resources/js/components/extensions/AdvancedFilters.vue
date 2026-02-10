<script setup>
/**
 * Advanced filters for Cisco Extensions: Extension, Landline, Gateway, Assigned To,
 * Status (dropdown), Usage (dropdown), Created Date From/To. No title bar – visibility toggled by parent "Advanced Filters" button.
 * Dates: dd-mm-yyyy.
 */
import { computed } from 'vue'
import { toDdMmYyyy, fromDdMmYyyy } from '@/lib/dateFormat'

const props = defineProps({
  visible: { type: Boolean, default: false },
  filters: { type: Object, required: true },
  filterOptions: {
    type: Object,
    default: () => ({
      gateways: [],
      statuses: [],
      usage_options: [],
    }),
  },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['apply', 'reset'])

const createdFromDisplay = computed({
  get: () => toDdMmYyyy(props.filters.created_from),
  set: (v) => { props.filters.created_from = fromDdMmYyyy(v) || '' },
})
const createdToDisplay = computed({
  get: () => toDdMmYyyy(props.filters.created_to),
  set: (v) => { props.filters.created_to = fromDdMmYyyy(v) || '' },
})
</script>

<template>
  <Transition
    enter-active-class="transition ease-out duration-200"
    enter-from-class="opacity-0 -translate-y-2"
    enter-to-class="opacity-100 translate-y-0"
    leave-active-class="transition ease-in duration-150"
    leave-from-class="opacity-100 translate-y-0"
    leave-to-class="opacity-0 -translate-y-2"
  >
    <div v-show="visible" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
      <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Extension</label>
          <input
            v-model="filters.extension"
            type="text"
            placeholder="Search extension..."
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Landline Number</label>
          <input
            v-model="filters.landline_number"
            type="text"
            placeholder="Search landline..."
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Gateway</label>
          <select
            v-model="filters.gateway"
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          >
            <option value="">All Gateways</option>
            <option v-for="g in filterOptions.gateways" :key="g.value" :value="g.value">{{ g.label }}</option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Assigned To</label>
          <input
            v-model="filters.assigned_to_q"
            type="text"
            placeholder="Search employee..."
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
      </div>

      <div class="grid grid-cols-1 gap-4 border-t border-gray-100 px-4 pb-4 pt-2 sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Status</label>
          <select
            v-model="filters.status"
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          >
            <option value="">All Statuses</option>
            <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Usage</label>
          <select
            v-model="filters.usage"
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          >
            <option value="">All</option>
            <option v-for="u in filterOptions.usage_options" :key="u.value" :value="u.value">{{ u.label }}</option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Created Date From</label>
          <input
            v-model="createdFromDisplay"
            type="text"
            placeholder="DD-MM-YYYY"
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Created Date To</label>
          <input
            v-model="createdToDisplay"
            type="text"
            placeholder="DD-MM-YYYY"
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
      </div>

      <div class="flex gap-3 border-t border-gray-200 px-4 py-3">
        <button
          type="button"
          class="inline-flex items-center rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
          :disabled="loading"
          @click="$emit('apply')"
        >
          Apply Filters
        </button>
        <button
          type="button"
          class="inline-flex items-center rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
          :disabled="loading"
          @click="$emit('reset')"
        >
          Clear Filters
        </button>
      </div>
    </div>
  </Transition>
</template>
