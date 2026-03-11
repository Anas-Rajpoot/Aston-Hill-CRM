<script setup>
/**
 * VAS Reports – KPIs (total, pending, completed today, SLA compliance),
 * clickable stat cards, advanced filters, sortable/customizable table,
 * column customizer, CSV export, pagination.
 * Super admin sees all VAS requests; back office sees assigned; others see own.
 */
import { ref, computed, watch, onMounted } from 'vue'
import api from '@/lib/axios'
import ColumnCustomizerModal from '@/components/lead-submissions/ColumnCustomizerModal.vue'
import { toDdMonYyyyLower, toDdMonYyyyDash } from '@/lib/dateFormat'
import { useAuthStore } from '@/stores/auth'
import { canModuleAction } from '@/lib/accessControl'

const authStore = useAuthStore()
const canView = computed(() =>
  canModuleAction(authStore.user, 'reports', 'view', ['reports.view', 'reports.list'])
)
const canExport = computed(() =>
  canModuleAction(authStore.user, 'reports', 'export', ['reports.export', 'reports.export_reports'])
)
const TABLE_MODULE = 'vas-reports'
const perPageOptions = ref([10, 20, 25, 50, 100])

/* ───── Loading states ───── */
const loading = ref(true)
const statsLoading = ref(true)
const tableLoading = ref(false)
const exportLoading = ref(false)

/* ───── KPI / stats state ───── */
const stats = ref({
  total_vas_requests: 0,
  pending_requests: 0,
  completed_today: 0,
  sla_compliance_pct: 0,
})

/* ───── Active card filter ───── */
const activeCard = ref('all')

/* ───── Filter state ───── */
const filterOptions = ref({ request_types: [], statuses: [], managers: [], team_leaders: [], sales_agents: [] })
const advancedFiltersVisible = ref(false)
const fromPickerRef = ref(null)
const toPickerRef = ref(null)
const submittedFromPickerRef = ref(null)
const submittedToPickerRef = ref(null)
const filters = ref({
  from: '',
  to: '',
  submitted_from: '',
  submitted_to: '',
  status: '',
  request_type: '',
  company_name: '',
  account_number: '',
  manager_id: '',
  team_leader_id: '',
  sales_agent_id: '',
  back_office_executive_id: '',
})
const boOptions = ref([])

/* ───── Table data ───── */
const tableData = ref([])
const tableMeta = ref({ current_page: 1, last_page: 1, per_page: authStore.defaultTablePageSize || 25, total: 0 })
const sort = ref('submitted_at')
const order = ref('desc')

/* ───── Column customization ───── */
const columnModalVisible = ref(false)
const allColumns = ref([])
const VAS_REQUESTS_COLUMN_ORDER = [
  'created_at', 'request_type', 'account_number', 'contact_number', 'company_name',
  'request_description', 'additional_notes', 'manager', 'team_leader', 'sales_agent',
  'executive', 'sla_timer', 'status', 'creator',
]
const visibleColumns = ref([...VAS_REQUESTS_COLUMN_ORDER])
const defaultVisibleColumns = [...VAS_REQUESTS_COLUMN_ORDER]

const COLUMN_LABELS = {
  id: 'SR',
  submitted_at: 'Submission Date',
  created_at: 'Created',
  request_type: 'Request Type',
  account_number: 'Account Number',
  company_name: 'Company Name as per Trade License',
  description: 'Request Description',
  request_description: 'Request Description',
  additional_notes: 'Additional Notes',
  contact_number: 'Contact Number',
  manager: 'Manager Name',
  team_leader: 'Team Leader Name',
  sales_agent: 'Sales Agent Name',
  executive: 'Back Office Executive',
  status: 'Status',
  approved_at: 'Completion Date',
  completion_date: 'Completion Date',
  creator: 'Submitter Name',
  sla_timer: 'SLA Timer',
  activity: 'Activity',
  remarks: 'Remarks',
}

const SLA_DAYS = 7

