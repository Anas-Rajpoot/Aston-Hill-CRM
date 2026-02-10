<script setup>
/**
 * Clients: Search card (Company Name required, Account Number optional) + filters bar.
 */
defineProps({
  filters: { type: Object, required: true },
  filterOptions: {
    type: Object,
    default: () => ({ statuses: [], managers: [], team_leaders: [], sales_agents: [] }),
  },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['search', 'clear', 'apply', 'reset'])
</script>

<template>
  <div class="space-y-4">
    <!-- Search Client card (as in reference image) -->
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
      <h3 class="text-sm font-medium text-gray-900 mb-3">Search Client</h3>
      <div class="flex flex-wrap items-end gap-4">
        <div class="min-w-[200px] flex-1">
          <label for="client-company-name" class="block text-sm text-gray-700 mb-1">
            Company Name <span class="text-red-600">*</span>
          </label>
          <input
            id="client-company-name"
            v-model="filters.company_name"
            type="text"
            placeholder="Search by company name..."
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 placeholder-gray-400 focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
            @keyup.enter="$emit('search')"
          />
        </div>
        <div class="min-w-[200px] flex-1">
          <label for="client-account-number" class="block text-sm text-gray-700 mb-1">
            Account Number (Optional)
          </label>
          <input
            id="client-account-number"
            v-model="filters.account_number"
            type="text"
            placeholder="Search by account number..."
            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 placeholder-gray-400 focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
            @keyup.enter="$emit('search')"
          />
        </div>
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="inline-flex items-center rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 focus:ring-2 focus:ring-green-500 disabled:opacity-50"
            :disabled="loading"
            @click="$emit('search')"
          >
            <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            Search
          </button>
          <button
            type="button"
            class="inline-flex items-center rounded border border-gray-300 bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
            :disabled="loading"
            @click="$emit('clear')"
          >
            <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            Clear
          </button>
        </div>
      </div>
      <div class="mt-3 flex justify-end">
        <slot name="customize-columns" />
      </div>
    </div>

    <!-- Filters row: Status, Manager, Team Leader, Sales Agent, Apply, Reset, Customize Columns slot -->
    <div class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3">
      <select
        v-model="filters.status"
        class="min-w-[140px] rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
        :disabled="loading"
      >
        <option value="">Status</option>
        <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">
          {{ s.label }}
        </option>
      </select>
      <select
        v-model="filters.manager_id"
        class="min-w-[160px] rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
        :disabled="loading"
      >
        <option :value="null">Manager</option>
        <option v-for="m in filterOptions.managers" :key="m.id" :value="m.id">
          {{ m.name }}
        </option>
      </select>
      <select
        v-model="filters.team_leader_id"
        class="min-w-[160px] rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
        :disabled="loading"
      >
        <option :value="null">Team Leader</option>
        <option v-for="t in filterOptions.team_leaders" :key="t.id" :value="t.id">
          {{ t.name }}
        </option>
      </select>
      <select
        v-model="filters.sales_agent_id"
        class="min-w-[160px] rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
        :disabled="loading"
      >
        <option :value="null">Sales Agent</option>
        <option v-for="s in filterOptions.sales_agents" :key="s.id" :value="s.id">
          {{ s.name }}
        </option>
      </select>
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
      <div class="ml-auto flex items-center gap-2">
        <slot name="after-reset" />
      </div>
    </div>
  </div>
</template>
