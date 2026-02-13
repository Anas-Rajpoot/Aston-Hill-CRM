<script setup>
/**
 * Order Status – list product/service data from clients. Title + breadcrumbs, filters, advanced filters, sortable table, column customizer.
 */
import { ref, onMounted } from 'vue'
import clientsApi from '@/services/clientsApi'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import OrderStatusTable from '@/components/order-status/OrderStatusTable.vue'
import ColumnCustomizerModal from '@/components/lead-submissions/ColumnCustomizerModal.vue'
import Pagination from '@/components/Pagination.vue'

const loading = ref(true)
const orders = ref([])
const meta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 })
const sort = ref('submitted_at')
const order = ref('desc')
const columnModalVisible = ref(false)
const advancedFiltersOpen = ref(false)

const defaultVisibleColumns = [
  'activity',
  'account_number',
  'wo_number',
  'status',
  'submitted_at',
  'additional_notes',
]

const allOrderStatusColumns = [
  { key: 'activity', label: 'Activity' },
  { key: 'id', label: 'ID' },
  { key: 'company_name', label: 'Company Name' },
  { key: 'account_number', label: 'Account Number' },
  { key: 'submitted_at', label: 'Activation Date' },
  { key: 'manager', label: 'Manager Name' },
  { key: 'team_leader', label: 'Team Leader' },
  { key: 'sales_agent', label: 'Sales Agent Name' },
  { key: 'status', label: 'Work Order Status' },
  { key: 'service_type', label: 'Service Type' },
  { key: 'product_type', label: 'Product Type' },
  { key: 'address', label: 'Address' },
  { key: 'product_name', label: 'Product Name' },
  { key: 'mrc', label: 'MRC' },
  { key: 'quantity', label: 'Quantity' },
  { key: 'other', label: 'Activity Notes' },
  { key: 'migration_numbers', label: 'Migration Numbers' },
  { key: 'fiber', label: 'Fiber' },
  { key: 'order_number', label: 'ACL' },
  { key: 'wo_number', label: 'Work Order' },
  { key: 'completion_date', label: 'Completion Date' },
  { key: 'payment_connection', label: 'Payment Connection' },
  { key: 'contract_type', label: 'Contract Type' },
  { key: 'contract_end_date', label: 'Contract End Date' },
  { key: 'renewal_alert', label: 'Renewal Alert' },
  { key: 'additional_notes', label: 'Remarks' },
  { key: 'creator', label: 'Created By' },
]

const visibleColumns = ref([...defaultVisibleColumns])

const filters = ref({
  company_name: '',
  account_number: '',
  wo_number: '',
  status: '',
  submitted_from: '',
  submitted_to: '',
})

function buildParams() {
  const f = filters.value
  const cols = visibleColumns.value.filter((c) => c !== 'activity')
  const columns = cols.includes('id') ? cols : ['id', ...cols]
  if (!columns.includes('submitted_at')) columns.push('submitted_at')

  const p = {
    page: meta.value.current_page,
    per_page: meta.value.per_page,
    sort: sort.value,
    order: order.value,
    columns,
  }
  if (f.company_name) p.company_name = f.company_name
  if (f.account_number) p.account_number = f.account_number
  if (f.wo_number) p.wo_number = f.wo_number
  if (f.status) p.status = f.status
  if (f.submitted_from) p.submitted_from = f.submitted_from
  if (f.submitted_to) p.submitted_to = f.submitted_to
  return p
}

async function load() {
  loading.value = true
  try {
    const data = await clientsApi.index(buildParams())
    orders.value = data.data ?? []
    meta.value = data.meta ?? meta.value
  } finally {
    loading.value = false
  }
}

function applyFilters() {
  meta.value.current_page = 1
  load()
}

function resetFilters() {
  filters.value = {
    company_name: '',
    account_number: '',
    wo_number: '',
    status: '',
    submitted_from: '',
    submitted_to: '',
  }
  meta.value.current_page = 1
  load()
}

function onSort({ sort: s, order: o }) {
  sort.value = s
  order.value = o
  meta.value.current_page = 1
  load()
}

function onSaveColumns(cols) {
  visibleColumns.value = cols
  meta.value.current_page = 1
  load()
}

function onPageChange(page) {
  meta.value.current_page = page
  load()
}