/* ───── Computed helpers ───── */
const activeColumns = computed(() =>
  visibleColumns.value.map((key) => ({
    key,
    label: COLUMN_LABELS[key] || key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()),
  }))
)

const activeFilterCount = computed(() => {
  let count = 0
  const f = filters.value
  if (f.from) count++
  if (f.to) count++
  if (f.submitted_from) count++
  if (f.submitted_to) count++
  if (f.company_name) count++
  if (f.account_number) count++
  if (f.manager_id) count++
  if (f.team_leader_id) count++
  if (f.sales_agent_id) count++
  if (f.back_office_executive_id) count++
  return count
})

function filterParams() {
  const p = {}
  const f = filters.value
  if (f.from) p.from = f.from
  if (f.to) p.to = f.to
  if (f.submitted_from) p.submitted_from = f.submitted_from
  if (f.submitted_to) p.submitted_to = f.submitted_to
  if (f.status) p.status = f.status
  if (f.request_type) p.request_type = f.request_type
  if (f.company_name) p.company_name = f.company_name
  if (f.account_number) p.account_number = f.account_number
  if (f.manager_id) p.manager_id = f.manager_id
  if (f.team_leader_id) p.team_leader_id = f.team_leader_id
  if (f.sales_agent_id) p.sales_agent_id = f.sales_agent_id
  if (f.back_office_executive_id) p.back_office_executive_id = f.back_office_executive_id
  return p
}

function displayFilterDate(ymd) {
  return toDdMonYyyyDash(ymd || '') || ''
}

function openPicker(inputRef) {
  const el = inputRef?.value
  if (!el) return
  if (typeof el.showPicker === 'function') {
    el.showPicker()
  } else {
    el.focus()
    el.click()
  }
}

function normalizeVisibleColumns(cols) {
  const unique = [...new Set((Array.isArray(cols) ? cols : []).filter(Boolean))]
  const canonical = VAS_REQUESTS_COLUMN_ORDER.filter((col) => unique.includes(col))
  const extras = unique.filter((col) => !VAS_REQUESTS_COLUMN_ORDER.includes(col))
  return [...canonical, ...extras]
}

const params = computed(() => {
  const p = {
    page: tableMeta.value.current_page,
    per_page: tableMeta.value.per_page,
    sort: sort.value,
    order: order.value,
    columns: visibleColumns.value,
  }
  Object.assign(p, filterParams())
  if (activeCard.value && activeCard.value !== 'all') {
    p.card_filter = activeCard.value
  }
  return p
})

/* ───── Data loading ───── */
async function loadFilterOptions() {
  if (!canView.value) return
  try {
    const { data } = await api.get('/vas-requests/filters')
    filterOptions.value.request_types = data.request_types ?? []
    filterOptions.value.statuses = data.statuses ?? []
    filterOptions.value.managers = data.managers ?? []
    filterOptions.value.team_leaders = data.team_leaders ?? []
    filterOptions.value.sales_agents = data.sales_agents ?? []
  } catch {
    filterOptions.value = { request_types: [], statuses: [], managers: [], team_leaders: [], sales_agents: [] }
  }
}

async function loadBoOptions() {
  if (!canView.value) return
  try {
    const { data } = await api.get('/vas-requests/back-office-options')
    boOptions.value = data.executives ?? []
  } catch {
    boOptions.value = []
  }
}

async function loadStats() {
  if (!canView.value) return
  statsLoading.value = true
  try {
    const { data } = await api.get('/reports/vas-stats', { params: filterParams() })
    stats.value = data
  } catch {
    stats.value = { total_vas_requests: 0, pending_requests: 0, completed_today: 0, sla_compliance_pct: 0 }
  } finally {
    statsLoading.value = false
  }
}

async function loadTable() {
  if (!canView.value) return
  tableLoading.value = true
  try {
    const { data } = await api.get('/vas-requests', { params: params.value })
    tableData.value = data.data ?? []
    tableMeta.value = data.meta ?? { current_page: 1, last_page: 1, per_page: authStore.defaultTablePageSize || 25, total: 0 }
  } catch {
    tableData.value = []
  } finally {
    tableLoading.value = false
  }
}

