<script setup>
/**
 * Advanced filters for Customer Support – search, dates, team, issue category, status.
 * Date display: dd-Mon-yyyy with native calendar picker.
 */
import { ref, computed } from 'vue'
import { toDdMonYyyyDash } from '@/lib/dateFormat'

const props = defineProps({
  visible: { type: Boolean, default: false },
  filters: { type: Object, required: true },
  filterOptions: {
    type: Object,
    default: () => ({
      issue_categories: [],
      statuses: [],
      managers: [],
      team_leaders: [],
      sales_agents: [],
    }),
  },
  loading: { type: Boolean, default: false },
})

defineEmits(['apply', 'reset'])

const activeCount = computed(() => {
  const f = props.filters
  let n = 0
  if (f.q) n++
  if (f.company_name) n++
  if (f.account_number) n++
  if (f.contact_number) n++
  if (f.from || f.to) n++
  if (f.submitted_from || f.submitted_to) n++
  if (f.sales_agent_id || f.team_leader_id || f.manager_id) n++
  return n
})

const fromRef = ref(null)
const toRef = ref(null)
const submittedFromRef = ref(null)
const submittedToRef = ref(null)

function openPicker(r) {
  const el = r?.$el ?? r
  if (el?.showPicker) {
    try { el.showPicker() } catch { el.click() }
  } else if (el) { el.click() }
}

function formatDisplay(ymd) {
  return toDdMonYyyyDash(ymd) || ''
}
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
            placeholder="Company, account, contact..."
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Company</label>
          <input
            v-model="filters.company_name"
            type="text"
            placeholder="Company name..."
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Account Number</label>
          <input
            v-model="filters.account_number"
            type="text"
            placeholder="Account number..."
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Contact Number</label>
          <input
            v-model="filters.contact_number"
            type="text"
            placeholder="Contact..."
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Created From</label>
          <div class="relative" @click="openPicker(fromRef)">
            <input
              type="text"
              readonly
              :value="formatDisplay(filters.from)"
              placeholder="dd-Mon-yyyy"
              class="w-full cursor-pointer rounded border border-gray-300 bg-white px-2 py-1.5 pr-7 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
              :disabled="loading"
            />
            <svg class="pointer-events-none absolute right-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
            <input ref="fromRef" v-model="filters.from" type="date" class="sr-only" tabindex="-1" :disabled="loading" />
          </div>
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Created To</label>
          <div class="relative" @click="openPicker(toRef)">
            <input
              type="text"
              readonly
              :value="formatDisplay(filters.to)"
              placeholder="dd-Mon-yyyy"
              class="w-full cursor-pointer rounded border border-gray-300 bg-white px-2 py-1.5 pr-7 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
              :disabled="loading"
            />
            <svg class="pointer-events-none absolute right-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
            <input ref="toRef" v-model="filters.to" type="date" class="sr-only" tabindex="-1" :disabled="loading" />
          </div>
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Submitted From</label>
          <div class="relative" @click="openPicker(submittedFromRef)">
            <input
              type="text"
              readonly
              :value="formatDisplay(filters.submitted_from)"
              placeholder="dd-Mon-yyyy"
              class="w-full cursor-pointer rounded border border-gray-300 bg-white px-2 py-1.5 pr-7 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
              :disabled="loading"
            />
            <svg class="pointer-events-none absolute right-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
            <input ref="submittedFromRef" v-model="filters.submitted_from" type="date" class="sr-only" tabindex="-1" :disabled="loading" />
          </div>
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Submitted To</label>
          <div class="relative" @click="openPicker(submittedToRef)">
            <input
              type="text"
              readonly
              :value="formatDisplay(filters.submitted_to)"
              placeholder="dd-Mon-yyyy"
              class="w-full cursor-pointer rounded border border-gray-300 bg-white px-2 py-1.5 pr-7 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
              :disabled="loading"
            />
            <svg class="pointer-events-none absolute right-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
            <input ref="submittedToRef" v-model="filters.submitted_to" type="date" class="sr-only" tabindex="-1" :disabled="loading" />
          </div>
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
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Sales Agent</label>
          <select
            v-model="filters.sales_agent_id"
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          >
            <option :value="null">All</option>
            <option v-for="u in filterOptions.sales_agents" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
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
