<script setup>
/**
 * Clients listing – search by company name / account number, filters, sort, customize columns, export.
 */
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import clientsApi from '@/services/clientsApi'
import ClientsFiltersBar from '@/components/clients/ClientsFiltersBar.vue'
import ColumnCustomizerModal from '@/components/lead-submissions/ColumnCustomizerModal.vue'
import ClientTable from '@/components/clients/ClientTable.vue'
import Pagination from '@/components/Pagination.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'

const router = useRouter()
const loading = ref(true)
const clients = ref([])
const meta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 })
const allColumns = ref([])
const defaultColumns = ref([])
const visibleColumns = ref([
  'company_name', 'submitted_at', 'manager', 'team_leader', 'sales_agent', 'status',
  'service_type', 'product_type', 'address', 'product_name', 'mrc', 'quantity',
  'other', 'migration_numbers', 'fiber', 'order_number', 'wo_number',
  'completion_date', 'payment_connection', 'contract_type', 'contract_end_date',
  'renewal_alert', 'additional_notes',
])
const sort = ref('submitted_at')
const order = ref('desc')
const columnModalVisible = ref(false)
const exportLoading = ref(false)
const importLoading = ref(false)
const importFileInputRef = ref(null)

const filters = ref({
  company_name: '',
  account_number: '',
  submitted_from: '',
  submitted_to: '',
})

function buildParams() {
  const f = filters.value
  const p = {
    page: meta.value.current_page,
    per_page: meta.value.per_page,
    sort: sort.value,
    order: order.value,
    columns: visibleColumns.value,
  }
  if (f.company_name) p.company_name = f.company_name
  if (f.account_number) p.account_number = f.account_number
  if (f.submitted_from) p.submitted_from = f.submitted_from
  if (f.submitted_to) p.submitted_to = f.submitted_to
  return p
}

const COLUMN_LABELS = {
  id: 'ID',
  company_name: 'Company Name',
  account_number: 'Account Number',
  submitted_at: 'Submission Date',
  manager: 'Manager Name',
  team_leader: 'Team Leader',
  sales_agent: 'Sales Agent Name',
  status: 'Status',
  service_type: 'Service Type',
  product_type: 'Product Type',
  address: 'Address',
  product_name: 'Product Name',
  mrc: 'MRC',
  quantity: 'Quantity',
  other: 'Other',
  migration_numbers: 'Migration Numbers',
  fiber: 'Fiber',
  order_number: 'ACL',
  wo_number: 'WIO',
  completion_date: 'Completion Date',
  payment_connection: 'Payment Connection',
  contract_type: 'Contract Type',
  contract_end_date: 'Contract End Date',
  renewal_alert: 'Renewal Alert',
  additional_notes: 'Additional Notes',
  creator: 'Created By',
}