onMounted(() => {
  load()
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-white py-6 px-4 sm:px-6">
    <div class="mx-auto max-w-[1600px] space-y-4">
      <!-- Heading first, then breadcrumb -->
      <div class="flex flex-wrap items-center gap-1">
        <div class="flex items-center gap-3">
          <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#B8E6D5]">
            <svg class="h-6 w-6 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
          </span>
          <div>
            <h1 class="text-2xl font-bold text-gray-900 leading-tight">Order Status</h1>
            <p class="mt-0.5 text-sm text-gray-500">Track and monitor order status by Activity, Account Number, or Work Order.</p>
          </div>
        </div>
        <Breadcrumbs />
      </div>

      <!-- Filters card: Activity, Account Number, Work Order + Search, Clear, Advanced Filters, Customize Columns -->
      <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <h3 class="mb-3 text-sm font-medium text-gray-900">Filters</h3>
        <div class="flex flex-wrap items-end gap-4">
          <div class="min-w-[140px] max-w-[200px] flex-1">
            <label for="os-activity" class="mb-0.5 block text-xs text-gray-700">Activity</label>
            <input
              id="os-activity"
              v-model="filters.company_name"
              type="text"
              placeholder="Activity..."
              class="w-full rounded border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-700 placeholder-gray-400 focus:border-green-500 focus:ring-1 focus:ring-green-500"
              :disabled="loading"
              @keyup.enter="applyFilters"
            />
          </div>
          <div class="min-w-[140px] max-w-[200px] flex-1">
            <label for="os-account" class="mb-0.5 block text-xs text-gray-700">Account Number</label>
            <input
              id="os-account"
              v-model="filters.account_number"
              type="text"
              placeholder="Account number..."
              class="w-full rounded border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-700 placeholder-gray-400 focus:border-green-500 focus:ring-1 focus:ring-green-500"
              :disabled="loading"
              @keyup.enter="applyFilters"
            />
          </div>
          <div class="min-w-[140px] max-w-[200px] flex-1">
            <label for="os-wo" class="mb-0.5 block text-xs text-gray-700">Work Order</label>
            <input
              id="os-wo"
              v-model="filters.wo_number"
              type="text"
              placeholder="Work order..."
              class="w-full rounded border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-700 placeholder-gray-400 focus:border-green-500 focus:ring-1 focus:ring-green-500"
              :disabled="loading"
              @keyup.enter="applyFilters"
            />
          </div>
          <div class="flex flex-1 flex-wrap items-center gap-2">
            <button
              type="button"
              class="inline-flex items-center rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 focus:ring-2 focus:ring-green-500 disabled:opacity-50"
              :disabled="loading"
              @click="applyFilters"
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
              @click="resetFilters"
            >
              Clear
            </button>
            <button
              type="button"
              class="inline-flex items-center rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
              :class="advancedFiltersOpen && 'ring-1 ring-green-500 border-green-500'"
              @click="advancedFiltersOpen = !advancedFiltersOpen"
            >
              <svg class="mr-1.5 h-4 w-4 transition-transform" :class="advancedFiltersOpen && 'rotate-90'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
              </svg>
              Advanced Filters
            </button>
            <button
              type="button"
              class="inline-flex items-center rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
              @click="columnModalVisible = true"
            >
              Customize Columns
              <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Advanced filters panel (Work Order Status, Activation Date From, Activation Date To) -->
        <div v-show="advancedFiltersOpen" class="mt-4 border-t border-gray-200 pt-4">
          <div class="flex flex-wrap items-end gap-4">
            <div class="min-w-[140px] max-w-[200px]">
              <label for="os-status" class="mb-0.5 block text-xs text-gray-700">Work Order Status</label>
              <select
                id="os-status"
                v-model="filters.status"
                class="w-full rounded border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
                :disabled="loading"
              >
                <option value="">All</option>
                <option value="pending">Pending</option>
                <option value="in_progress">In Progress</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
            <div class="min-w-[140px] max-w-[200px]">
              <label for="os-submitted-from" class="mb-0.5 block text-xs text-gray-700">Activation Date From</label>
              <input
                id="os-submitted-from"
                v-model="filters.submitted_from"
                type="date"
                class="w-full rounded border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
                :disabled="loading"
              />
            </div>
            <div class="min-w-[140px] max-w-[200px]">
              <label for="os-submitted-to" class="mb-0.5 block text-xs text-gray-700">Activation Date To</label>
              <input
                id="os-submitted-to"
                v-model="filters.submitted_to"
                type="date"
                class="w-full rounded border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
                :disabled="loading"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <OrderStatusTable
          :columns="visibleColumns"
          :data="orders"
          :sort="sort"
          :order="order"
          :loading="loading"
          @sort="onSort"
        />
        <div
          class="flex flex-wrap items-center gap-4 border-t border-gray-200 bg-white px-4 py-3"
          :class="meta.last_page > 1 ? 'justify-between' : 'justify-start'"
        >
          <p class="text-sm text-gray-600">
            Showing {{ meta.total ? (meta.current_page - 1) * meta.per_page + 1 : 0 }} to
            {{ Math.min(meta.current_page * meta.per_page, meta.total) }} of {{ meta.total }} entries
          </p>
          <Pagination
            v-if="meta.last_page > 1"
            :meta="{
              prev_page_url: meta.current_page > 1 ? '#' : null,
              next_page_url: meta.current_page < meta.last_page ? '#' : null,
              current_page: meta.current_page,
              last_page: meta.last_page,
            }"
            @change="onPageChange"
          />
        </div>
      </div>
    </div>

    <ColumnCustomizerModal
      :visible="columnModalVisible"
      :all-columns="allOrderStatusColumns"
      :visible-columns="visibleColumns"
      :default-columns="defaultVisibleColumns"
      @update:visible="columnModalVisible = $event"
      @save="onSaveColumns"
    />
  </div>
</template>
