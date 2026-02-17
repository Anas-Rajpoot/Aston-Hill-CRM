<script setup>
/**
 * Lead Reports – KPIs, filters, charts (by status, by category, monthly trend),
 * sortable/customizable lead submissions table with pagination and CSV export.
 * Super admins see all leads; other users see leads they created or are assigned to.
 */
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/lib/axios'
import leadSubmissionsApi from '@/services/leadSubmissionsApi'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import ColumnCustomizerModal from '@/components/lead-submissions/ColumnCustomizerModal.vue'
import { toDdMonYyyyLower } from '@/lib/dateFormat'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const authStore = useAuthStore()
const TABLE_MODULE = 'lead-reports'
const perPageOptions = ref([10, 20, 25, 50, 100])

/* ───── Loading states ───── */
const loading = ref(true)
const statsLoading = ref(true)
const tableLoading = ref(false)
const exportLoading = ref(false)

/* ───── KPI / chart state ───── */
const stats = ref({
  total_leads: 0,
  new_submissions: 0,
  resubmissions: 0,
  sla_compliance_pct: 0,
  avg_processing_days: 0,
  by_status: {},
  by_category: [],
  monthly_trend: [],
})

/* ───── Filter state ───── */
const filterOptions = ref({ categories: [], statuses: [] })
const filters = ref({
  from: '',
  to: '',
  submitted_from: '',
  submitted_to: '',
  service_category_id: '',
  status: '',
  sales_agent_id: '',
  team_leader_id: '',
})
const advancedVisible = ref(false)

/* ───── Table data ───── */
const tableData = ref([])
const tableMeta = ref({ current_page: 1, last_page: 1, per_page: authStore.defaultTablePageSize || 25, total: 0 })
const sort = ref('submitted_at')
const order = ref('desc')

/* ───── Column customization ───── */
const columnModalVisible = ref(false)
const allColumns = ref([])
const visibleColumns = ref([
  'submitted_at', 'company_name', 'category', 'status', 'sla_timer',
  'sales_agent', 'executive',
])
const defaultVisibleColumns = [
  'submitted_at', 'company_name', 'category', 'status', 'sla_timer',
  'sales_agent', 'executive',
]

const COLUMN_LABELS = {
  id: 'ID',
  submitted_at: 'Submission Date',
  updated_at: 'Updated',
  submission_type: 'Request Type',
  account_number: 'Account Number',
  company_name: 'Company Name',
  authorized_signatory_name: 'Authorized Signatory',
  email: 'Email',
  contact_number_gsm: 'Contact (GSM)',
  alternate_contact_number: 'Alternate Contact',
  address: 'Address',
  emirate: 'Emirate',
  location_coordinates: 'Location Coordinates',
  category: 'Service Category',
  type: 'Service Type',
  product: 'Product',
  offer: 'Offer',
  mrc_aed: 'MRC (AED)',
  quantity: 'Qty',
  ae_domain: 'AE Domain',
  gaid: 'GAID',
  remarks: 'Remarks',
  sales_agent: 'Sales Agent',
  team_leader: 'Team Leader',
  manager: 'Manager',
  status: 'Status',
  sla_timer: 'SLA Status',
  executive: 'BO Executive',
  status_changed_at: 'Last Updated',
  creator: 'Created By',
}

/* ───── Computed helpers ───── */
const activeColumns = computed(() =>
  visibleColumns.value
    .filter((key) => key !== 'id')
    .map((key) => ({
      key,
      label: COLUMN_LABELS[key] || key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()),
    }))
)

const activeFilterCount = computed(() => {
  let count = 0
  if (filters.value.from) count++
  if (filters.value.to) count++
  if (filters.value.submitted_from) count++
  if (filters.value.submitted_to) count++
  if (filters.value.sales_agent_id) count++
  if (filters.value.team_leader_id) count++
  return count
})

const params = computed(() => {
  const p = {
    page: tableMeta.value.current_page,
    per_page: tableMeta.value.per_page,
    sort: sort.value,
    order: order.value,
    columns: visibleColumns.value,
  }
  if (filters.value.from) p.from = filters.value.from
  if (filters.value.to) p.to = filters.value.to
  if (filters.value.submitted_from) p.submitted_from = filters.value.submitted_from
  if (filters.value.submitted_to) p.submitted_to = filters.value.submitted_to
  if (filters.value.service_category_id) p.service_category_id = filters.value.service_category_id
  if (filters.value.status) p.status = filters.value.status
  if (filters.value.sales_agent_id) p.sales_agent_id = filters.value.sales_agent_id
  if (filters.value.team_leader_id) p.team_leader_id = filters.value.team_leader_id
  return p
})