async function loadColumns() {
  if (!canView.value) return
  try {
    const { data } = await api.get('/vas-requests/columns')
    allColumns.value = data.all_columns ?? []
    const visible = data.visible_columns
    if (Array.isArray(visible) && visible.length) {
      visibleColumns.value = normalizeVisibleColumns(visible.filter((c) => c !== 'id'))
    }
  } catch {
    /* keep defaults */
  }
}

/* ───── Card clicks ───── */
function selectCard(card) {
  activeCard.value = card
  tableMeta.value.current_page = 1
  loadTable()
}

/* ───── Filters ───── */
function resetFilters() {
  filters.value = {
    from: '', to: '', submitted_from: '', submitted_to: '',
    status: '', request_type: '', company_name: '', account_number: '',
    manager_id: '', team_leader_id: '', sales_agent_id: '', back_office_executive_id: '',
  }
  activeCard.value = 'all'
  tableMeta.value.current_page = 1
  loadStats()
  loadTable()
}

function applyFilters() {
  activeCard.value = 'all'
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
    const filtered = cols.filter((c) => c !== 'id')
    const normalized = normalizeVisibleColumns(filtered)
    await api.post('/vas-requests/columns', { visible_columns: normalized })
    visibleColumns.value = normalized
    tableMeta.value.current_page = 1
    loadTable()
  } catch {
    /* silent */
  }
}

/* ───── Truncate helper ───── */
function truncate(val, len = 30) {
  if (!val) return '—'
  const s = String(val)
  return s.length > len ? s.slice(0, len) + '…' : s
}

/* ───── Row number helper ───── */
function rowNumber(idx) {
  return (tableMeta.value.current_page - 1) * tableMeta.value.per_page + idx + 1
}

/* ───── Format helpers ───── */
function formatDate(d) {
  if (!d) return '—'
  const str = typeof d === 'string' ? d.trim().slice(0, 10) : ''
  return toDdMonYyyyLower(str) || d || '—'
}

function statusLabel(status) {
  const s = (status || '').toLowerCase()
  if (s === 'approved') return 'Completed'
  if (s === 'rejected') return 'Rejected'
  if (s === 'submitted') return 'Submitted'
  if (s === 'draft') return 'Pending'
  return status || '—'
}

function statusBadgeClass(status) {
  const s = (status || '').toLowerCase()
  if (s === 'approved') return 'bg-brand-primary-light text-brand-primary-hover'
  if (s === 'rejected') return 'bg-red-100 text-red-800'
  if (s === 'submitted') return 'bg-brand-primary-light text-brand-primary-hover'
  return 'bg-gray-100 text-gray-700'
}

function duStatusLabel(status) {
  const s = (status || '').toLowerCase()
  if (s === 'approved') return 'Approved'
  if (s === 'rejected') return 'Rejected'
  return 'Pending'
}

function duStatusBadgeClass(status) {
  const s = (status || '').toLowerCase()
  if (s === 'approved') return 'bg-brand-primary-light text-brand-primary-hover'
  if (s === 'rejected') return 'bg-red-100 text-red-800'
  return 'bg-amber-100 text-amber-800'
}

function slaStatus(row) {
  if (!row.submitted_at || !row.approved_at) return null
  const s = (row.status || '').toLowerCase()
  if (s !== 'approved') return null
  const sub = parseDate(row.submitted_at)
  const app = parseDate(row.approved_at)
  if (!sub || !app) return null
  const days = Math.floor((app - sub) / (24 * 60 * 60 * 1000))
  return days <= SLA_DAYS ? 'Within SLA' : 'Breached SLA'
}

