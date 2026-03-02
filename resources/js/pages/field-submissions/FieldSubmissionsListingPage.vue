<script setup>
/**
 * Field Submissions Listing – same design and functionality as Lead Submissions.
 */
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import fieldSubmissionsApi from '@/services/fieldSubmissionsApi'
import FiltersBar from '@/components/field-submissions/FiltersBar.vue'
import AdvancedFilters from '@/components/field-submissions/AdvancedFilters.vue'
import ColumnCustomizerModal from '@/components/lead-submissions/ColumnCustomizerModal.vue'
import AssignModal from '@/components/AssignModal.vue'
import FieldTable from '@/components/field-submissions/FieldTable.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import api from '@/lib/axios'
import Toast from '@/components/Toast.vue'
import RecordHistoryModal from '@/components/RecordHistoryModal.vue'
import { debounce } from '@/composables/useApiRequest'
import { useFilterCache } from '@/composables/useFilterCache'
import { canModuleAction } from '@/lib/accessControl'

const auth = useAuthStore()
let listAbortController = null
const { loadBootstrap: loadCachedBootstrap, invalidate: invalidateFilterCache } = useFilterCache('field-submissions')
const loading = ref(true)
const filterOptions = ref({
  statuses: [],
  products: [],
  emirates: [],
  managers: [],
  teamLeaders: [],
  salesAgents: [],
  field_executives: [],
})
const submissions = ref([])
const TABLE_MODULE = 'field-submissions'
const meta = ref({ current_page: 1, last_page: 1, per_page: auth.defaultTablePageSize || 25, total: 0 })
const perPageOptions = ref([10, 20, 25, 50, 100])
const allColumns = ref([])
const visibleColumns = ref([
  'id', 'created_at', 'company_name', 'contact_number', 'product', 'emirates', 'complete_address',
  'sales_agent', 'team_leader', 'manager', 'field_agent', 'field_status', 'target_date', 'sla_timer', 'sla_status', 'last_updated', 'creator',
])
const assignModalVisible = ref(false)
const assignRow = ref(null)
/** For bulk assign: submission IDs. When set, modal runs in bulk mode. */
const assignBulkIds = ref([])
const selectedIds = ref([])
const bulkAssignMessage = ref('')

const showToast = ref(false)
const toastType = ref('success')
const toastMsg  = ref('')
function toast(t, m) { toastType.value = t; toastMsg.value = m; showToast.value = true }

const historyModalVisible = ref(false)
const historyRecordId = ref(null)
const historyRecordLabel = ref('')
function openHistoryModal(row) {
  if (!row?.id) return
  historyRecordId.value = row.id
  historyRecordLabel.value = row.company_name || `Field Submission #${row.id}`
  historyModalVisible.value = true
}
function closeHistoryModal() {
  historyModalVisible.value = false
  historyRecordId.value = null
  historyRecordLabel.value = ''
}
async function fetchFieldAudits(id) {
  return await fieldSubmissionsApi.getAudits(id)
}

const canBulkAssign = (() => {
  const roles = auth.user?.roles ?? []
  const perms = auth.user?.permissions ?? []
  const isSuperAdmin = Array.isArray(roles) && roles.some((r) => (typeof r === 'string' ? r : r?.name) === 'superadmin')
  if (isSuperAdmin) return true
  return perms.includes('field_head.assign_field_agent') || perms.includes('field-submissions.assign_field_agent')
})()
const canExport = () => canModuleAction(auth.user, 'field', 'export')
const sort = ref('created_at')
const order = ref('desc')
const advancedVisible = ref(false)
const columnModalVisible = ref(false)
const exportLoading = ref(false)

