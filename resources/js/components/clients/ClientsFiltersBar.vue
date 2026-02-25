<script setup>
/**
 * Clients: Search card (Company Name required, Account Number optional) + filters bar.
 */
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
  filters: { type: Object, required: true },
  loading: { type: Boolean, default: false },
  accountNumbers: { type: Array, default: () => [] },
  alertTypes: { type: Array, default: () => [] },
  showAlertTypeFilter: { type: Boolean, default: true },
  title: { type: String, default: 'Search Products & Services' },
  compactActions: { type: Boolean, default: false },
})

const emit = defineEmits(['search', 'clear'])

const accDropdownOpen = ref(false)
const accSearch = ref('')
const accDropdownRef = ref(null)

const filteredAccountNumbers = computed(() => {
  const q = accSearch.value.toLowerCase().trim()
  if (!q) return props.accountNumbers
  return props.accountNumbers.filter((a) => String(a).toLowerCase().includes(q))
})

function selectAccount(acc) {
  props.filters.account_number = acc
  accDropdownOpen.value = false
  accSearch.value = ''
}

function clearAccount() {
  props.filters.account_number = ''
  accDropdownOpen.value = false
  accSearch.value = ''
}

function toggleAccDropdown() {
  if (props.loading) return
  accDropdownOpen.value = !accDropdownOpen.value
  if (accDropdownOpen.value) accSearch.value = ''
}

function onClickOutside(e) {
  if (accDropdownRef.value && !accDropdownRef.value.contains(e.target)) {
    accDropdownOpen.value = false
  }
}

onMounted(() => document.addEventListener('mousedown', onClickOutside))
onBeforeUnmount(() => document.removeEventListener('mousedown', onClickOutside))
</script>

<template>
  <div class="space-y-4">
    <!-- Search Client card (as in reference image) -->
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
      <h3 class="text-sm font-medium text-gray-900 mb-3">{{ title }}</h3>
      <div class="flex flex-wrap lg:flex-nowrap items-end gap-2.5">
        <div class="min-w-[130px] max-w-[180px] flex-1 lg:flex-none lg:w-[165px]">
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
        <div ref="accDropdownRef" class="relative min-w-[130px] max-w-[180px] flex-1 lg:flex-none lg:w-[165px]">
          <label class="block text-xs text-gray-700 mb-0.5">Account Number</label>
          <button
            type="button"
            class="flex w-full items-center justify-between rounded border border-gray-300 bg-white px-2.5 py-1.5 text-xs text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500 disabled:opacity-50"
            :disabled="loading"
            @click="toggleAccDropdown"
          >
            <span :class="filters.account_number ? 'text-gray-900' : 'text-gray-400'" class="truncate">
              {{ filters.account_number || 'All Account Numbers' }}
            </span>
            <svg class="ml-1 h-3.5 w-3.5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
          <div
            v-if="accDropdownOpen"
            class="absolute left-0 z-50 mt-1 w-full rounded border border-gray-300 bg-white shadow-lg"
          >
            <div class="border-b border-gray-200 p-1.5">
              <input
                v-model="accSearch"
                type="text"
                placeholder="Search..."
                class="w-full rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 placeholder-gray-400 focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
              />
            </div>
            <ul class="max-h-[200px] overflow-y-auto py-1">
              <li>
                <button
                  type="button"
                  class="w-full px-2.5 py-1.5 text-left text-xs text-gray-500 hover:bg-green-50"
                  :class="{ 'bg-green-50 font-medium text-green-700': !filters.account_number }"
                  @click="clearAccount"
                >
                  All Account Numbers
                </button>
              </li>
              <li v-for="acc in filteredAccountNumbers" :key="acc">
                <button
                  type="button"
                  class="w-full px-2.5 py-1.5 text-left text-xs text-gray-700 hover:bg-green-50"
                  :class="{ 'bg-green-50 font-medium text-green-700': filters.account_number === acc }"
                  @click="selectAccount(acc)"
                >
                  {{ acc }}
                </button>
              </li>
              <li v-if="filteredAccountNumbers.length === 0" class="px-2.5 py-2 text-center text-xs text-gray-400">
                No matches found
              </li>
            </ul>
          </div>
        </div>
        <div v-if="showAlertTypeFilter" class="min-w-[130px] max-w-[180px] flex-1 lg:flex-none lg:w-[165px]">
          <label for="client-alert-type" class="block text-xs text-gray-700 mb-0.5">Alert Type</label>
          <select
            id="client-alert-type"
            v-model="filters.alert_type"
            class="w-full rounded border border-gray-300 bg-white px-2.5 py-1.5 text-xs text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          >
            <option value="__any__">All Alert Types</option>
            <option v-for="a in alertTypes" :key="a" :value="a">{{ a }}</option>
          </select>
        </div>
        <div
          class="flex w-full lg:w-auto flex-wrap lg:flex-nowrap items-center gap-2 lg:shrink-0"
          :class="'lg:ml-auto'"
        >
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
          <span class="inline-flex items-center gap-2 whitespace-nowrap" :class="compactActions ? 'ml-0 lg:ml-4' : ''">
            <slot name="customize-columns" />
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