function parseDate(str) {
  if (!str) return null
  const part = String(str).trim().split(/\s+/)[0] || str
  const isoMatch = part.match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (isoMatch) return new Date(parseInt(isoMatch[1], 10), parseInt(isoMatch[2], 10) - 1, parseInt(isoMatch[3], 10)).getTime()
  const ddMonMatch = part.match(/(\d{1,2})[\/\-](\w{3})[\/\-](\d{4})/)
  if (!ddMonMatch) return null
  const months = { Jan: 0, Feb: 1, Mar: 2, Apr: 3, May: 4, Jun: 5, Jul: 6, Aug: 7, Sep: 8, Oct: 9, Nov: 10, Dec: 11 }
  const m = months[ddMonMatch[2]]
  if (m === undefined) return null
  return new Date(parseInt(ddMonMatch[3], 10), m, parseInt(ddMonMatch[1], 10)).getTime()
}

function slaBadgeClass(sla) {
  if (!sla) return 'bg-gray-100 text-gray-700'
  return sla === 'Breached SLA' ? 'bg-red-100 text-red-800' : 'bg-brand-primary-light text-brand-primary-hover'
}

function cellValue(row, key) {
  if (key === 'submitted_at' || key === 'approved_at' || key === 'created_at') return formatDate(row[key])
  if (key === 'creator' && typeof row[key] === 'object') return row[key]?.name ?? '—'
  if (typeof row[key] === 'object' && row[key] !== null && 'name' in row[key]) return row[key].name ?? '—'
  return row[key] != null ? String(row[key]) : '—'
}

