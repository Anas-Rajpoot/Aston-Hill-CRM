<script setup>
/**
 * Field Operations Reports – KPIs, filters, charts (status distribution, agent workload,
 * meeting completion rate), sortable/customizable field submissions table with pagination and CSV export.
 * Super admins (field_head.list) see all; other users see submissions they created or are assigned to.
 */
import { ref, computed, watch, onMounted } from 'vue'
import api from '@/lib/axios'
import fieldSubmissionsApi from '@/services/fieldSubmissionsApi'
import ColumnCustomizerModal from '@/components/lead-submissions/ColumnCustomizerModal.vue'
import { toDdMonYyyyLower } from '@/lib/dateFormat'
import { useAuthStore } from '@/stores/auth'
import { canModuleAction } from '@/lib/accessControl'

const authStore = useAuthStore()
const canView = computed(() =>
  canModuleAction(authStore.user, 'reports', 'view', ['reports.view', 'reports.list'])
)
const canExport = computed(() =>
  canModuleAction(authStore.user, 'reports', 'export', ['reports.export', 'reports.export_reports'])
)
const TABLE_MODULE = 'field-operations-reports'
const perPageOptions = ref([10, 20, 25, 50, 100])

/* ───── Loading states ───── */
const loading = ref(true)
const statsLoading = ref(true)
const tableLoading = ref(false)
const exportLoading = ref(false)

/* ───── KPI / chart state ───── */
const stats = ref({
  total_requests: 0,
  meetings_completed: 0,
  cancellations: 0,
  follow_ups: 0,
  sla_breaches: 0,
  by_status: [],
  by_agent_workload: [],
  completion_rate_by_month: [],
})

/* ───── Filter state ───── */
const filterOptions = ref({ statuses: [], emirates: [] })
const filters = ref({
  from: '',
  to: '',
  submitted_from: '',
  submitted_to: '',
  status: '',
  emirates: '',
  field_executive_id: '',
})
const advancedVisible = ref(false)

/* ───── Table data ───── */
const tableData = ref([])
const tableMeta = ref({ current_page: 1, last_page: 1, per_page: authStore.defaultTablePageSize || 25, total: 0 })
const sort = ref('created_at')
const order = ref('desc')

/* ───── Column customization ───── */
const columnModalVisible = ref(false)
const allColumns = ref([])
const visibleColumns = ref([
  'company_name', 'field_agent', 'field_status', 'meeting_date',
  'emirates', 'sla_status',
])
const defaultVisibleColumns = [
  'company_name', 'field_agent', 'field_status', 'meeting_date',
  'emirates', 'sla_status',
]

const COLUMN_LABELS = {
  id: 'ID',
  submitted_at: 'Submission Date',
  created_at: 'Created',
  company_name: 'Company Name',
  contact_number: 'Contact Number',
  product: 'Product',
  emirates: 'Emirates',
  complete_address: 'Address',
  sales_agent: 'Sales Agent',
  team_leader: 'Team Leader',
  manager: 'Manager',
  field_agent: 'Field Agent',
  status: 'Status',
  field_status: 'Status',
  target_date: 'Target Date',
  meeting_date: 'Meeting Date',
  sla_timer: 'SLA Timer',
  sla_status: 'SLA Status',
  last_updated: 'Last Updated',
  creator: 'Created By',
}

/* ───── Computed helpers ───── */
const activeColumns = computed(() =>
  visibleColumns.value.map((key) => ({
    key,
    label: COLUMN_LABELS[key] || key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()),
  }))
)

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
  if (filters.value.status) p.status = filters.value.status
  if (filters.value.emirates) p.emirates = filters.value.emirates
  const fid = parseInt(filters.value.field_executive_id, 10)
  if (!Number.isNaN(fid) && fid > 0) p.field_executive_id = fid
  return p
})

function filterParams() {
  const p = {}
  if (filters.value.from) p.from = filters.value.from
  if (filters.value.to) p.to = filters.value.to
  if (filters.value.submitted_from) p.submitted_from = filters.value.submitted_from
  if (filters.value.submitted_to) p.submitted_to = filters.value.submitted_to
  if (filters.value.status) p.status = filters.value.status
  if (filters.value.emirates) p.emirates = filters.value.emirates
  const fid = parseInt(filters.value.field_executive_id, 10)
  if (!Number.isNaN(fid) && fid > 0) p.field_executive_id = fid
  return p
}

