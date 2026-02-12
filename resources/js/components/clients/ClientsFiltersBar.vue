<script setup>
/**
 * Clients: Search card (Company Name required, Account Number optional) + filters bar.
 */
defineProps({
  filters: { type: Object, required: true },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['search', 'clear'])
</script>

<template>
  <div class="space-y-4">
    <!-- Search Client card (as in reference image) -->
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
      <h3 class="text-sm font-medium text-gray-900 mb-3">Search Client</h3>
      <div class="flex flex-wrap items-end gap-4">
        <div class="min-w-[140px] max-w-[200px] flex-1">
          <label for="client-company-name" class="block text-xs text-gray-700 mb-0.5">
            Company Name
          </label>
          <input
            id="client-company-name"
            v-model="filters.company_name"
            type="text"
            placeholder="Search by company name..."
            class="w-full rounded border border-gray-300 bg-white px-2.5 py-1.5 text-xs text-gray-700 placeholder-gray-400 focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
            @keyup.enter="$emit('search')"
          />
        </div>
        <div class="min-w-[140px] max-w-[200px] flex-1">
          <label for="client-account-number" class="block text-xs text-gray-700 mb-0.5">
            Account Number
          </label>
          <input
            id="client-account-number"
            v-model="filters.account_number"
            type="text"
            placeholder="Search by account number..."
            class="w-full rounded border border-gray-300 bg-white px-2.5 py-1.5 text-xs text-gray-700 placeholder-gray-400 focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
            @keyup.enter="$emit('search')"
          />
        </div>
        <div class="flex flex-1 flex-wrap items-center gap-2">
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
          <span class="ml-auto inline-flex items-center">
            <slot name="customize-columns" />
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
