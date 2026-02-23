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
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import RecordHistoryModal from '@/components/RecordHistoryModal.vue'
import api from '@/lib/axios'
import { useAuthStore } from '@/stores/auth'

const historyModalVisible = ref(false)
const historyRecordId = ref(null)
const historyRecordLabel = ref('')
function openHistoryModal(row) {
  if (!row?.id) return
  historyRecordId.value = row.id
  historyRecordLabel.value = row.company_name || `Client #${row.id}`
  historyModalVisible.value = true
}
function closeHistoryModal() {
  historyModalVisible.value = false
  historyRecordId.value = null
  historyRecordLabel.value = ''
}
async function fetchClientAudits(id) {
  return await clientsApi.audits(id)
}

const router = useRouter()
const authStore = useAuthStore()
const loading = ref(true)
const clients = ref([])
const TABLE_MODULE = 'clients'
const perPageOptions = ref([10, 20, 25, 50, 100])
const meta = ref({ current_page: 1, last_page: 1, per_page: authStore.defaultTablePageSize || 25, total: 0 })
const allColumns = ref([])
const defaultColumns = ref([])
const visibleColumns = ref([
  'company_name', 'submitted_at', 'manager', 'team_leader', 'sales_agent',
  'service_type', 'product_type', 'address', 'product_name', 'mrc', 'quantity',
  'other', 'migration_numbers', 'activity', 'account_number', 'wo_number', 'status',
  'completion_date', 'contract_type', 'contract_end_date',
  'renewal_alert', 'additional_notes', 'creator',
])
const sort = ref('submitted_at')
const order = ref('desc')
const columnModalVisible = ref(false)
const exportLoading = ref(false)
const importLoading = ref(false)
const importFileInputRef = ref(null)

const accountNumbers = ref([])
const filterOptions = ref({ managers: [], team_leaders: [], sales_agents: [] })

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
  wo_number: 'Work Order',
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
    a.download = `products-services-${new Date().toISOString().slice(0, 10)}.csv`
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

async function loadFilters() {
  try {
    const data = await clientsApi.filters()
    accountNumbers.value = data.account_numbers ?? []
    filterOptions.value = {
      managers: data.managers ?? [],
      team_leaders: data.team_leaders ?? [],
      sales_agents: data.sales_agents ?? [],
    }
  } catch { /* silent */ }
}

const COLUMN_ORDER = [
  'company_name', 'submitted_at', 'manager', 'team_leader', 'sales_agent',
  'service_type', 'product_type', 'address', 'product_name', 'mrc', 'quantity', 'other',
  'migration_numbers', 'activity', 'account_number', 'wo_number', 'status', 'completion_date',
  'contract_type', 'contract_end_date',
  'renewal_alert', 'additional_notes', 'creator',
]

function enforceColumnOrder(cols) {
  const set = new Set(cols)
  if (!set.has('activity')) set.add('activity')
  const ordered = COLUMN_ORDER.filter((c) => set.has(c))
  const extra = [...set].filter((c) => !COLUMN_ORDER.includes(c))
  return [...ordered, ...extra]
}

async function loadColumns() {
  try {
    const data = await clientsApi.columns()
    allColumns.value = data.all_columns ?? []
    visibleColumns.value = enforceColumnOrder(data.visible_columns ?? visibleColumns.value)
    defaultColumns.value = data.default_columns ?? []
    updateTableColumns()
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
    visibleColumns.value = enforceColumnOrder(cols)
    updateTableColumns()
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

async function onPerPageChange(event) {
  const newPerPage = Number(event.target.value)
  meta.value.per_page = newPerPage
  meta.value.current_page = 1
  try {
    await api.post(`/table-preferences/${TABLE_MODULE}`, { per_page: newPerPage })
  } catch { /* silent */ }
  load()
}

async function loadTablePreference() {
  try {
    const { data } = await api.get(`/table-preferences/${TABLE_MODULE}`)
    if (data.per_page) meta.value.per_page = Number(data.per_page)
    if (Array.isArray(data.options) && data.options.length) perPageOptions.value = data.options
  } catch { /* use system default */ }
}

function goToAddClient() {
  router.push('/clients/create')
}

async function onUpdateCell(clientId, field, value) {
  const row = clients.value.find((r) => r.id === clientId)
  const prev = row ? { ...row } : null
  if (row) {
    if (field === 'manager_id' && value != null) {
      row.manager_id = value
      row.manager = filterOptions.value.managers.find((u) => u.id === Number(value))?.name ?? row.manager
    } else if (field === 'team_leader_id' && value != null) {
      row.team_leader_id = value
      row.team_leader = filterOptions.value.team_leaders.find((u) => u.id === Number(value))?.name ?? row.team_leader
    } else if (field === 'sales_agent_id' && value != null) {
      row.sales_agent_id = value
      row.sales_agent = filterOptions.value.sales_agents.find((u) => u.id === Number(value))?.name ?? row.sales_agent
    } else {
      row[field] = value
    }
  }
  try {
    await clientsApi.inlineUpdate(clientId, { [field]: value })
  } catch {
    if (prev) Object.assign(row, prev)
    load()
  }
}

const tableColumns = ref([])
function updateTableColumns() {
  tableColumns.value = visibleColumns.value.filter((c) => c !== 'id' && c !== 'fiber' && c !== 'order_number')
}

onMounted(() => {
  loadFilters()
  loadTablePreference().then(() => {
    loadColumns().then(() => {
      updateTableColumns()
      load()
    })
  })
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-white p-4">
    <div class="w-full space-y-4">
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
        :account-numbers="accountNumbers"
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

      <div class="overflow-hidden rounded-lg border-2 border-black bg-white shadow-sm">
        <ClientTable
          :columns="tableColumns"
          :data="clients"
          :sort="sort"
          :order="order"
          :loading="loading"
          :current-page="meta.current_page"
          :per-page="meta.per_page"
          :edit-options="filterOptions"
          @sort="onSort"
          @update-cell="onUpdateCell"
          @view-history="openHistoryModal"
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
                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm min-w-[80px] text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
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
      :all-columns="allColumns"
      :visible-columns="visibleColumns"
      :default-columns="defaultColumns"
      @update:visible="columnModalVisible = $event"
      @save="onSaveColumns"
    />

    <RecordHistoryModal
      :visible="historyModalVisible"
      :record-id="historyRecordId"
      :record-label="historyRecordLabel"
      module-name="Clients"
      :fetch-fn="fetchClientAudits"
      @close="closeHistoryModal"
    />
  </div>
</template>
