<script setup>
/**
 * Order Status listing with focused filters/columns.
 */
import { ref, computed, onMounted } from 'vue'
import clientsApi from '@/services/clientsApi'
import OrderStatusTable from '@/components/order-status/OrderStatusTable.vue'
import ColumnCustomizerModal from '@/components/lead-submissions/ColumnCustomizerModal.vue'
import DateInputDdMmYyyy from '@/components/DateInputDdMmYyyy.vue'
import api from '@/lib/axios'
import { useAuthStore } from '@/stores/auth'
import { canModuleAction } from '@/lib/accessControl'

const auth = useAuthStore()
const canViewAction = () => canModuleAction(auth.user, 'order-status', 'view')
const TABLE_MODULE = 'order-status'
const COLUMN_MODULE = 'order-status-listing'
const CLIENTS_MAX_PER_PAGE = 50
const clampPerPage = (v) => Math.min(Math.max(Number(v) || 20, 1), CLIENTS_MAX_PER_PAGE)
const toPositiveInt = (value, fallback = 1) => {
  const n = Number(value)
  return Number.isFinite(n) && n > 0 ? Math.floor(n) : fallback
}
const perPageOptions = ref([10, 20, 25, 50])
const loading = ref(false)
const orders = ref([])
const meta = ref({ current_page: 1, last_page: 1, per_page: clampPerPage(auth.defaultTablePageSize || 25), total: 0 })
const sort = ref('submitted_at')
const order = ref('desc')
const columnModalVisible = ref(false)
const advancedFiltersOpen = ref(false)
const hasSearched = ref(false)

const defaultVisibleColumns = [
  'company_name',
  'activity',
  'account_number',
  'wo_number',
  'status',
  'submitted_at',
  'additional_notes',
]
const canonicalColumnOrder = [...defaultVisibleColumns]
const requiredOrderStatusColumns = new Set(['company_name', 'activity', 'account_number', 'wo_number', 'status', 'submitted_at', 'additional_notes'])

const allOrderStatusColumns = [
  { key: 'company_name', label: 'Company Name' },
  { key: 'activity', label: 'Activity' },
  { key: 'account_number', label: 'Account Number' },
  { key: 'wo_number', label: 'Work Order Number' },
  { key: 'status', label: 'Work Order Status' },
  { key: 'submitted_at', label: 'Activation Date' },
  { key: 'additional_notes', label: 'Additional Notes' },
]

const visibleColumns = ref([...defaultVisibleColumns])
const orderedVisibleColumns = computed(() => normalizeVisibleColumns(visibleColumns.value))

function normalizeVisibleColumns(cols) {
  const input = Array.isArray(cols) ? cols : []
  const allowed = new Set(allOrderStatusColumns.map((c) => c.key))
  const picked = new Set(input.filter((c) => allowed.has(c)))
  // Keep key order-status columns always present to avoid broken legacy preferences.
  requiredOrderStatusColumns.forEach((c) => picked.add(c))
  // Keep canonical order so Activity always follows Company Name.
  const ordered = canonicalColumnOrder.filter((c) => picked.has(c))
  return ordered.length ? ordered : [...defaultVisibleColumns]
}

const filters = ref({
  activity: '',
  company_name: '',
  account_number: '',
  wo_number: '',
  work_order_status: '',
  status: '',
  submission_type: '',
  service_category: '',
  service_type: '',
  product_type: '',
  product_name: '',
  payment_connection: '',
  contract_type: '',
  clawback_chum: '',
  company_category: '',
  trade_license_number: '',
  establishment_card_number: '',
  account_manager_name: '',
  csr_name_1: '',
  submitted_from: '',
  submitted_to: '',
  activation_from: '',
  activation_to: '',
  completion_from: '',
  completion_to: '',
  contract_end_from: '',
  contract_end_to: '',
  trade_license_expiry_from: '',
  trade_license_expiry_to: '',
  establishment_card_expiry_from: '',
  establishment_card_expiry_to: '',
})

// Keep only non-primary filters here; Activity + Account Number + Work Order stay in the general filter bar.
const advancedTextFilters = [
  { key: 'company_name', label: 'Company Name', placeholder: 'Company name...' },
  { key: 'work_order_status', label: 'Work Order Status', placeholder: 'Work order status...' },
  { key: 'status', label: 'Client Status', placeholder: 'Client status...' },
  { key: 'submission_type', label: 'Submission Type', placeholder: 'Submission type...' },
  { key: 'service_category', label: 'Service Category', placeholder: 'Service category...' },
  { key: 'service_type', label: 'Service Type', placeholder: 'Service type...' },
  { key: 'product_type', label: 'Product Type', placeholder: 'Product type...' },
  { key: 'product_name', label: 'Product Name', placeholder: 'Product name...' },
  { key: 'payment_connection', label: 'Payment Connection', placeholder: 'Payment connection...' },
  { key: 'contract_type', label: 'Contract Type', placeholder: 'Contract type...' },
  { key: 'clawback_chum', label: 'Clawback CHUM', placeholder: 'Clawback CHUM...' },
  { key: 'company_category', label: 'Company Category', placeholder: 'Company category...' },
  { key: 'trade_license_number', label: 'Trade License Number', placeholder: 'Trade license number...' },
  { key: 'establishment_card_number', label: 'Establishment Card Number', placeholder: 'Establishment card number...' },
  { key: 'account_manager_name', label: 'Account Manager Name', placeholder: 'Account manager name...' },
  { key: 'csr_name_1', label: 'CSR Name 1', placeholder: 'CSR name...' },
]