/* ───── Data loading ───── */
async function loadFilterOptions() {
  if (!canView.value) return
  try {
    const data = await fieldSubmissionsApi.filters()
    filterOptions.value.statuses = data.statuses ?? []
    filterOptions.value.emirates = Array.isArray(data.emirates)
      ? data.emirates.map((e) => ({ value: e, label: e }))
      : []
  } catch {
    filterOptions.value = { statuses: [], emirates: [] }
  }
}

async function loadStats() {
  if (!canView.value) return
  statsLoading.value = true
  try {
    const { data } = await api.get('/reports/field-stats', { params: filterParams() })
    stats.value = data
  } catch {
    stats.value = {
      total_requests: 0, meetings_completed: 0, cancellations: 0,
      follow_ups: 0, sla_breaches: 0,
      by_status: [], by_agent_workload: [], completion_rate_by_month: [],
    }
  } finally {
    statsLoading.value = false
  }
}

async function loadTable() {
  if (!canView.value) return
  tableLoading.value = true
  try {
    const data = await fieldSubmissionsApi.index(params.value)
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
    const data = await fieldSubmissionsApi.columns()
    allColumns.value = data.all_columns ?? []
    const visible = data.visible_columns
    if (Array.isArray(visible) && visible.length) {
      visibleColumns.value = visible
    }
  } catch {
    /* keep defaults */
  }
}

/* ───── Filters ───── */
function resetFilters() {
  filters.value = {
    from: '', to: '', submitted_from: '', submitted_to: '',
    status: '', emirates: '', field_executive_id: '',
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
    const filtered = cols.filter((c) => c !== 'id')
    await fieldSubmissionsApi.saveColumns(filtered)
    visibleColumns.value = filtered
    tableMeta.value.current_page = 1
    loadTable()
  } catch {
    /* silent */
  }
}

/* ───── Advanced filter count ───── */
const activeFilterCount = computed(() => {
  let count = 0
  if (filters.value.from) count++
  if (filters.value.to) count++
  if (filters.value.submitted_from) count++
  if (filters.value.submitted_to) count++
  if (filters.value.field_executive_id) count++
  return count
})

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
  return toDdMonYyyyLower(str) || '—'
}

function statusBadgeClass(status) {
  const s = (status || '').toLowerCase()
  if (s.includes('completed') || s === 'visited') return 'bg-brand-primary-light text-brand-primary-hover'
  if (s === 'cancelled') return 'bg-red-100 text-red-800'
  if (s === 'scheduled' || s.includes('scheduled')) return 'bg-brand-primary-light text-brand-primary-hover'
  if (s === 'rescheduled' || s.includes('follow')) return 'bg-amber-100 text-amber-800'
  return 'bg-gray-100 text-gray-700'
}

function slaBadgeClass(slaStatus) {
  const s = (slaStatus || '').toLowerCase()
  if (s.includes('breach')) return 'bg-red-100 text-red-800'
  if (s.includes('approach')) return 'bg-amber-100 text-amber-800'
  return 'bg-brand-primary-light text-brand-primary-hover'
}

function cellValue(row, key) {
  const v = row[key]
  if (v == null) return '—'
  if (key === 'submitted_at' || key === 'created_at' || key === 'meeting_date' || key === 'target_date' || key === 'last_updated') return formatDate(v)
  if (key === 'creator' && typeof v === 'object') return v.name ?? '—'
  if (typeof v === 'object' && v !== null && 'name' in v) return v.name ?? '—'
  return String(v) || '—'
}

/* ───── Chart computed ───── */
const statusBarColors = ['bg-brand-primary', 'bg-sky-500', 'bg-amber-500', 'bg-red-500', 'bg-purple-500', 'bg-cyan-500']
const agentBarColors = ['bg-brand-primary', 'bg-sky-500', 'bg-amber-500', 'bg-indigo-500', 'bg-rose-500']

const maxStatusCount = computed(() => Math.max(1, ...(stats.value.by_status || []).map((s) => s.count)))
const maxAgentCount = computed(() => Math.max(1, ...(stats.value.by_agent_workload || []).map((a) => a.count)))