function filterParams() {
  const p = {}
  if (filters.value.from) p.from = filters.value.from
  if (filters.value.to) p.to = filters.value.to
  if (filters.value.submitted_from) p.submitted_from = filters.value.submitted_from
  if (filters.value.submitted_to) p.submitted_to = filters.value.submitted_to
  if (filters.value.service_category_id) p.service_category_id = filters.value.service_category_id
  if (filters.value.status) p.status = filters.value.status
  if (filters.value.sales_agent_id) p.sales_agent_id = filters.value.sales_agent_id
  if (filters.value.team_leader_id) p.team_leader_id = filters.value.team_leader_id
  return p
}

/* ───── Data loading ───── */
async function loadFilterOptions() {
  try {
    const { data } = await api.get('/lead-submissions/filters')
    filterOptions.value.categories = data.categories ?? []
    filterOptions.value.statuses = data.statuses ?? []
  } catch {
    filterOptions.value = { categories: [], statuses: [] }
  }
}

async function loadStats() {
  statsLoading.value = true
  try {
    const { data } = await api.get('/reports/lead-stats', { params: filterParams() })
    stats.value = data
  } catch {
    stats.value = {
      total_leads: 0, new_submissions: 0, resubmissions: 0,
      sla_compliance_pct: 0, avg_processing_days: 0,
      by_status: {}, by_category: [], monthly_trend: [],
    }
  } finally {
    statsLoading.value = false
  }
}

async function loadTable() {
  tableLoading.value = true
  try {
    const data = await leadSubmissionsApi.index(params.value)
    tableData.value = data.data ?? []
    tableMeta.value = data.meta ?? { current_page: 1, last_page: 1, per_page: authStore.defaultTablePageSize || 25, total: 0 }
  } catch {
    tableData.value = []
  } finally {
    tableLoading.value = false
  }
}

async function loadColumns() {
  try {
    const data = await leadSubmissionsApi.columns()
    allColumns.value = data.all_columns ?? []
    const visible = data.visible_columns
    if (Array.isArray(visible) && visible.length) {
      visibleColumns.value = visible.filter((c) => c !== 'id')
    }
  } catch {
    /* keep defaults */
  }
}

/* ───── Filters ───── */
function resetFilters() {
  filters.value = {
    from: '', to: '', submitted_from: '', submitted_to: '',
    service_category_id: '', status: '', sales_agent_id: '', team_leader_id: '',
  }
  tableMeta.value.current_page = 1
  loadStats()
  loadTable()
}

function applyFilters() {
  tableMeta.value.current_page = 1
  loadStats()
  loadTable()
}

/* ───── Sorting ───── */
function onSort(colKey) {
  if (sort.value === colKey) {
    order.value = order.value === 'asc' ? 'desc' : 'asc'
  } else {
    sort.value = colKey
    order.value = 'asc'
  }
  tableMeta.value.current_page = 1
  loadTable()
}

/* ───── Pagination ───── */
function onPageChange(page) {
  tableMeta.value.current_page = page
}

async function onPerPageChange(e) {
  const val = Number(e.target.value)
  tableMeta.value.per_page = val
  tableMeta.value.current_page = 1
  loadTable()
  try { await api.post(`/table-preferences/${TABLE_MODULE}`, { per_page: val }) } catch { /* silent */ }
}

watch(() => tableMeta.value.current_page, () => loadTable())

/* ───── Column customization ───── */
async function onSaveColumns(cols) {
  try {
    await leadSubmissionsApi.saveColumns(cols)
    visibleColumns.value = cols.filter((c) => c !== 'id')
    tableMeta.value.current_page = 1
    loadTable()
  } catch {
    /* silent */
  }
}

/* ───── Format helpers ───── */
function formatDate(d) {
  if (!d) return '—'
  const str = typeof d === 'string' ? d.trim().slice(0, 10) : ''
  return toDdMonYyyyLower(str) || '—'
}