const advancedDateFilters = [
  { from: 'submitted_from', to: 'submitted_to', label: 'Submitted Date' },
  { from: 'activation_from', to: 'activation_to', label: 'Activation Date' },
  { from: 'completion_from', to: 'completion_to', label: 'Completion Date' },
  { from: 'contract_end_from', to: 'contract_end_to', label: 'Contract End Date' },
  { from: 'trade_license_expiry_from', to: 'trade_license_expiry_to', label: 'Trade License Expiry' },
  { from: 'establishment_card_expiry_from', to: 'establishment_card_expiry_to', label: 'Establishment Card Expiry' },
]

const hasAnyFilter = computed(() => {
  const f = filters.value
  return Boolean(
    f.activity ||
    f.company_name ||
    f.account_number ||
    f.wo_number ||
    f.work_order_status ||
    f.status ||
    f.submission_type ||
    f.service_category ||
    f.service_type ||
    f.product_type ||
    f.product_name ||
    f.payment_connection ||
    f.contract_type ||
    f.clawback_chum ||
    f.company_category ||
    f.trade_license_number ||
    f.establishment_card_number ||
    f.account_manager_name ||
    f.csr_name_1 ||
    f.submitted_from || f.submitted_to ||
    f.activation_from || f.activation_to ||
    f.completion_from || f.completion_to ||
    f.contract_end_from || f.contract_end_to ||
    f.trade_license_expiry_from || f.trade_license_expiry_to ||
    f.establishment_card_expiry_from || f.establishment_card_expiry_to
  )
})

function normalizeMeta(incoming) {
  const current = meta.value || {}
  const next = incoming || {}
  return {
    current_page: toPositiveInt(next.current_page, toPositiveInt(current.current_page, 1)),
    last_page: toPositiveInt(next.last_page, toPositiveInt(current.last_page, 1)),
    per_page: clampPerPage(next.per_page ?? current.per_page ?? 25),
    total: toPositiveInt(next.total, toPositiveInt(current.total, 0)),
  }
}

function buildParams() {
  const f = filters.value
  const cols = orderedVisibleColumns.value.filter((c) => c !== 'activity' && c !== 'id')
  const columns = ['id', ...cols]
  if (!columns.includes('submitted_at')) columns.push('submitted_at')

  const p = {
    page: meta.value.current_page,
    per_page: clampPerPage(meta.value.per_page),
    sort: sort.value,
    order: order.value,
    columns,
  }
  if (f.activity) p.activity = f.activity
  if (f.company_name) p.company_name = f.company_name
  if (f.account_number) p.account_number = f.account_number
  if (f.wo_number) p.wo_number = f.wo_number
  if (f.work_order_status) p.work_order_status = f.work_order_status
  if (f.status) p.status = f.status
  if (f.submission_type) p.submission_type = f.submission_type
  if (f.service_category) p.service_category = f.service_category
  if (f.service_type) p.service_type = f.service_type
  if (f.product_type) p.product_type = f.product_type
  if (f.product_name) p.product_name = f.product_name
  if (f.payment_connection) p.payment_connection = f.payment_connection
  if (f.contract_type) p.contract_type = f.contract_type
  if (f.clawback_chum) p.clawback_chum = f.clawback_chum
  if (f.company_category) p.company_category = f.company_category
  if (f.trade_license_number) p.trade_license_number = f.trade_license_number
  if (f.establishment_card_number) p.establishment_card_number = f.establishment_card_number
  if (f.account_manager_name) p.account_manager_name = f.account_manager_name
  if (f.csr_name_1) p.csr_name_1 = f.csr_name_1
  if (f.submitted_from) p.submitted_from = f.submitted_from
  if (f.submitted_to) p.submitted_to = f.submitted_to
  if (f.activation_from) p.activation_from = f.activation_from
  if (f.activation_to) p.activation_to = f.activation_to
  if (f.completion_from) p.completion_from = f.completion_from
  if (f.completion_to) p.completion_to = f.completion_to
  if (f.contract_end_from) p.contract_end_from = f.contract_end_from
  if (f.contract_end_to) p.contract_end_to = f.contract_end_to
  if (f.trade_license_expiry_from) p.trade_license_expiry_from = f.trade_license_expiry_from
  if (f.trade_license_expiry_to) p.trade_license_expiry_to = f.trade_license_expiry_to
  if (f.establishment_card_expiry_from) p.establishment_card_expiry_from = f.establishment_card_expiry_from
  if (f.establishment_card_expiry_to) p.establishment_card_expiry_to = f.establishment_card_expiry_to
  return p
}