/* ───── Export CSV ───── */
function escapeCsv(val) {
  if (val == null) return ''
  const s = String(val)
  if (s.includes(',') || s.includes('"') || s.includes('\n')) return '"' + s.replace(/"/g, '""') + '"'
  return s
}

async function exportReport() {
  if (!canExport.value) return
  exportLoading.value = true
  try {
    const exportParams = { ...params.value, page: 1, per_page: 5000 }
    const { data } = await api.get('/vas-requests', { params: exportParams })
    const rows = data.data ?? []
    const cols = visibleColumns.value
    const headers = cols.map((c) => COLUMN_LABELS[c] ?? c)
    const csvRows = [headers.map(escapeCsv).join(',')]
    for (const row of rows) {
      const rowCols = cols.map((col) => {
        if (col === 'status') return escapeCsv(statusLabel(row.status))
        return escapeCsv(cellValue(row, col))
      })
      csvRows.push(rowCols.join(','))
    }
    const blob = new Blob([csvRows.join('\r\n')], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `vas-reports-${new Date().toISOString().slice(0, 10)}.csv`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    /* silent */
  } finally {
    exportLoading.value = false
  }
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
  if (!canView.value) {
    loading.value = false
    return
  }
  loading.value = true
  await Promise.all([loadFilterOptions(), loadColumns(), loadBoOptions(), loadTablePreference()])
  await Promise.all([loadStats(), loadTable()])
  loading.value = false
})
</script>

<template>
  <div class="space-y-6 bg-white -mx-4 -my-5 min-h-full px-6 py-6">
    <div v-if="!canView" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
      You do not have permission to view reports.
    </div>

    <!-- KPI Cards (clickable) -->
    <div v-if="canView" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Total VAS Requests -->
      <button
        type="button"
        :class="[
          'rounded-xl border-2 p-5 shadow-sm flex items-center gap-4 text-left transition-all',
          activeCard === 'all'
            ? 'border-brand-primary bg-brand-primary-light ring-1 ring-brand-primary-muted'
            : 'border-gray-200 bg-white hover:border-brand-primary-muted hover:shadow-md',
        ]"
        @click="selectCard('all')"
      >
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-brand-primary-light text-brand-primary">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
        </div>
        <div>
          <p class="text-sm font-medium text-gray-500">Total VAS Requests</p>
          <p class="text-2xl font-bold text-gray-900">{{ statsLoading ? '…' : stats.total_vas_requests.toLocaleString() }}</p>
        </div>
      </button>

      <!-- Pending Requests -->
      <button
        type="button"
        :class="[
          'rounded-xl border-2 p-5 shadow-sm flex items-center gap-4 text-left transition-all',
          activeCard === 'pending'
            ? 'border-amber-500 bg-amber-50 ring-1 ring-amber-200'
            : 'border-gray-200 bg-white hover:border-amber-300 hover:shadow-md',
        ]"
        @click="selectCard('pending')"
      >
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-500">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        </div>
        <div>
          <p class="text-sm font-medium text-gray-500">Pending Requests</p>
          <p class="text-2xl font-bold text-gray-900">{{ statsLoading ? '…' : stats.pending_requests.toLocaleString() }}</p>
        </div>
      </button>

      <!-- Completed Today -->
      <button
        type="button"
        :class="[
          'rounded-xl border-2 p-5 shadow-sm flex items-center gap-4 text-left transition-all',
          activeCard === 'completed_today'
            ? 'border-brand-primary bg-brand-primary-light ring-1 ring-brand-primary-muted'
            : 'border-gray-200 bg-white hover:border-brand-primary-muted hover:shadow-md',
        ]"
        @click="selectCard('completed_today')"
      >
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-brand-primary-light text-brand-primary">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
        </div>
        <div>
          <p class="text-sm font-medium text-gray-500">Completed Today</p>
          <p class="text-2xl font-bold text-gray-900">{{ statsLoading ? '…' : stats.completed_today.toLocaleString() }}</p>
        </div>
      </button>

      <!-- SLA Compliance -->
      <div class="rounded-xl border-2 border-gray-200 bg-white p-5 shadow-sm flex items-center gap-4">
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-brand-primary-light text-brand-primary">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h2v16H4V4z" /></svg>
        </div>
        <div>
          <p class="text-sm font-medium text-gray-500">SLA Compliance</p>
          <p class="text-2xl font-bold text-gray-900">{{ statsLoading ? '…' : stats.sla_compliance_pct }}%</p>
        </div>
      </div>
    </div>

    <!-- Filters Section -->
    <div v-if="canView" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
      <div class="overflow-x-auto">
        <div class="flex w-max min-w-full flex-nowrap items-end gap-4">
        <div class="shrink-0">
          <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
          <select v-model="filters.status" class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-44 focus:ring-brand-primary focus:border-brand-primary">
            <option value="">All Status</option>
            <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </div>
        <div class="shrink-0">
          <label class="block text-xs font-medium text-gray-500 mb-1">Request Type</label>
          <select v-model="filters.request_type" class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-44 focus:ring-brand-primary focus:border-brand-primary">
            <option value="">All Types</option>
            <option v-for="t in filterOptions.request_types" :key="t.value" :value="t.value">{{ t.label }}</option>
          </select>
        </div>
        <button type="button" class="shrink-0 rounded-lg bg-brand-primary px-5 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover" @click="applyFilters">Apply</button>
        <button type="button" class="shrink-0 rounded-lg border border-gray-300 bg-white px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="resetFilters">Reset</button>
        <div class="flex shrink-0 items-center gap-2">
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
            @click="advancedFiltersVisible = !advancedFiltersVisible"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
            Advanced Filters
            <span v-if="activeFilterCount > 0" class="ml-1 inline-flex h-5 w-5 items-center justify-center rounded-full bg-brand-primary text-[10px] font-bold text-white">{{ activeFilterCount }}</span>
          </button>
          <button
            v-if="canExport"
            type="button"
            class="inline-flex items-center gap-2 rounded-lg bg-brand-primary px-3 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover disabled:opacity-70 disabled:cursor-wait"
            :disabled="exportLoading"
            @click="exportReport"
          >
            <svg v-if="exportLoading" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
            <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
            {{ exportLoading ? 'Exporting…' : 'Export' }}
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
      </div>

      <!-- Advanced Filters -->
      <div v-if="advancedFiltersVisible" class="mt-4 pt-4 border-t border-gray-200">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Date From</label>
            <div class="relative w-full">
              <input
                :value="displayFilterDate(filters.from)"
                type="text"
                readonly
                placeholder="DD-MMM-YYYY"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 pr-9 text-sm focus:ring-brand-primary focus:border-brand-primary"
                @click="openPicker(fromPickerRef)"
              />
              <input
                ref="fromPickerRef"
                type="date"
                class="pointer-events-none absolute opacity-0"
                :value="filters.from"
                tabindex="-1"
                @change="filters.from = $event.target.value || ''"
              />
              <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-gray-400 hover:bg-gray-100" @click="openPicker(fromPickerRef)">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
              </button>
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Date To</label>
            <div class="relative w-full">
              <input
                :value="displayFilterDate(filters.to)"
                type="text"
                readonly
                placeholder="DD-MMM-YYYY"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 pr-9 text-sm focus:ring-brand-primary focus:border-brand-primary"
                @click="openPicker(toPickerRef)"
              />
              <input
                ref="toPickerRef"
                type="date"
                class="pointer-events-none absolute opacity-0"
                :value="filters.to"
                tabindex="-1"
                @change="filters.to = $event.target.value || ''"
              />
              <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-gray-400 hover:bg-gray-100" @click="openPicker(toPickerRef)">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
              </button>
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Submitted From</label>
            <div class="relative w-full">
              <input
                :value="displayFilterDate(filters.submitted_from)"
                type="text"
                readonly
                placeholder="DD-MMM-YYYY"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 pr-9 text-sm focus:ring-brand-primary focus:border-brand-primary"
                @click="openPicker(submittedFromPickerRef)"
              />
              <input
                ref="submittedFromPickerRef"
                type="date"
                class="pointer-events-none absolute opacity-0"
                :value="filters.submitted_from"
                tabindex="-1"
                @change="filters.submitted_from = $event.target.value || ''"
              />
              <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-gray-400 hover:bg-gray-100" @click="openPicker(submittedFromPickerRef)">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
              </button>
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Submitted To</label>
            <div class="relative w-full">
              <input
                :value="displayFilterDate(filters.submitted_to)"
                type="text"
                readonly
                placeholder="DD-MMM-YYYY"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 pr-9 text-sm focus:ring-brand-primary focus:border-brand-primary"
                @click="openPicker(submittedToPickerRef)"
              />
              <input
                ref="submittedToPickerRef"
                type="date"
                class="pointer-events-none absolute opacity-0"
                :value="filters.submitted_to"
                tabindex="-1"
                @change="filters.submitted_to = $event.target.value || ''"
              />
              <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-gray-400 hover:bg-gray-100" @click="openPicker(submittedToPickerRef)">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
              </button>
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Company Name</label>
            <input v-model="filters.company_name" type="text" placeholder="Search company…" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-brand-primary focus:border-brand-primary" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Account Number</label>
            <input v-model="filters.account_number" type="text" placeholder="Search account…" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-brand-primary focus:border-brand-primary" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Manager</label>
            <select v-model="filters.manager_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-brand-primary focus:border-brand-primary">
              <option value="">All Managers</option>
              <option v-for="m in filterOptions.managers" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Team Leader</label>
            <select v-model="filters.team_leader_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-brand-primary focus:border-brand-primary">
              <option value="">All Team Leaders</option>
              <option v-for="tl in filterOptions.team_leaders" :key="tl.id" :value="tl.id">{{ tl.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Sales Agent</label>
            <select v-model="filters.sales_agent_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-brand-primary focus:border-brand-primary">
              <option value="">All Sales Agents</option>
              <option v-for="sa in filterOptions.sales_agents" :key="sa.id" :value="sa.id">{{ sa.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">BO Executive</label>
            <select v-model="filters.back_office_executive_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-brand-primary focus:border-brand-primary">
              <option value="">All Executives</option>
              <option v-for="bo in boOptions" :key="bo.id" :value="bo.id">{{ bo.name }}</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- Table Section -->
    <div v-if="canView" class="overflow-hidden rounded-xl border-2 border-black bg-white shadow-sm">
      <div class="relative overflow-x-auto">
        <div
          v-if="tableLoading"
          class="absolute inset-0 z-10 flex items-center justify-center bg-white/80"
          aria-live="polite"
          aria-busy="true"
        >
          <div class="flex flex-col items-center gap-2">
            <svg class="h-8 w-8 animate-spin text-brand-primary" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <span class="text-sm font-medium text-gray-600">Updating...</span>
          </div>
        </div>
        <table class="min-w-full border-2 border-black border-collapse bg-white">
          <thead class="bg-brand-primary border-b-2 border-green-700">
            <tr>
              <th class="whitespace-nowrap px-4 py-3 text-left text-sm font-semibold text-white border-b-2 border-black w-12">SR</th>
              <th
                v-for="col in activeColumns"
                :key="col.key"
                class="whitespace-nowrap px-4 py-3 text-left text-sm font-semibold text-white cursor-pointer select-none border-b-2 border-black"
                @click="onSort(col.key)"
              >
                <div class="flex items-center gap-1">
                  <span>{{ col.label }}</span>
                  <span v-if="sort === col.key" class="text-white">
                    <svg v-if="order === 'asc'" class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" /></svg>
                    <svg v-else class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                  </span>
                  <span v-else class="text-white/40">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" /></svg>
                  </span>
                </div>
              </th>
              <!-- DU Status (virtual) -->
              <th class="whitespace-nowrap px-4 py-3 text-left text-sm font-semibold text-white border-b-2 border-black">DU Status</th>
              <!-- SLA Status (virtual) -->
              <th class="whitespace-nowrap px-4 py-3 text-left text-sm font-semibold text-white border-b-2 border-black">SLA Status</th>
            </tr>
          </thead>
          <tbody class="bg-white">
            <tr v-if="!tableLoading && !tableData.length" class="border-b border-black bg-white">
              <td :colspan="activeColumns.length + 3" class="px-4 py-12 text-center text-gray-400 border-b border-black">No records found</td>
            </tr>
            <tr v-else v-for="(row, idx) in tableData" :key="row.id" class="border-b border-black bg-white hover:bg-gray-50/50">
              <td class="px-4 py-3 text-sm text-gray-500 whitespace-nowrap border-b border-black">{{ rowNumber(idx) }}</td>
              <td v-for="col in activeColumns" :key="col.key" class="px-4 py-3 text-sm text-gray-900 whitespace-nowrap border-b border-black">
                <!-- Status badge -->
                <template v-if="col.key === 'status'">
                  <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusBadgeClass(row.status)]">
                    {{ statusLabel(row.status) }}
                  </span>
                </template>
                <!-- Company name (truncated with hover) -->
                <template v-else-if="col.key === 'company_name'">
                  <span class="font-medium text-gray-900" :title="row.company_name || ''">{{ truncate(row.company_name, 30) }}</span>
                </template>
                <!-- Default cell -->
                <template v-else>
                  <span class="text-gray-700">{{ cellValue(row, col.key) }}</span>
                </template>
              </td>
              <!-- DU Status (derived from status) -->
              <td class="px-4 py-3 text-sm whitespace-nowrap border-b border-black">
                <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', duStatusBadgeClass(row.status)]">
                  {{ duStatusLabel(row.status) }}
                </span>
              </td>
              <!-- SLA Status -->
              <td class="px-4 py-3 text-sm whitespace-nowrap border-b border-black">
                <span v-if="slaStatus(row)" :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', slaBadgeClass(slaStatus(row))]">
                  {{ slaStatus(row) }}
                </span>
                <span v-else class="text-sm text-gray-400">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination footer -->
      <div class="flex flex-wrap items-center justify-between gap-3 border-t border-black bg-white px-4 py-3">
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
              class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm min-w-[80px] text-gray-700 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
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
      v-if="canView"
      :visible="columnModalVisible"
      :all-columns="allColumns"
      :visible-columns="visibleColumns"
      :default-columns="defaultVisibleColumns"
      @update:visible="columnModalVisible = $event"
      @save="onSaveColumns"
    />
  </div>
</template>
