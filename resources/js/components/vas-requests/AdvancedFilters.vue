<script setup>
/**
 * Advanced Filters – only filters not in the default bar (no Category, Type, Status).
 * Compact layout so no horizontal scroll is needed.
 */
import { computed } from 'vue'

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
    }),
  },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['apply', 'reset'])

const activeCount = computed(() => {
  const f = props.filters
  let n = 0
  if (f.q) n++
  if (f.account_number) n++
  if (f.company_name) n++
  if (f.product) n++
  if (f.from || f.to) n++
  if (f.submitted_from || f.submitted_to) n++
  if (f.updated_from || f.updated_to) n++
  if (f.mrc !== '' && f.mrc != null) n++
  if (f.quantity !== '' && f.quantity != null) n++
  if (f.sales_agent_id || f.team_leader_id || f.manager_id) n++
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
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Account #</label>
          <input
            v-model="filters.account_number"
            type="text"
            placeholder="Account..."
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Company</label>
          <input
            v-model="filters.company_name"
            type="text"
            placeholder="Company..."
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Product</label>
          <select
            v-model="filters.product"
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          >
            <option value="">All Products</option>
            <option v-for="p in filterOptions.products" :key="p" :value="p">{{ p }}</option>
          </select>
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Created From</label>
          <input
            v-model="filters.from"
            type="date"
            placeholder="DD-MM-YYYY"
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Created To</label>
          <input
            v-model="filters.to"
            type="date"
            placeholder="DD-MM-YYYY"
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Submitted From</label>
          <input
            v-model="filters.submitted_from"
            type="date"
            placeholder="DD-MM-YYYY"
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Submitted To</label>
          <input
            v-model="filters.submitted_to"
            type="date"
            placeholder="DD-MM-YYYY"
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Updated From</label>
          <input
            v-model="filters.updated_from"
            type="date"
            placeholder="DD-MM-YYYY"
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Updated To</label>
          <input
            v-model="filters.updated_to"
            type="date"
            placeholder="DD-MM-YYYY"
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">MRC</label>
          <input
            v-model="filters.mrc"
            type="number"
            min="0"
            step="0.01"
            placeholder="Enter MRC (AED)"
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
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
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div>
          <label class="mb-0.5 block text-xs font-medium text-gray-600">Sales Agent</label>
          <select
            v-model="filters.sales_agent_id"
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
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
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
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
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          >
            <option :value="null">All</option>
            <option v-for="u in filterOptions.managers" :key="u.id" :value="u.id">{{ u.name }}</option>
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