async function load() {
  loading.value = true
  try {
    const data = await clientsApi.index(buildParams())
    orders.value = data.data ?? []
    meta.value = normalizeMeta(data.meta)
  } finally {
    loading.value = false
  }
}

function applyFilters() {
  if (!hasAnyFilter.value) {
    hasSearched.value = false
    orders.value = []
    meta.value = { ...meta.value, current_page: 1, last_page: 1, total: 0 }
    return
  }
  hasSearched.value = true
  meta.value.current_page = 1
  load()
}

function resetFilters() {
  filters.value = {
    activity: '',
    company_name: '',
    account_number: '',
    wo_number: '',
    work_order_status: '',
    status: '',
    submission_type: '',
    service_category: '',
    service_type: '',
    product_type: '',
    product_name: '',
    payment_connection: '',
    contract_type: '',
    clawback_chum: '',
    company_category: '',
    trade_license_number: '',
    establishment_card_number: '',
    account_manager_name: '',
    csr_name_1: '',
    submitted_from: '',
    submitted_to: '',
    activation_from: '',
    activation_to: '',
    completion_from: '',
    completion_to: '',
    contract_end_from: '',
    contract_end_to: '',
    trade_license_expiry_from: '',
    trade_license_expiry_to: '',
    establishment_card_expiry_from: '',
    establishment_card_expiry_to: '',
  }
  hasSearched.value = false
  orders.value = []
  meta.value = { ...meta.value, current_page: 1, last_page: 1, total: 0 }
  meta.value.current_page = 1
}

function onSort({ sort: s, order: o }) {
  if (!hasSearched.value) return
  sort.value = s
  order.value = o
  meta.value.current_page = 1
  load()
}

async function onSaveColumns(cols) {
  await saveColumns(cols)
}

function onPageChange(page) {
  if (!hasSearched.value) return
  meta.value.current_page = toPositiveInt(page, 1)
  load()
}

async function onPerPageChange(e) {
  const val = clampPerPage(e.target.value)
  meta.value.per_page = val
  meta.value.current_page = 1
  if (hasSearched.value) load()
  try { await api.post(`/table-preferences/${TABLE_MODULE}`, { per_page: val }) } catch { /* silent */ }
}

async function loadTablePreference() {
  try {
    const { data } = await api.get(`/table-preferences/${TABLE_MODULE}`)
    if (data.per_page) meta.value.per_page = clampPerPage(data.per_page)
    if (Array.isArray(data.options) && data.options.length) {
      perPageOptions.value = [...new Set(data.options.map(clampPerPage))].filter((v) => v <= CLIENTS_MAX_PER_PAGE)
    }
  } catch { /* use system default */ }
}

async function loadColumns() {
  try {
    const { data } = await api.get('/clients/columns', { params: { module: COLUMN_MODULE } })
    const cols = normalizeVisibleColumns(data?.visible_columns)
    if (cols.length) {
      visibleColumns.value = cols
      return
    }
    // Backward-compatible fallback for previously saved module keys.
    const { data: legacy } = await api.get('/clients/columns', { params: { module: TABLE_MODULE } })
    const legacyCols = normalizeVisibleColumns(legacy?.visible_columns)
    if (legacyCols.length) {
      visibleColumns.value = legacyCols
    }
  } catch { /* silent */ }
}

async function saveColumns(cols) {
  try {
    const normalized = normalizeVisibleColumns(cols)
    await api.post('/clients/columns', { module: COLUMN_MODULE, visible_columns: normalized })
    visibleColumns.value = normalized
    meta.value.current_page = 1
    if (hasSearched.value) load()
  } catch { /* silent */ }
}