const filters = ref({
  q: '',
  company_name: '',
  product: '',
  emirates: '',
  status: '',
  from: '',
  to: '',
  submitted_from: '',
  submitted_to: '',
  sales_agent_id: null,
  team_leader_id: null,
  manager_id: null,
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
  if (f.q) p.q = f.q
  if (f.company_name) p.company_name = f.company_name
  if (f.product) p.product = f.product
  if (f.emirates) p.emirates = f.emirates
  if (f.status) p.status = f.status
  if (f.from) p.from = f.from
  if (f.to) p.to = f.to
  if (f.submitted_from) p.submitted_from = f.submitted_from
  if (f.submitted_to) p.submitted_to = f.submitted_to
  if (f.sales_agent_id) p.sales_agent_id = f.sales_agent_id
  if (f.team_leader_id) p.team_leader_id = f.team_leader_id
  if (f.manager_id) p.manager_id = f.manager_id
  return p
}

const COLUMN_LABELS = {
  id: 'ID',
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
  sla_timer: 'SLA Timer',
  sla_status: 'SLA Status',
  last_updated: 'Last Updated',
  creator: 'Created By',
}

function escapeCsv(val) {
  if (val == null) return ''
  const s = String(val)
  if (s.includes(',') || s.includes('"') || s.includes('\n')) return '"' + s.replace(/"/g, '""') + '"'
  return s
}

function rowToCsvCells(row, columns) {
  return columns.map((col) => {
    const v = row[col]
    if (v == null) return ''
    if (col === 'creator' && v && typeof v === 'object') return v.name ?? ''
    if (typeof v === 'object' && v !== null && 'name' in v) return v.name ?? ''
    return v
  })
}

async function onExport() {
  const params = { ...buildParams(), page: 1, per_page: 100 }
  exportLoading.value = true
  try {
    const data = await fieldSubmissionsApi.index(params)
    const rows = data.data ?? []
    const cols = visibleColumns.value
    const headers = cols.map((c) => COLUMN_LABELS[c] ?? c)
    const csvRows = [headers.map(escapeCsv).join(',')]
    for (const row of rows) {
      csvRows.push(rowToCsvCells(row, cols).map(escapeCsv).join(','))
    }
    const blob = new Blob([csvRows.join('\r\n')], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `field-submissions-${new Date().toISOString().slice(0, 10)}.csv`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    //
  } finally {
    exportLoading.value = false
  }
}

async function load() {
  window.scrollTo(0, 0)
  // Cancel any in-flight list request before starting a new one
  if (listAbortController) listAbortController.abort()
  listAbortController = new AbortController()
  const { signal } = listAbortController

  loading.value = true
  try {
    const data = await fieldSubmissionsApi.index(buildParams(), { signal })
    submissions.value = data.data ?? []
    meta.value = data.meta ?? meta.value
  } catch (err) {
    if (err?.name === 'CanceledError' || err?.code === 'ERR_CANCELED') return
    throw err
  } finally {
    loading.value = false
    window.scrollTo(0, 0)
  }
}

async function loadFilters() {
  try {
    const [filtersRes, teamRes, editOptionsRes] = await Promise.all([
      fieldSubmissionsApi.filters(),
      fieldSubmissionsApi.getTeamOptions().catch(() => ({ data: {} })),
      fieldSubmissionsApi.getEditOptions().catch(() => ({})),
    ])
    const data = filtersRes
    const team = teamRes?.data ?? teamRes ?? {}
    const editOpt = editOptionsRes?.data ?? editOptionsRes ?? {}
    filterOptions.value = {
      statuses: data.statuses ?? [],
      products: data.products ?? [],
      emirates: data.emirates ?? [],
      managers: team.managers ?? [],
      teamLeaders: team.team_leaders ?? [],
      salesAgents: team.sales_agents ?? [],
      field_executives: team.field_executives ?? [],
      field_statuses: editOpt.field_statuses ?? [],
    }
  } catch {
    //
  }
}

async function loadColumns() {
  try {
    const data = await fieldSubmissionsApi.columns()
    allColumns.value = data.all_columns ?? []
    visibleColumns.value = data.visible_columns ?? visibleColumns.value
  } catch {
    //
  }
}

function applyFilters() {
  meta.value.current_page = 1
  load()
}

function resetFilters() {
  filters.value = {
    q: '',
    company_name: '',
    product: '',
    emirates: '',
    status: '',
    from: '',
    to: '',
    submitted_from: '',
    submitted_to: '',
    sales_agent_id: null,
    team_leader_id: null,
    manager_id: null,
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
    await fieldSubmissionsApi.saveColumns(cols)
    visibleColumns.value = cols
    meta.value.current_page = 1
    load()
  } catch {
    //
  }
}

function formatDateForDisplay(d) {
  if (!d) return null
  const date = d instanceof Date ? d : new Date(d)
  const day = String(date.getDate()).padStart(2, '0')
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  return `${day}-${months[date.getMonth()]}-${date.getFullYear()}`
}

/** Map display column name to API field name for updateSubmissionFields. */
const COLUMN_TO_API_FIELD = {
  manager: 'manager_id',
  team_leader: 'team_leader_id',
  sales_agent: 'sales_agent_id',
  field_agent: 'field_executive_id',
  target_date: 'meeting_date',
}

async function onUpdateCell(submissionId, field, value) {
  const apiField = COLUMN_TO_API_FIELD[field] ?? field
  const row = submissions.value.find((r) => r.id === submissionId)
  const prev = row ? { ...row } : null
  const payload = {}
  if (apiField === 'meeting_date') {
    payload.meeting_date = value || null
    if (row) {
      row.target_date = value ? new Date(value).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : null
      row.meeting_date = value
    }
  } else {
    payload[apiField] = value
    if (row) {
      if (field === 'manager') {
        row.manager_id = value
        row.manager = filterOptions.value.managers.find((m) => m.id === value)?.name ?? row.manager
      } else if (field === 'team_leader') {
        row.team_leader_id = value
        row.team_leader = filterOptions.value.teamLeaders.find((t) => t.id === value)?.name ?? row.team_leader
      } else if (field === 'sales_agent') {
        row.sales_agent_id = value
        row.sales_agent = filterOptions.value.salesAgents.find((s) => s.id === value)?.name ?? row.sales_agent
      } else if (field === 'field_agent') {
        row.field_executive_id = value
        row.field_agent = value == null ? 'Unassigned' : (filterOptions.value.field_executives.find((e) => e.id === value)?.name ?? row.field_agent)
      } else if (field === 'field_status') {
        row.field_status = value
      } else {
        row[field] = value
      }
    }
  }
  try {
    const res = await fieldSubmissionsApi.updateSubmissionFields(submissionId, payload)
    if (res?.row && row) Object.assign(row, res.row)
  } catch {
    if (prev) Object.assign(row, prev)
    load()
  }
}

async function onUpdateStatus(id, newStatus) {
  const row = submissions.value.find((r) => r.id === id)
  const prevStatus = row?.status
  const prevSubmittedAt = row?.submitted_at
  if (row) {
    row.status = newStatus
    if (newStatus === 'submitted') row.submitted_at = formatDateForDisplay(new Date())
  }
  try {
    const res = await fieldSubmissionsApi.updateStatus(id, newStatus)
    if (row) {
      row.status = res.status
      row.submitted_at = res.submitted_at ?? row.submitted_at
    }
  } catch {
    if (row) {
      row.status = prevStatus
      row.submitted_at = prevSubmittedAt
    }
    load()
  }
}

function onPageChange(page) {
  meta.value.current_page = page
  load()
}

async function onPerPageChange(e) {
  const val = Number(e.target.value)
  meta.value.per_page = val
  meta.value.current_page = 1
  load()
  try { await api.post(`/table-preferences/${TABLE_MODULE}`, { per_page: val }) } catch { /* silent */ }
}

async function loadTablePreference() {
  try {
    const { data } = await api.get(`/table-preferences/${TABLE_MODULE}`)
    if (data.per_page) meta.value.per_page = Number(data.per_page)
    if (Array.isArray(data.options) && data.options.length) perPageOptions.value = data.options
  } catch { /* use system default */ }
}

function openAssignModal(row) {
  if (!row) return
  assignRow.value = row
  assignBulkIds.value = []
  assignModalVisible.value = true
}

function openBulkAssign() {
  bulkAssignMessage.value = ''
  if (selectedIds.value.length === 0) {
    bulkAssignMessage.value = 'Please select at least one row.'
    return
  }
  assignRow.value = null
  assignBulkIds.value = [...selectedIds.value]
  assignModalVisible.value = true
}

function onAssignModalClose() {
  assignModalVisible.value = false
  assignRow.value = null
  assignBulkIds.value = []
}

let _assignPollTimer = null
function onAssignModalSaved(result) {
  const wasBulk = assignBulkIds.value.length > 0
  const bulkCount = assignBulkIds.value.length
  toast('success', wasBulk
    ? `We are assigning field agent to ${bulkCount} submission(s). You can continue working.`
    : 'Field agent assigned successfully.')
  assignRow.value = null
  assignBulkIds.value = []
  selectedIds.value = []
  load()
  if (wasBulk && result?.tracking_id) {
    let attempts = 0
    if (_assignPollTimer) clearInterval(_assignPollTimer)
    _assignPollTimer = setInterval(async () => {
      attempts++
      try {
        const status = await fieldSubmissionsApi.bulkAssignStatus(result.tracking_id)
        if (status?.status === 'completed') {
          clearInterval(_assignPollTimer)
          _assignPollTimer = null
          load()
          toast('success', `${bulkCount} submission(s) assigned successfully.`)
        } else if (status?.status === 'failed') {
          clearInterval(_assignPollTimer)
          _assignPollTimer = null
          load()
          toast('error', 'Bulk assignment failed. Please try again.')
        }
      } catch { /* ignore polling errors */ }
      if (attempts >= 20) {
        clearInterval(_assignPollTimer)
        _assignPollTimer = null
        load()
      }
    }, 3000)
  }
}

let _cachedAgentOptions = null
async function loadFieldAgentOptions() {
  if (_cachedAgentOptions) return _cachedAgentOptions
  const res = await fieldSubmissionsApi.getFieldAgentOptions()
  _cachedAgentOptions = res?.agents ?? []
  return _cachedAgentOptions
}

async function onAssignSingle(row, agentId) {
  await fieldSubmissionsApi.assignFieldTechnician(row.id, agentId)
}

async function onAssignBulk(ids, agentId) {
  const res = await fieldSubmissionsApi.bulkAssign(ids, { field_executive_id: agentId })
  return res
}


watch(selectedIds, (ids) => {
  if (ids && ids.length > 0) bulkAssignMessage.value = ''
}, { deep: true })

// Debounced search: waits 400ms after user stops typing before hitting API
const debouncedSearch = debounce(() => {
  meta.value.current_page = 1
  load()
}, 400)

// Watch the search query field to trigger debounced load
watch(() => filters.value.q, (newVal, oldVal) => {
  if (newVal !== oldVal) debouncedSearch()
})

// Cleanup on unmount
onUnmounted(() => {
  if (listAbortController) listAbortController.abort()
  if (_assignPollTimer) clearInterval(_assignPollTimer)
  debouncedSearch.cancel()
})

onMounted(async () => {
  // Try aggregated bootstrap endpoint (1 request instead of 4+)
  const bootstrapResult = await loadCachedBootstrap(buildParams())
  if (bootstrapResult) {
    const fd = bootstrapResult.filters ?? {}
    const team = bootstrapResult.team_options ?? {}
    filterOptions.value = {
      statuses: fd.statuses ?? [],
      products: fd.products ?? [],
      emirates: fd.emirates ?? [],
      managers: team.managers ?? [],
      teamLeaders: team.team_leaders ?? [],
      salesAgents: team.sales_agents ?? [],
      field_executives: team.field_executives ?? [],
      field_statuses: bootstrapResult.field_statuses ?? [],
    }
    const cd = bootstrapResult.columns ?? {}
    if (cd.all_columns) allColumns.value = cd.all_columns
    const requestedCols = [...visibleColumns.value]
    if (cd.visible_columns) visibleColumns.value = cd.visible_columns ?? visibleColumns.value
    const pd = bootstrapResult.page ?? {}
    submissions.value = pd.data ?? []
    meta.value = pd.meta ?? meta.value
    loading.value = false
    const colsChanged = visibleColumns.value.length !== requestedCols.length || visibleColumns.value.some(c => !requestedCols.includes(c))
    if (colsChanged) load()
  } else {
    // Fallback: individual requests (parallel)
    await loadTablePreference()
    loadFilters()
    loadColumns()
    load()
  }

  // Pre-warm agent options cache so the assign modal opens instantly
  loadFieldAgentOptions().catch(() => {})
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-white py-3 pr-4">
    <div class="w-full space-y-3">
      <!-- Top: breadcrumbs + title (left), Bulk Assign + Export (right) -->
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-baseline gap-2">
          <h1 class="text-xl font-semibold text-gray-900 leading-tight ml-4">Field Submissions</h1>
          <Breadcrumbs />
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button
            v-if="canBulkAssign"
            type="button"
            class="inline-flex items-center rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
            title="Bulk Assign"
            @click="openBulkAssign"
          >
            <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            Bulk Assign
          </button>
          <button
            v-if="canExport()"
            type="button"
            class="inline-flex items-center rounded bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-70 disabled:cursor-wait"
            :disabled="loading || exportLoading"
            @click="onExport"
          >
            <svg
              v-if="exportLoading"
              class="mr-1.5 h-4 w-4 animate-spin"
              fill="none"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
            </svg>
            <svg
              v-else
              class="mr-1.5 h-4 w-4"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            {{ exportLoading ? 'Exporting...' : 'Export' }}
          </button>
        </div>
      </div>
      <div
        v-if="bulkAssignMessage"
        class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-800"
        role="alert"
      >
        {{ bulkAssignMessage }}
      </div>

      <div
        v-if="exportLoading"
        class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm text-green-800"
        role="status"
        aria-live="polite"
      >
        <svg class="h-5 w-5 shrink-0 animate-spin text-green-600" fill="none" viewBox="0 0 24 24" aria-hidden="true">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
        </svg>
        <span>Exporting… Your CSV will download when ready.</span>
      </div>

      <FiltersBar
        :filters="filters"
        :filter-options="filterOptions"
        :loading="loading"
        @apply="applyFilters"
        @reset="resetFilters"
      >
        <template #after-reset>
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
            @click="advancedVisible = !advancedVisible"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
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
        </template>
      </FiltersBar>

      <AdvancedFilters
        :visible="advancedVisible"
        :filters="filters"
        :filter-options="filterOptions"
        :loading="loading"
        @apply="applyFilters"
        @reset="resetFilters"
      />

      <div class="overflow-hidden rounded-lg border-2 border-black bg-white shadow-sm">
        <FieldTable
          :columns="visibleColumns"
          :data="submissions"
          :sort="sort"
          :order="order"
          :loading="loading"
          :current-page="meta.current_page"
          :per-page="meta.per_page"
          :edit-options="filterOptions"
          v-model:selected-ids="selectedIds"
          @sort="onSort"
          @update-status="onUpdateStatus"
          @update-cell="onUpdateCell"
          @assign-technician="openAssignModal"
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
      @update:visible="columnModalVisible = $event"
      @save="onSaveColumns"
    />
    <AssignModal
      :visible="assignModalVisible"
      :row="assignRow"
      :bulk-ids="assignBulkIds"
      title="Assign to Field Agent"
      bulk-title="Assign {count} submission(s) to Field Agent"
      select-label="Select Field Agent"
      :load-options="loadFieldAgentOptions"
      :on-assign-single="onAssignSingle"
      :on-assign-bulk="onAssignBulk"
      @close="onAssignModalClose"
      @saved="onAssignModalSaved"
    />

    <RecordHistoryModal
      :visible="historyModalVisible"
      :record-id="historyRecordId"
      :record-label="historyRecordLabel"
      module-name="Field Submissions"
      :fetch-fn="fetchFieldAudits"
      @close="closeHistoryModal"
    />

    <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="4000" @dismiss="showToast = false" />
  </div>
</template>
