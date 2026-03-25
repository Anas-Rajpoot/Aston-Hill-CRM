<script setup>
/**
 * Advanced Filters – only filters not in the default bar (no Category, Type, Status).
 * Compact layout so no horizontal scroll is needed.
 * Date display: dd-mm-yyyy (placeholder and value).
 */
import { computed, ref } from 'vue'

const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']
function formatDate(val) {
  if (!val) return ''
  const [y, m, d] = val.split('-')
  if (!y || !m || !d) return ''
  return `${d}-${MONTHS[parseInt(m, 10) - 1]}-${y}`
}

const createdFromRef = ref(null)
const createdToRef = ref(null)
const submittedFromRef = ref(null)
const submittedToRef = ref(null)

function openPicker(r) {
  const el = r?.$el ?? r
  if (el?.showPicker) {
    try { el.showPicker() } catch { el.click() }
  } else if (el) {
    el.click()
  }
}

const props = defineProps({
  visible: { type: Boolean, default: false },
  filters: { type: Object, required: true },
  filterOptions: {
    type: Object,
    default: () => ({
      products: [],
      managers: [],
      teamLeaders: [],
      salesAgents: [],
      types: [],
      executives: [],
      call_verification_options: [],
      documents_verification_options: [],
      du_status_options: [],
    }),
  },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['apply', 'reset'])

const uniqueTypeOptions = computed(() => {
  const list = Array.isArray(props.filterOptions?.types) ? props.filterOptions.types : []
  const seen = new Set()
  const out = []
  for (const item of list) {
    const name = String(item?.name ?? '').trim()
    if (!name) continue
    const key = name.toLowerCase()
    if (seen.has(key)) continue
    seen.add(key)
    out.push(item)
  }
  return out
})

const activeCount = computed(() => {
  const f = props.filters
  let n = 0
  if (f.q) n++
  if (f.account_number) n++
  if (f.company_name) n++
  if (f.product) n++
  if (f.from || f.to) n++
  if (f.submitted_from || f.submitted_to) n++
  if (f.mrc !== '' && f.mrc != null) n++
  if (f.quantity !== '' && f.quantity != null) n++
  if (f.sales_agent_id || f.team_leader_id || f.manager_id) n++
  if (f.executive_id) n++
  if (f.call_verification) n++
  if (f.documents_verification) n++
  if (f.du_status) n++
  return n
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

      <!-- Compact grid: many columns, small gaps, no scroll -->
      <div class="grid grid-cols-3 gap-x-3 gap-y-2 p-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6">
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Search</label>
          <input
            v-model="filters.q"
            type="text"
            placeholder="Company, account, email..."
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Account #</label>
          <input
            v-model="filters.account_number"
            type="text"
            placeholder="Account..."
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Company</label>
          <input
            v-model="filters.company_name"
            type="text"
            placeholder="Company..."
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Product</label>
          <select
            v-model="filters.product"
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
            :disabled="loading"
          >
            <option value="">All Products</option>
            <option v-for="p in filterOptions.products" :key="p" :value="p">{{ p }}</option>
          </select>
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Service Type</label>
          <select
            v-model="filters.service_type_id"
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
            :disabled="loading"
          >
            <option :value="null">All Types</option>
            <option v-for="t in uniqueTypeOptions" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Created From</label>
          <div class="relative" @click="openPicker(createdFromRef)">
            <input
              type="text"
              readonly
              :value="formatDate(filters.from)"
              placeholder="DD-MM-YYYY"
              class="w-full cursor-pointer rounded border border-gray-300 bg-white px-2 py-1.5 pr-7 text-xs focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
              :disabled="loading"
            />
            <svg class="pointer-events-none absolute right-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
            <input ref="createdFromRef" v-model="filters.from" type="date" class="sr-only" :disabled="loading" tabindex="-1" />
          </div>
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Created To</label>
          <div class="relative" @click="openPicker(createdToRef)">
            <input
              type="text"
              readonly
              :value="formatDate(filters.to)"
              placeholder="DD-MM-YYYY"
              class="w-full cursor-pointer rounded border border-gray-300 bg-white px-2 py-1.5 pr-7 text-xs focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
              :disabled="loading"
            />
            <svg class="pointer-events-none absolute right-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
            <input ref="createdToRef" v-model="filters.to" type="date" class="sr-only" :disabled="loading" tabindex="-1" />
          </div>
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Submitted From</label>
          <div class="relative" @click="openPicker(submittedFromRef)">
            <input
              type="text"
              readonly
              :value="formatDate(filters.submitted_from)"
              placeholder="DD-MM-YYYY"
              class="w-full cursor-pointer rounded border border-gray-300 bg-white px-2 py-1.5 pr-7 text-xs focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
              :disabled="loading"
            />
            <svg class="pointer-events-none absolute right-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
            <input ref="submittedFromRef" v-model="filters.submitted_from" type="date" class="sr-only" :disabled="loading" tabindex="-1" />
          </div>
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Submitted To</label>
          <div class="relative" @click="openPicker(submittedToRef)">
            <input
              type="text"
              readonly
              :value="formatDate(filters.submitted_to)"
              placeholder="DD-MM-YYYY"
              class="w-full cursor-pointer rounded border border-gray-300 bg-white px-2 py-1.5 pr-7 text-xs focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
              :disabled="loading"
            />
            <svg class="pointer-events-none absolute right-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
            <input ref="submittedToRef" v-model="filters.submitted_to" type="date" class="sr-only" :disabled="loading" tabindex="-1" />
          </div>
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">MRC</label>
          <input
            v-model="filters.mrc"
            type="number"
            min="0"
            step="0.01"
            placeholder="Enter MRC (AED)"
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Quantity</label>
          <input
            v-model="filters.quantity"
            type="number"
            min="0"
            placeholder="Enter quantity"
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Sales Agent</label>
          <select
            v-model="filters.sales_agent_id"
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
            :disabled="loading"
          >
            <option :value="null">All</option>
            <option v-for="u in filterOptions.salesAgents" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Team Leader</label>
          <select
            v-model="filters.team_leader_id"
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
            :disabled="loading"
          >
            <option :value="null">All</option>
            <option v-for="u in filterOptions.teamLeaders" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Manager</label>
          <select
            v-model="filters.manager_id"
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
            :disabled="loading"
          >
            <option :value="null">All</option>
            <option v-for="u in filterOptions.managers" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Back Office Executive</label>
          <select
            v-model="filters.executive_id"
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
            :disabled="loading"
          >
            <option :value="null">All</option>
            <option v-for="u in filterOptions.executives" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Call Verification</label>
          <select
            v-model="filters.call_verification"
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
            :disabled="loading"
          >
            <option value="">All</option>
            <option v-for="o in filterOptions.call_verification_options" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Documents Verification</label>
          <select
            v-model="filters.documents_verification"
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
            :disabled="loading"
          >
            <option value="">All</option>
            <option v-for="o in filterOptions.documents_verification_options" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">DU Status</label>
          <select
            v-model="filters.du_status"
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
            :disabled="loading"
          >
            <option value="">All</option>
            <option v-for="o in filterOptions.du_status_options" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-2 border-t border-gray-200 bg-gray-50/50 px-3 py-2">
        <button
          type="button"
          class="inline-flex items-center rounded bg-brand-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-primary-hover disabled:opacity-50"
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
