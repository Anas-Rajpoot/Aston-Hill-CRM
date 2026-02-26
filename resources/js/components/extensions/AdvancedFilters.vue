<script setup>
/**
 * Advanced filters for Cisco Extensions: Extension, Gateway, User Name, Assigned To,
 * Manager, Team Leader, Usage, Created Date From/To. Dates: dd-mm-yyyy.
 * (Status and Landline Number are in general filters and should not be duplicated here.)
 */
import { computed } from 'vue'
import DateInputDdMmYyyy from '@/components/DateInputDdMmYyyy.vue'

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
  managerOptions: { type: Array, default: () => [] },
  teamLeaderOptions: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['apply', 'reset'])

const createdFromDisplay = computed({
  get: () => props.filters.created_from || '',
  set: (v) => { props.filters.created_from = v || '' },
})
const createdToDisplay = computed({
  get: () => props.filters.created_to || '',
  set: (v) => { props.filters.created_to = v || '' },
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
      <div class="border-b border-gray-100 bg-gray-50 px-4 py-2">
        <p class="text-xs font-medium text-gray-600">All filters</p>
        <p class="text-xs text-gray-500">Extension, Gateway, User Name, Assigned To, Manager, Team Leader, Usage, Created Date From/To</p>
      </div>
      <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
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
          <label class="mb-1 block text-xs font-medium text-gray-600">User Name</label>
          <input
            v-model="filters.username"
            type="text"
            placeholder="Search user name..."
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
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
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Manager</label>
          <select
            v-model="filters.manager_id"
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          >
            <option value="">All Managers</option>
            <option v-for="m in managerOptions" :key="m.id" :value="m.id">{{ m.name || m.label }}</option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Team Leader</label>
          <select
            v-model="filters.team_leader_id"
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          >
            <option value="">All Team Leaders</option>
            <option v-for="t in teamLeaderOptions" :key="t.id" :value="t.id">{{ t.name || t.label }}</option>
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
          <DateInputDdMmYyyy v-model="createdFromDisplay" placeholder="dd-Mon-yyyy" :disabled="loading" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">Created Date To</label>
          <DateInputDdMmYyyy v-model="createdToDisplay" placeholder="dd-Mon-yyyy" :disabled="loading" />
        </div>
      </div>

      <div class="w-full border-t border-gray-200 px-4 py-3">
        <div class="flex w-full flex-wrap items-center justify-end gap-3">
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
    </div>
  </Transition>
</template>