function escapeCsv(val) {
  if (val == null) return ''
  const s = String(val)
  if (s.includes(',') || s.includes('"') || s.includes('\n')) return '"' + s.replace(/"/g, '""') + '"'
  return s
}

async function onExport() {
  const params = { ...buildParams(), page: 1, per_page: 100 }
  exportLoading.value = true
  try {
    const data = await clientsApi.index(params)
    const rows = data.data ?? []
    const cols = visibleColumns.value
    const headers = cols.map((c) => COLUMN_LABELS[c] ?? c)
    const csvRows = [headers.map(escapeCsv).join(',')]
    for (const row of rows) {
      csvRows.push(cols.map((col) => escapeCsv(row[col] ?? '')).join(','))
    }
    const blob = new Blob([csvRows.join('\r\n')], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `clients-${new Date().toISOString().slice(0, 10)}.csv`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    //
  } finally {
    exportLoading.value = false
  }
}

async function onImportFileSelect(event) {
  const file = event.target?.files?.[0]
  if (!file) return
  importLoading.value = true
  try {
    await clientsApi.importCsv(file)
    event.target.value = ''
    load()
  } catch (err) {
    const msg = err?.response?.data?.message ?? err?.message ?? 'Import failed.'
    alert(msg)
  } finally {
    importLoading.value = false
  }
}

async function load() {
  window.scrollTo(0, 0)
  loading.value = true
  try {
    const data = await clientsApi.index(buildParams())
    clients.value = data.data ?? []
    meta.value = data.meta ?? meta.value
  } finally {
    loading.value = false
    window.scrollTo(0, 0)
  }
}

async function loadColumns() {
  try {
    const data = await clientsApi.columns()
    allColumns.value = data.all_columns ?? []
    visibleColumns.value = data.visible_columns ?? visibleColumns.value
    defaultColumns.value = data.default_columns ?? []
  } catch {
    //
  }
}

function applyFilters() {
  meta.value.current_page = 1
  load()
}

function clearSearch() {
  filters.value.company_name = ''
  filters.value.account_number = ''
  meta.value.current_page = 1
  load()
}

function resetFilters() {
  filters.value = {
    company_name: '',
    account_number: '',
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

async function onSaveColumns(cols) {
  try {
    await clientsApi.saveColumns(cols)
    visibleColumns.value = cols
    meta.value.current_page = 1
    load()
  } catch {
    //
  }
}

function onPageChange(page) {
  meta.value.current_page = page
  load()
}

function goToAddClient() {
  router.push('/clients/create')
}

/** Effective columns for table: include id for row identity; API returns it in BASE_COLUMNS. */
const tableColumns = ref([])
function updateTableColumns() {
  const cols = visibleColumns.value
  tableColumns.value = cols.includes('id') ? cols : ['id', ...cols]
}

onMounted(() => {
  loadColumns().then(() => {
    updateTableColumns()
    load()
  })
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-white py-6 px-4 sm:px-6">
    <div class="mx-auto max-w-[1600px] space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-baseline gap-2">
          <h1 class="text-xl font-semibold text-gray-900 leading-tight">Clients</h1>
          <Breadcrumbs />
        </div>
        <div class="flex items-center gap-2">
          <input
            ref="importFileInputRef"
            type="file"
            accept=".csv"
            class="hidden"
            @change="onImportFileSelect"
          />
          <button
            type="button"
            class="inline-flex items-center rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
            :disabled="loading || importLoading"
            @click="importFileInputRef?.click()"
          >
            <svg v-if="importLoading" class="mr-1.5 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
            </svg>
            <svg v-else class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4 4m0 0l-4-4m4 4V8" />
            </svg>
            {{ importLoading ? 'Importing...' : 'Import CSV' }}
          </button>
          <button
            type="button"
            class="inline-flex items-center rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
            :disabled="loading || exportLoading"
            @click="onExport"
          >
            <svg v-if="exportLoading" class="mr-1.5 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
            </svg>
            <svg v-else class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            {{ exportLoading ? 'Exporting...' : 'Export' }}
          </button>
          <button
            type="button"
            class="inline-flex items-center rounded bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-70 disabled:cursor-wait"
            :disabled="loading"
            @click="goToAddClient"
          >
            <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add New Client
          </button>
        </div>
      </div>

      <p class="text-sm text-gray-500">Search for a client to view their profile.</p>

      <ClientsFiltersBar
        :filters="filters"
        :loading="loading"
        @search="applyFilters"
        @clear="clearSearch"
      >
        <template #customize-columns>
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
        </template>
      </ClientsFiltersBar>

      <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <ClientTable
          :columns="visibleColumns"
          :data="clients"
          :sort="sort"
          :order="order"
          :loading="loading"
          :current-page="meta.current_page"
          :per-page="meta.per_page"
          @sort="onSort"
        />
        <div
          class="flex flex-wrap items-center gap-4 border-t border-black bg-white px-4 py-3"
          :class="meta.last_page > 1 ? 'justify-between' : 'justify-start'"
        >
          <p class="text-sm text-gray-600">
            Showing {{ meta.total ? ((meta.current_page - 1) * meta.per_page) + 1 : 0 }} to {{ Math.min(meta.current_page * meta.per_page, meta.total) }} of {{ meta.total }} results
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
      :all-columns="allColumns"
      :visible-columns="visibleColumns"
      :default-columns="defaultColumns"
      @update:visible="columnModalVisible = $event"
      @save="onSaveColumns"
    />
  </div>
</template>