/* ───── Export ───── */
function escapeCsv(val) {
  if (val == null) return ''
  const s = String(val)
  if (s.includes(',') || s.includes('"') || s.includes('\n')) return '"' + s.replace(/"/g, '""') + '"'
  return s
}

async function exportExcel() {
  if (!canExport.value) return
  exportLoading.value = true
  try {
    const exportParams = { ...params.value, page: 1, per_page: 5000 }
    const data = await fieldSubmissionsApi.index(exportParams)
    const rows = data.data ?? []
    const cols = visibleColumns.value
    const headers = cols.map((c) => COLUMN_LABELS[c] ?? c)
    const csvRows = [headers.map(escapeCsv).join(',')]
    for (const row of rows) {
      csvRows.push(cols.map((col) => escapeCsv(cellValue(row, col))).join(','))
    }
    const blob = new Blob([csvRows.join('\r\n')], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `field-operations-report-${new Date().toISOString().slice(0, 10)}.csv`
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
          <h1 class="text-2xl font-bold text-gray-900">Field Operations Reports</h1>        </div>
        <p class="text-sm text-gray-500 mt-1">Track field meetings, agent workload, and completion rates.</p>
      </div>
      <div class="flex gap-2">
        <button
          v-if="canExport"
          type="button"
          class="inline-flex items-center gap-2 rounded-lg bg-brand-primary px-4 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover disabled:opacity-70 disabled:cursor-wait"
          :disabled="exportLoading"
          @click="exportExcel"
        >
          <svg v-if="exportLoading" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" /></svg>
          <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
          {{ exportLoading ? 'Exporting…' : 'Export' }}
        </button>
      </div>
    </div>

    <div v-if="!canView" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
      You do not have permission to view reports.
    </div>

    <!-- KPI cards -->
    <div v-if="canView" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
      <div class="rounded-xl bg-gray-800 text-white p-5 shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-300">Total Field Requests</p>
            <p class="text-2xl font-bold mt-1">{{ statsLoading ? '…' : stats.total_requests.toLocaleString() }}</p>
          </div>
          <div class="rounded-full bg-brand-primary/20 p-2">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
          </div>
        </div>
      </div>
      <div class="rounded-xl bg-gray-800 text-white p-5 shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-300">Meetings Completed</p>
            <p class="text-2xl font-bold mt-1">{{ statsLoading ? '…' : stats.meetings_completed.toLocaleString() }}</p>
          </div>
          <div class="rounded-full bg-brand-primary/20 p-2">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          </div>
        </div>
      </div>
      <div class="rounded-xl bg-gray-800 text-white p-5 shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-300">Cancellations</p>
            <p class="text-2xl font-bold mt-1">{{ statsLoading ? '…' : stats.cancellations.toLocaleString() }}</p>
          </div>
          <div class="rounded-full bg-amber-500/20 p-2">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
          </div>
        </div>
      </div>
      <div class="rounded-xl bg-gray-800 text-white p-5 shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-300">Follow-ups</p>
            <p class="text-2xl font-bold mt-1">{{ statsLoading ? '…' : stats.follow_ups.toLocaleString() }}</p>
          </div>
          <div class="rounded-full bg-brand-primary/20 p-2">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
          </div>
        </div>
      </div>
      <div class="rounded-xl bg-gray-800 text-white p-5 shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-300">SLA Breaches</p>
            <p class="text-2xl font-bold mt-1">{{ statsLoading ? '…' : stats.sla_breaches.toLocaleString() }}</p>
          </div>
          <div class="rounded-full bg-red-500/20 p-2">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Charts row -->
    <div v-if="canView" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Status Distribution -->
      <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Status Distribution</h3>
        <div v-if="statsLoading" class="py-8 text-center text-gray-400 text-sm">Loading…</div>
        <div v-else class="space-y-3">
          <div v-for="(s, idx) in (stats.by_status || [])" :key="s.name" class="flex items-center gap-3">
            <span class="w-28 text-sm text-gray-600 truncate">{{ s.name }}</span>
            <div class="flex-1 h-6 bg-gray-100 rounded-full overflow-hidden">
              <div class="h-full rounded-full transition-all duration-500" :class="statusBarColors[idx % statusBarColors.length]" :style="{ width: (100 * s.count / maxStatusCount) + '%' }" />
            </div>
            <span class="text-sm font-semibold text-gray-900 w-12 text-right">{{ s.count.toLocaleString() }}</span>
          </div>
          <p v-if="!(stats.by_status || []).length" class="text-sm text-gray-400 text-center py-4">No data available</p>
        </div>
      </div>

      <!-- Field Agent Workload -->
      <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Field Agent Workload</h3>
        <div v-if="statsLoading" class="py-8 text-center text-gray-400 text-sm">Loading…</div>
        <div v-else class="space-y-3">
          <div v-for="(a, idx) in (stats.by_agent_workload || [])" :key="a.name" class="flex items-center gap-3">
            <span class="w-32 text-sm text-gray-600 truncate">{{ a.name }}</span>
            <div class="flex-1 h-6 bg-gray-100 rounded-full overflow-hidden">
              <div class="h-full rounded-full transition-all duration-500" :class="agentBarColors[idx % agentBarColors.length]" :style="{ width: (100 * a.count / maxAgentCount) + '%' }" />
            </div>
            <span class="text-sm font-semibold text-gray-900 w-12 text-right">{{ a.count.toLocaleString() }}</span>
          </div>
          <p v-if="!(stats.by_agent_workload || []).length" class="text-sm text-gray-400 text-center py-4">No data available</p>
        </div>
      </div>

      <!-- Meeting Completion Rate -->
      <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Meeting Completion Rate</h3>
        <div v-if="statsLoading" class="py-8 text-center text-gray-400 text-sm">Loading…</div>
        <div v-else class="space-y-3">
          <div v-for="m in (stats.completion_rate_by_month || []).slice(-6)" :key="m.label" class="flex items-center gap-3">
            <span class="w-20 text-sm text-gray-600">{{ m.label }}</span>
            <div class="flex-1 h-6 bg-gray-100 rounded-full overflow-hidden">
              <div class="h-full bg-brand-primary rounded-full transition-all duration-500" :style="{ width: (m.pct || 0) + '%' }" />
            </div>
            <span class="text-sm font-semibold text-gray-900 w-12 text-right">{{ m.pct }}%</span>
          </div>
          <p v-if="!(stats.completion_rate_by_month || []).length" class="text-sm text-gray-400 text-center py-4">No data available</p>
        </div>
      </div>
    </div>

    <!-- Filters Section (below charts) -->
    <div v-if="canView" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
      <div class="flex flex-wrap items-end gap-4">
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
          <select v-model="filters.status" class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-44 focus:ring-brand-primary focus:border-brand-primary">
            <option value="">All Status</option>
            <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Emirates</label>
          <select v-model="filters.emirates" class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-44 focus:ring-brand-primary focus:border-brand-primary">
            <option value="">All Emirates</option>
            <option v-for="e in filterOptions.emirates" :key="e.value" :value="e.value">{{ e.label }}</option>
          </select>
        </div>
        <button type="button" class="ml-auto rounded-lg bg-brand-primary px-5 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover" @click="applyFilters">Apply</button>
        <button type="button" class="rounded-lg border border-gray-300 bg-white px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="resetFilters">Reset</button>
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
            @click="advancedVisible = !advancedVisible"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
            Advanced Filters
            <span v-if="activeFilterCount > 0" class="ml-1 inline-flex h-5 w-5 items-center justify-center rounded-full bg-brand-primary text-[10px] font-bold text-white">{{ activeFilterCount }}</span>
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
            <input v-model="filters.from" type="date" class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-40 focus:ring-brand-primary focus:border-brand-primary" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Date To</label>
            <input v-model="filters.to" type="date" class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-40 focus:ring-brand-primary focus:border-brand-primary" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Submitted From</label>
            <input v-model="filters.submitted_from" type="date" class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-40 focus:ring-brand-primary focus:border-brand-primary" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Submitted To</label>
            <input v-model="filters.submitted_to" type="date" class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-40 focus:ring-brand-primary focus:border-brand-primary" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Field Agent ID</label>
            <input v-model="filters.field_executive_id" type="text" placeholder="Agent ID" class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-36 focus:ring-brand-primary focus:border-brand-primary" />
          </div>
        </div>
      </div>
    </div>

    <!-- Table Section -->
    <div v-if="canView" class="rounded-xl border-2 border-black bg-white shadow-sm overflow-hidden">
      <!-- Table header bar -->
      <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-b-2 border-black bg-white">
        <h2 class="text-base font-semibold text-gray-900">Field Submissions Details</h2>
        <p class="text-sm text-gray-500">{{ tableMeta.total }} records</p>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="min-w-full">
          <thead class="bg-brand-primary border-b-2 border-green-700">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider border-b-2 border-black">#</th>
              <th
                v-for="col in activeColumns"
                :key="col.key"
                class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider cursor-pointer select-none hover:bg-white/10 transition-colors border-b-2 border-black"
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
                <!-- Status badge (field_status) -->
                <template v-if="col.key === 'field_status' || col.key === 'status'">
                  <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusBadgeClass(row[col.key])]">
                    {{ row[col.key] || '—' }}
                  </span>
                </template>
                <!-- SLA badge -->
                <template v-else-if="col.key === 'sla_status'">
                  <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', slaBadgeClass(row.sla_status)]">
                    {{ row.sla_status || '—' }}
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
      :visible="columnModalVisible"
      :all-columns="allColumns"
      :visible-columns="visibleColumns"
      :default-columns="defaultVisibleColumns"
      @update:visible="columnModalVisible = $event"
      @save="onSaveColumns"
    />
  </div>
</template>
