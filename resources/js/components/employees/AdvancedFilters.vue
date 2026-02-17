<script setup>
/**
 * Advanced filters: Search, Name, Email, Manager, Team Leader, Role, date ranges.
 * Status, Department are in the top filter bar only.
 * Date display: dd-mm-yyyy.
 */
import { computed } from 'vue'
import { toDdMmYyyy, fromDdMmYyyy } from '@/lib/dateFormat'

const props = defineProps({
  visible: { type: Boolean, default: false },
  filters: { type: Object, required: true },
  filterOptions: {
    type: Object,
    default: () => ({
      managers: [],
      team_leaders: [],
      roles: [],
    }),
  },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['apply', 'reset'])

const activeCount = computed(() => {
  const f = props.filters
  let n = 0
  if (f.q) n++
  if (f.name) n++
  if (f.email) n++
  if (f.role) n++
  if (f.manager_id || f.team_leader_id) n++
  if (f.joining_from || f.joining_to) n++
  if (f.terminate_from || f.terminate_to) n++
  return n
})

const joiningFromDisplay = computed({
  get: () => toDdMmYyyy(props.filters.joining_from),
  set: (v) => { props.filters.joining_from = fromDdMmYyyy(v) || '' },
})
const joiningToDisplay = computed({
  get: () => toDdMmYyyy(props.filters.joining_to),
  set: (v) => { props.filters.joining_to = fromDdMmYyyy(v) || '' },
})
const terminateFromDisplay = computed({
  get: () => toDdMmYyyy(props.filters.terminate_from),
  set: (v) => { props.filters.terminate_from = fromDdMmYyyy(v) || '' },
})
const terminateToDisplay = computed({
  get: () => toDdMmYyyy(props.filters.terminate_to),
  set: (v) => { props.filters.terminate_to = fromDdMmYyyy(v) || '' },
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
      <div class="border-b border-gray-200 bg-gray-50/50 px-3 py-2">
        <h3 class="text-xs font-medium text-gray-700">
          Advanced Filters
          <span class="ml-1.5 font-normal text-gray-500">{{ activeCount }} active</span>
        </h3>
      </div>

      <div class="grid grid-cols-3 gap-x-3 gap-y-2 p-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6">
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Search</label>
          <input
            v-model="filters.q"
            type="text"
            placeholder="Name, email, ID..."
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Name</label>
          <input
            v-model="filters.name"
            type="text"
            placeholder="Employee name..."
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Email</label>
          <input
            v-model="filters.email"
            type="text"
            placeholder="Email..."
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Role</label>
          <select
            v-model="filters.role"
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          >
            <option value="">All Roles</option>
            <option v-for="r in filterOptions.roles" :key="r.value || r" :value="r.value || r">{{ r.label || r }}</option>
          </select>
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Manager</label>
          <select
            v-model="filters.manager_id"
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          >
            <option :value="null">All</option>
            <option v-for="u in filterOptions.managers" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Team Leader</label>
          <select
            v-model="filters.team_leader_id"
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          >
            <option :value="null">All</option>
            <option v-for="u in filterOptions.team_leaders" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Joining From</label>
          <input
            v-model="joiningFromDisplay"
            type="text"
            placeholder="dd-mm-yyyy"
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Joining To</label>
          <input
            v-model="joiningToDisplay"
            type="text"
            placeholder="dd-mm-yyyy"
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Terminate From</label>
          <input
            v-model="terminateFromDisplay"
            type="text"
            placeholder="dd-mm-yyyy"
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Terminate To</label>
          <input
            v-model="terminateToDisplay"
            type="text"
            placeholder="dd-mm-yyyy"
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-2 border-t border-gray-200 bg-gray-50/50 px-3 py-2">
        <button
          type="button"
          class="inline-flex items-center rounded bg-green-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-green-700 disabled:opacity-50"
          :disabled="loading"
          @click="$emit('apply')"
        >
          <svg class="mr-1 h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          Apply Filters
        </button>
        <button
          type="button"
          class="rounded px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100 disabled:opacity-50"
          :disabled="loading"
          @click="$emit('reset')"
        >
          Clear All
        </button>
      </div>
    </div>
  </Transition>
</template>