function slaBadgeClass(slaTimer) {
  if (!slaTimer) return 'bg-gray-100 text-gray-700'
  if (String(slaTimer).startsWith('Overdue')) return 'bg-red-100 text-red-800'
  if (String(slaTimer).includes('left') && String(slaTimer).includes('h')) return 'bg-amber-100 text-amber-800'
  return 'bg-green-100 text-green-800'
}

function slaLabel(slaTimer) {
  if (!slaTimer) return '—'
  if (String(slaTimer).startsWith('Overdue')) return 'Breached'
  if (String(slaTimer).includes('h left')) return 'Approaching'
  return 'On Time'
}

const statusLabels = {
  draft: 'Draft',
  submitted: 'Submitted',
  approved: 'Approved',
  rejected: 'Rejected',
  pending_for_ata: 'Pending for ATA',
  pending_for_finance: 'Pending for Finance',
  pending_from_sales: 'Pending from Sales',
  unassigned: 'Unassigned',
}

function statusBadgeClass(status) {
  const s = (status || '').toLowerCase()
  if (s === 'approved' || s === 'completed') return 'bg-green-100 text-green-800'
  if (s === 'rejected') return 'bg-red-100 text-red-800'
  if (s === 'submitted' || s === 'pending_for_ata' || s === 'pending_for_finance' || s === 'pending_from_sales') return 'bg-blue-100 text-blue-800'
  return 'bg-gray-100 text-gray-700'
}

function statusLabel(status) {
  return statusLabels[status] || status || '—'
}

function truncate(val, len = 30) {
  if (!val) return '—'
  const s = String(val)
  return s.length > len ? s.slice(0, len) + '…' : s
}

function cellValue(row, key) {
  const v = row[key]
  if (v == null) return '—'
  if (key === 'submitted_at' || key === 'status_changed_at' || key === 'updated_at' || key === 'completion_date') return formatDate(v)
  if (key === 'creator' && typeof v === 'object') return v.name ?? '—'
  if (typeof v === 'object' && v !== null && 'name' in v) return v.name ?? '—'
  return String(v) || '—'
}

/* ───── Chart computed ───── */
const byStatusList = computed(() => {
  const by = stats.value.by_status || {}
  return Object.entries(by).map(([name, count]) => ({ name: statusLabel(name), count })).sort((a, b) => b.count - a.count)
})

const statusBarColors = ['bg-emerald-500', 'bg-blue-500', 'bg-amber-500', 'bg-red-500', 'bg-purple-500', 'bg-cyan-500']

const maxStatusCount = computed(() => Math.max(1, ...byStatusList.value.map((s) => s.count)))
const maxCategoryCount = computed(() => Math.max(1, ...(stats.value.by_category || []).map((c) => c.count)))
const maxTrendCount = computed(() => Math.max(1, ...(stats.value.monthly_trend || []).map((t) => t.count)))