onMounted(async () => {
  await loadTablePreference()
  await loadColumns()
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-white py-6 px-4 sm:px-6">
    <div class="mx-auto max-w-[1600px] space-y-4">
      <!-- Filters card: Activity, Account Number, Work Order + Search, Clear, Advanced Filters, Customize Columns -->
      <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-end gap-3">
          <div class="w-full sm:min-w-[140px] sm:max-w-[200px] sm:flex-1">
            <label for="os-activity" class="mb-0.5 block text-xs text-gray-700">Activity</label>
            <input
              id="os-activity"
              v-model="filters.activity"
              type="text"
              placeholder="Activity..."
              class="w-full rounded border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-700 placeholder-gray-400 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
              :disabled="loading"
              @keyup.enter="applyFilters"
            />
          </div>
          <div class="w-full sm:min-w-[140px] sm:max-w-[200px] sm:flex-1">
            <label for="os-account" class="mb-0.5 block text-xs text-gray-700">Account Number</label>
            <input
              id="os-account"
              v-model="filters.account_number"
              type="text"
              placeholder="Account number..."
              class="w-full rounded border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-700 placeholder-gray-400 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
              :disabled="loading"
              @keyup.enter="applyFilters"
            />
          </div>
          <div class="w-full sm:min-w-[140px] sm:max-w-[220px] sm:flex-1">
            <label for="os-wo-number" class="mb-0.5 block text-xs text-gray-700">Work Order Number</label>
            <input
              id="os-wo-number"
              v-model="filters.wo_number"
              type="text"
              placeholder="Work order number..."
              class="w-full rounded border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-700 placeholder-gray-400 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
              :disabled="loading"
              @keyup.enter="applyFilters"
            />
          </div>
          <div class="flex flex-wrap items-center gap-2 sm:ml-auto">
            <button
              type="button"
              class="inline-flex items-center rounded bg-brand-primary px-4 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover focus:ring-2 focus:ring-brand-primary disabled:opacity-50"
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
            <div class="flex flex-wrap items-center gap-2">
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
              @click="advancedFiltersOpen = !advancedFiltersOpen"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
              Advanced Filters
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
              @click="columnModalVisible = true"
            >
              Customize Columns
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            </div>
          </div>
        </div>

        <!-- Advanced filters panel (all API-supported order status filters) -->
        <div v-show="advancedFiltersOpen" class="mt-4 border-t border-gray-200 pt-4">
          <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <div v-for="f in advancedTextFilters" :key="f.key">
              <label :for="`os-${f.key}`" class="mb-0.5 block text-xs text-gray-700">{{ f.label }}</label>
              <input
                :id="`os-${f.key}`"
                v-model="filters[f.key]"
                type="text"
                :placeholder="f.placeholder"
                class="w-full rounded border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-700 placeholder-gray-400 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
                :disabled="loading"
                @keyup.enter="applyFilters"
              />
            </div>

            <template v-for="d in advancedDateFilters" :key="d.from">
              <div>
                <label :for="`os-${d.from}`" class="mb-0.5 block text-xs text-gray-700">{{ d.label }} From</label>
                <DateInputDdMmYyyy
                  v-model="filters[d.from]"
                  placeholder="DD-MMM-YYYY"
                  :disabled="loading"
                />
              </div>
              <div>
                <label :for="`os-${d.to}`" class="mb-0.5 block text-xs text-gray-700">{{ d.label }} To</label>
                <DateInputDdMmYyyy
                  v-model="filters[d.to]"
                  placeholder="DD-MMM-YYYY"
                  :disabled="loading"
                />
              </div>
            </template>
          </div>
        </div>
      </div>

      <!-- Initial state (first visit / cleared filters) -->
      <div v-if="!hasSearched" class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-10 text-center text-sm text-gray-600">
        You can view order status using filters.
      </div>

      <!-- Table -->
      <div v-else class="overflow-hidden rounded-lg border-2 border-black bg-white shadow-sm">
        <OrderStatusTable
          :columns="orderedVisibleColumns.filter((c) => c !== 'id' && c !== 'fiber' && c !== 'order_number')"
          :data="orders"
          :sort="sort"
          :order="order"
          :loading="loading"
          :current-page="meta.current_page"
          :per-page="meta.per_page"
          :can-view-action="canViewAction()"
          @sort="onSort"
        />
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-black bg-white px-4 py-3">
          <p class="text-sm text-gray-600">
            Showing {{ meta.total ? ((meta.current_page - 1) * meta.per_page) + 1 : 0 }}
            to {{ Math.min(meta.current_page * meta.per_page, meta.total) }}
            of {{ meta.total }} entries
          </p>
          <div class="flex items-center gap-4">
            <div class="flex items-center gap-2 text-sm text-gray-600">
              <span class="whitespace-nowrap font-medium">Number of rows</span>
              <select
                :value="meta.per_page"
                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm min-w-[80px] text-gray-700 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
                @change="onPerPageChange"
              >
                <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
              </select>
            </div>
            <div class="flex items-center gap-1.5">
              <button type="button" :disabled="meta.current_page <= 1" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50" @click="onPageChange(meta.current_page - 1)">Previous</button>
              <span class="rounded-md border border-gray-300 bg-gray-50 px-3 py-1.5 text-sm text-gray-700">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
              <button type="button" :disabled="meta.current_page >= meta.last_page" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50" @click="onPageChange(meta.current_page + 1)">Next</button>
            </div>
          </div>
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