/* ───── Export ───── */
function escapeCsv(val) {
  if (val == null) return ''
  const s = String(val)
  if (s.includes(',') || s.includes('"') || s.includes('\n')) return '"' + s.replace(/"/g, '""') + '"'
  return s
}

async function exportExcel() {
  exportLoading.value = true
  try {
    const exportParams = { ...params.value, page: 1, per_page: 5000 }
    const data = await leadSubmissionsApi.index(exportParams)
    const rows = data.data ?? []
    const cols = visibleColumns.value.filter((c) => c !== 'id')
    const headers = cols.map((c) => COLUMN_LABELS[c] ?? c)
    const csvRows = [headers.map(escapeCsv).join(',')]
    for (const row of rows) {
      csvRows.push(cols.map((col) => escapeCsv(cellValue(row, col))).join(','))
    }
    const blob = new Blob([csvRows.join('\r\n')], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `lead-reports-${new Date().toISOString().slice(0, 10)}.csv`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    /* silent */
  } finally {
    exportLoading.value = false
  }
}

/* ───── Row number helper ───── */
function rowNumber(idx) {
  return (tableMeta.value.current_page - 1) * tableMeta.value.per_page + idx + 1
}

/* ───── Load user table preference ───── */
async function loadTablePreference() {
  try {
    const { data } = await api.get(`/table-preferences/${TABLE_MODULE}`)
    if (data.per_page) tableMeta.value.per_page = Number(data.per_page)
    if (Array.isArray(data.options) && data.options.length) perPageOptions.value = data.options
  } catch { /* use system default */ }
}

/* ───── Init ───── */
onMounted(async () => {
  loading.value = true
  await Promise.all([loadFilterOptions(), loadColumns(), loadTablePreference()])
  await Promise.all([loadStats(), loadTable()])
  loading.value = false
})
</script>

<template>
  <div class="space-y-6 bg-white -mx-4 -my-5 min-h-full px-6 py-6">
    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <div class="flex flex-wrap items-baseline gap-2">
          <h1 class="text-2xl font-bold text-gray-900">Lead Reports</h1>
          <Breadcrumbs />
        </div>
        <p class="text-sm text-gray-500 mt-1">Analyze Lead Submissions, Resubmissions, and conversion metrics by service category and sales team.</p>
      </div>
      <div class="flex gap-2">
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-70 disabled:cursor-wait"
          :disabled="exportLoading"
          @click="exportExcel"
        >
          <svg v-if="exportLoading" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" /></svg>
          <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
          {{ exportLoading ? 'Exporting…' : 'Export' }}
        </button>
      </div>
    </div>

    <!-- KPI cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
      <div class="rounded-xl bg-gray-800 text-white p-5 shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-300">Total Leads</p>
            <p class="text-2xl font-bold mt-1">{{ statsLoading ? '…' : stats.total_leads.toLocaleString() }}</p>
          </div>
          <div class="rounded-full bg-emerald-500/20 p-2">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
          </div>
        </div>
      </div>
      <div class="rounded-xl bg-gray-800 text-white p-5 shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-300">New Submissions</p>
            <p class="text-2xl font-bold mt-1">{{ statsLoading ? '…' : stats.new_submissions.toLocaleString() }}</p>
          </div>
          <div class="rounded-full bg-emerald-500/20 p-2">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
          </div>
        </div>
      </div>
      <div class="rounded-xl bg-gray-800 text-white p-5 shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-300">Resubmissions</p>
            <p class="text-2xl font-bold mt-1">{{ statsLoading ? '…' : stats.resubmissions.toLocaleString() }}</p>
          </div>
          <div class="rounded-full bg-amber-500/20 p-2">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
          </div>
        </div>
      </div>
      <div class="rounded-xl bg-gray-800 text-white p-5 shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-300">SLA Compliance</p>
            <p class="text-2xl font-bold mt-1">{{ statsLoading ? '…' : stats.sla_compliance_pct }}%</p>
          </div>
          <div class="rounded-full bg-blue-500/20 p-2">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          </div>
        </div>
      </div>
      <div class="rounded-xl bg-gray-800 text-white p-5 shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-300">Avg Processing Time</p>
            <p class="text-2xl font-bold mt-1">{{ statsLoading ? '…' : stats.avg_processing_days }} days</p>
          </div>
          <div class="rounded-full bg-purple-500/20 p-2">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Charts row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Leads by Status -->
      <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Leads by Status</h3>
        <div v-if="statsLoading" class="py-8 text-center text-gray-400 text-sm">Loading…</div>
        <div v-else class="space-y-3">
          <div v-for="(s, idx) in byStatusList" :key="s.name" class="flex items-center gap-3">
            <span class="w-28 text-sm text-gray-600 truncate">{{ s.name }}</span>
            <div class="flex-1 h-6 bg-gray-100 rounded-full overflow-hidden">
              <div class="h-full rounded-full transition-all duration-500" :class="statusBarColors[idx % statusBarColors.length]" :style="{ width: (100 * s.count / maxStatusCount) + '%' }" />
            </div>
            <span class="text-sm font-semibold text-gray-900 w-12 text-right">{{ s.count.toLocaleString() }}</span>
          </div>
          <p v-if="!byStatusList.length" class="text-sm text-gray-400 text-center py-4">No data available</p>
        </div>
      </div>

      <!-- Service Category Distribution -->
      <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Service Category Distribution</h3>
        <div v-if="statsLoading" class="py-8 text-center text-gray-400 text-sm">Loading…</div>
        <div v-else class="space-y-3">
          <div v-for="c in (stats.by_category || [])" :key="c.name" class="flex items-center gap-3">
            <span class="w-28 text-sm text-gray-600 truncate">{{ c.name }}</span>
            <div class="flex-1 h-6 bg-gray-100 rounded-full overflow-hidden">
              <div class="h-full bg-amber-500 rounded-full transition-all duration-500" :style="{ width: (100 * c.count / maxCategoryCount) + '%' }" />
            </div>
            <span class="text-sm font-semibold text-gray-900 w-12 text-right">{{ c.count.toLocaleString() }}</span>
          </div>
          <p v-if="!(stats.by_category || []).length" class="text-sm text-gray-400 text-center py-4">No data available</p>
        </div>
      </div>

      <!-- Monthly Trend -->
      <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Monthly Trend</h3>
        <div v-if="statsLoading" class="py-8 text-center text-gray-400 text-sm">Loading…</div>
        <div v-else class="space-y-3">
          <div v-for="t in (stats.monthly_trend || []).slice(-6)" :key="t.label" class="flex items-center gap-3">
            <span class="w-20 text-sm text-gray-600">{{ t.label }}</span>
            <div class="flex-1 h-6 bg-gray-100 rounded-full overflow-hidden">
              <div class="h-full bg-blue-500 rounded-full transition-all duration-500" :style="{ width: (100 * t.count / maxTrendCount) + '%' }" />
            </div>
            <span class="text-sm font-semibold text-gray-900 w-12 text-right">{{ t.count.toLocaleString() }}</span>
          </div>
          <p v-if="!(stats.monthly_trend || []).length" class="text-sm text-gray-400 text-center py-4">No data available</p>
        </div>
      </div>
    </div>

    <!-- Filters Section (below charts) -->
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
      <div class="flex flex-wrap items-end gap-4">
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
          <select v-model="filters.status" class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-44 focus:ring-green-500 focus:border-green-500">
            <option value="">All Status</option>
            <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Service Category</label>
          <select v-model="filters.service_category_id" class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-48 focus:ring-green-500 focus:border-green-500">
            <option value="">All Categories</option>
            <option v-for="c in filterOptions.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <button type="button" class="rounded-lg bg-green-600 px-5 py-2 text-sm font-medium text-white hover:bg-green-700" @click="applyFilters">Apply</button>
        <button type="button" class="rounded-lg border border-gray-300 bg-white px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="resetFilters">Reset</button>
        <div class="ml-auto flex items-center gap-2">
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
            @click="advancedVisible = !advancedVisible"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
            Advanced Filters
            <span v-if="activeFilterCount > 0" class="ml-1 inline-flex h-5 w-5 items-center justify-center rounded-full bg-green-600 text-[10px] font-bold text-white">{{ activeFilterCount }}</span>
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
            @click="columnModalVisible = true"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" /></svg>
            Customize Columns
          </button>
        </div>
      </div>

      <!-- Advanced Filters -->
      <div v-if="advancedVisible" class="mt-4 pt-4 border-t border-gray-200">
        <div class="flex flex-wrap items-end gap-4">
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Date From</label>
            <input v-model="filters.from" type="date" class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-40 focus:ring-green-500 focus:border-green-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Date To</label>
            <input v-model="filters.to" type="date" class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-40 focus:ring-green-500 focus:border-green-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Submitted From</label>
            <input v-model="filters.submitted_from" type="date" class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-40 focus:ring-green-500 focus:border-green-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Submitted To</label>
            <input v-model="filters.submitted_to" type="date" class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-40 focus:ring-green-500 focus:border-green-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Sales Agent ID</label>
            <input v-model="filters.sales_agent_id" type="number" placeholder="Agent ID" class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-36 focus:ring-green-500 focus:border-green-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Team Leader ID</label>
            <input v-model="filters.team_leader_id" type="number" placeholder="Leader ID" class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-36 focus:ring-green-500 focus:border-green-500" />
          </div>
        </div>
      </div>
    </div>

    <!-- Table Section -->
    <div class="rounded-xl border-2 border-black bg-white shadow-sm overflow-hidden">
      <!-- Table header bar -->
      <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-b-2 border-black bg-white">
        <h2 class="text-base font-semibold text-gray-900">Lead Submissions Details</h2>
        <p class="text-sm text-gray-500">{{ tableMeta.total }} records</p>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="min-w-full">
          <thead class="bg-white">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b-2 border-black">#</th>
              <th
                v-for="col in activeColumns"
                :key="col.key"
                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer select-none hover:bg-gray-50 transition-colors border-b-2 border-black"
                @click="onSort(col.key)"
              >
                <div class="flex items-center gap-1">
                  <span>{{ col.label }}</span>
                  <span v-if="sort === col.key" class="text-green-600">
                    <svg v-if="order === 'asc'" class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" /></svg>
                    <svg v-else class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                  </span>
                  <span v-else class="text-gray-300">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" /></svg>
                  </span>
                </div>
              </th>
            </tr>
          </thead>
          <tbody class="bg-white">
            <tr v-if="tableLoading">
              <td :colspan="activeColumns.length + 1" class="px-4 py-12 text-center text-gray-400 border-b border-black">
                <svg class="mx-auto h-6 w-6 animate-spin text-gray-400 mb-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" /></svg>
                Loading…
              </td>
            </tr>
            <tr v-else-if="!tableData.length">
              <td :colspan="activeColumns.length + 1" class="px-4 py-12 text-center text-gray-400 border-b border-black">No records found</td>
            </tr>
            <tr v-else v-for="(row, idx) in tableData" :key="row.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-2.5 text-sm text-gray-500 whitespace-nowrap border-b border-black">{{ rowNumber(idx) }}</td>
              <td v-for="col in activeColumns" :key="col.key" class="px-4 py-2.5 text-sm whitespace-nowrap border-b border-black">
                <!-- Status badge -->
                <template v-if="col.key === 'status'">
                  <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusBadgeClass(row.status)]">
                    {{ statusLabel(row.status) }}
                  </span>
                </template>
                <!-- SLA badge -->
                <template v-else-if="col.key === 'sla_timer'">
                  <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', slaBadgeClass(row.sla_timer)]">
                    {{ slaLabel(row.sla_timer) }}
                  </span>
                </template>
                <!-- Company name (truncated with tooltip) -->
                <template v-else-if="col.key === 'company_name'">
                  <span
                    class="font-medium text-gray-900 cursor-default"
                    :title="row.company_name || ''"
                  >{{ truncate(row.company_name, 30) }}</span>
                </template>
                <!-- Default cell -->
                <template v-else>
                  <span class="text-gray-700">{{ cellValue(row, col.key) }}</span>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination footer -->
      <div class="flex flex-wrap items-center justify-between gap-3 border-t-2 border-black bg-white px-4 py-3">
        <!-- Left: entries info -->
        <p class="text-sm text-gray-600">
          Showing {{ tableMeta.total ? ((tableMeta.current_page - 1) * tableMeta.per_page) + 1 : 0 }}
          to {{ Math.min(tableMeta.current_page * tableMeta.per_page, tableMeta.total) }}
          of {{ tableMeta.total }} entries
        </p>

        <!-- Right: Number of rows + Previous / Page X of Y / Next -->
        <div class="flex items-center gap-4">
          <div class="flex items-center gap-2 text-sm text-gray-600">
            <span class="whitespace-nowrap font-medium">Number of rows</span>
            <select
              :value="tableMeta.per_page"
              class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm min-w-[80px] text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
              @change="onPerPageChange"
            >
              <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
            </select>
          </div>

          <div class="flex items-center gap-1.5">
            <button
              type="button"
              :disabled="tableMeta.current_page <= 1"
              class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
              @click="onPageChange(tableMeta.current_page - 1)"
            >Previous</button>
            <span class="rounded-md border border-gray-300 bg-gray-50 px-3 py-1.5 text-sm text-gray-700">
              Page {{ tableMeta.current_page }} of {{ tableMeta.last_page }}
            </span>
            <button
              type="button"
              :disabled="tableMeta.current_page >= tableMeta.last_page"
              class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
              @click="onPageChange(tableMeta.current_page + 1)"
            >Next</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Column Customizer Modal -->
    <ColumnCustomizerModal
      :visible="columnModalVisible"
      :all-columns="allColumns"
      :visible-columns="visibleColumns"
      :default-columns="defaultVisibleColumns"
      @update:visible="columnModalVisible = $event"
      @save="onSaveColumns"
    />
  </div>
</template>
