<script setup>
/**
 * Customer Support Requests listing – same design as Field / Lead Submissions.
 */
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import customerSupportApi from '@/services/customerSupportApi'
import api from '@/lib/axios'
import AssignModal from '@/components/AssignModal.vue'
import FiltersBar from '@/components/customer-support/FiltersBar.vue'
import AdvancedFilters from '@/components/customer-support/AdvancedFilters.vue'
import ColumnCustomizerModal from '@/components/lead-submissions/ColumnCustomizerModal.vue'
import CustomerSupportTable from '@/components/customer-support/CustomerSupportTable.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import RecordHistoryModal from '@/components/RecordHistoryModal.vue'
import Toast from '@/components/Toast.vue'
import { debounce } from '@/composables/useApiRequest'
import { useFilterCache } from '@/composables/useFilterCache'

const router = useRouter()
const historyModalVisible = ref(false)
const historyRecordId = ref(null)
const historyRecordLabel = ref('')
function onResubmit(row) {
  if (!row?.id) return
  router.push(`/customer-support/${row.id}/edit?resubmit=1`)
}

function openHistoryModal(row) {
  if (!row?.id) return
  historyRecordId.value = row.id
  historyRecordLabel.value = row.company_name || `Support Request #${row.id}`
  historyModalVisible.value = true
}
function closeHistoryModal() {
  historyModalVisible.value = false
  historyRecordId.value = null
  historyRecordLabel.value = ''
}
async function fetchSupportAudits(id) {
  return await customerSupportApi.getAudits(id)
}

const auth = useAuthStore()
let listAbortController = null
const { loadBootstrap: loadCachedBootstrap, invalidate: invalidateFilterCache } = useFilterCache('customer-support')
const loading = ref(true)
const filterOptions = ref({
  statuses: [],
  issue_categories: [],
  managers: [],
  team_leaders: [],
  sales_agents: [],
})
const submissions = ref([])
const TABLE_MODULE = 'customer-support'
const meta = ref({ current_page: 1, last_page: 1, per_page: auth.defaultTablePageSize || 25, total: 0 })
const perPageOptions = ref([10, 20, 25, 50, 100])
const allColumns = ref([])
const visibleColumns = ref([
  'id', 'submitted_at', 'ticket_number', 'account_number', 'company_name', 'issue_category',
  'contact_number', 'creator', 'csr', 'status', 'workflow_status',
  'pending', 'completion_date', 'updated_at',
  'trouble_ticket', 'activity', 'resolution_remarks', 'internal_remarks',
  'attachments', 'manager', 'team_leader', 'sales_agent',
])
const sort = ref('submitted_at')
const order = ref('desc')
const advancedVisible = ref(false)
const columnModalVisible = ref(false)
const exportLoading = ref(false)

const assignModalVisible = ref(false)
const assignRow = ref(null)
const assignBulkIds = ref([])
const selectedIds = ref([])
const bulkAssignMessage = ref('')

const showToast = ref(false)
const toastType = ref('success')
const toastMsg = ref('')
function toast(t, m) { toastType.value = t; toastMsg.value = m; showToast.value = true }

const canBulkAssign = (() => {
  const roles = auth.user?.roles ?? []
  if (!Array.isArray(roles)) return false
  return roles.some((r) => {
    const name = typeof r === 'string' ? r : r?.name
    return name === 'superadmin' || name === 'customer_support_representative' || name === 'support_manager'
  })
})()

const filters = ref({
  q: '',
  company_name: '',
  account_number: '',
  contact_number: '',
  issue_category: '',
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
  if (f.account_number) p.account_number = f.account_number
  if (f.contact_number) p.contact_number = f.contact_number
  if (f.issue_category) p.issue_category = f.issue_category
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
  submitted_at: 'Submission Date',
  ticket_number: 'Ticket ID',
  account_number: 'Account Number',
  company_name: 'Company Name',
  issue_category: 'Issue Category',
  contact_number: 'Contact Number',
  issue_description: 'Issue Description',
  attachments: 'Attachments',
  creator: 'Submitted By',
  csr: 'CSR Name',
  manager: 'Manager',
  team_leader: 'Team Leader',
  sales_agent: 'Sales Agent',
  status: 'Status',
  workflow_status: 'SLA Status',
  completion_date: 'Completion Date',
  updated_at: 'Last Updated',
  created_at: 'Created',
  trouble_ticket: 'Trouble Ticket',
  activity: 'Activity',
  pending: 'Pending With',
  resolution_remarks: 'Resolution Remarks',
  internal_remarks: 'Internal Remarks',
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
    if (typeof v === 'object' && v !== null && 'name' in v) return v.name ?? ''
    return v
  })
}

async function onExport() {
  const params = { ...buildParams(), page: 1, per_page: 100 }
  exportLoading.value = true
  try {
    const data = await customerSupportApi.index(params)
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
    a.download = `customer-support-requests-${new Date().toISOString().slice(0, 10)}.csv`
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
    const data = await customerSupportApi.index(buildParams(), { signal })
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
    const data = await customerSupportApi.filters()
    filterOptions.value = {
      statuses: data.statuses ?? [],
      issue_categories: data.issue_categories ?? [],
      managers: data.managers ?? [],
      team_leaders: data.team_leaders ?? [],
      sales_agents: data.sales_agents ?? [],
    }
  } catch {
    //
  }
}

async function loadColumns() {
  try {
    const data = await customerSupportApi.columns()
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
    account_number: '',
    contact_number: '',
    issue_category: '',
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
    await customerSupportApi.saveColumns(cols)
    visibleColumns.value = cols
    meta.value.current_page = 1
    load()
  } catch {
    //
  }
}

const COLUMN_TO_API_FIELD = {
  manager: 'manager_id',
  team_leader: 'team_leader_id',
  sales_agent: 'sales_agent_id',
}

async function onUpdateCell(submissionId, field, value) {
  const apiField = COLUMN_TO_API_FIELD[field] ?? field
  const row = submissions.value.find((r) => r.id === submissionId)
  const prev = row ? { ...row } : null
  const payload = { [apiField]: value }
  if (row) {
    if (field === 'manager') {
      row.manager_id = value
      row.manager = filterOptions.value.managers.find((m) => m.id === value)?.name ?? row.manager
    } else if (field === 'team_leader') {
      row.team_leader_id = value
      row.team_leader = filterOptions.value.team_leaders.find((t) => t.id === value)?.name ?? row.team_leader
    } else if (field === 'sales_agent') {
      row.sales_agent_id = value
      row.sales_agent = filterOptions.value.sales_agents.find((s) => s.id === value)?.name ?? row.sales_agent
    } else {
      row[field] = value
    }
  }
  try {
    const res = await customerSupportApi.updateSubmissionFields(submissionId, payload)
    if (res?.row && row) Object.assign(row, res.row)
  } catch {
    if (prev) Object.assign(row, prev)
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

function onAssignModalSaved() {
  toast('success', 'CSR assigned successfully.')
  assignRow.value = null
  assignBulkIds.value = []
  selectedIds.value = []
  load()
}

async function loadCsrOptions() {
  const res = await customerSupportApi.getCsrOptions()
  return res?.csrs ?? []
}

async function onAssignSingle(row, csrId) {
  await customerSupportApi.assignCsr(row.id, csrId)
}

async function onAssignBulk(ids, csrId) {
  await customerSupportApi.bulkAssign(ids, { csr_id: csrId })
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
  debouncedSearch.cancel()
})

onMounted(async () => {
  // Try aggregated bootstrap endpoint (1 request instead of 4+)
  const bootstrapResult = await loadCachedBootstrap(buildParams())
  if (bootstrapResult) {
    const fd = bootstrapResult.filters ?? {}
    const team = bootstrapResult.team_options ?? {}
    const csr = bootstrapResult.csr_options ?? {}
    filterOptions.value = {
      statuses: fd.statuses ?? [],
      issue_categories: fd.issue_categories ?? [],
      managers: team.managers ?? [],
      team_leaders: team.team_leaders ?? [],
      sales_agents: team.sales_agents ?? [],
      csrs: csr.csrs ?? [],
    }
    const cd = bootstrapResult.columns ?? {}
    if (cd.all_columns) allColumns.value = cd.all_columns
    if (cd.visible_columns) visibleColumns.value = cd.visible_columns ?? visibleColumns.value
    const pd = bootstrapResult.page ?? {}
    submissions.value = pd.data ?? []
    meta.value = pd.meta ?? meta.value
    loading.value = false
    // No separate loadFilters() needed - bootstrap has everything
  } else {
    // Fallback: individual requests (parallel)
    await loadTablePreference()
    loadFilters()
    loadColumns()
    load()
  }
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-white pt-4">
    <div class="w-full space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-baseline gap-2">
          <h1 class="text-xl font-semibold text-gray-900 leading-tight">Customer Support Requests</h1>
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
            Bulk Assign
          </button>
          <button
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
        <CustomerSupportTable
          :columns="visibleColumns"
          :data="submissions"
          :sort="sort"
          :order="order"
          :loading="loading"
          :current-page="meta.current_page"
          :per-page="meta.per_page"
          :edit-options="filterOptions"
          :can-bulk-assign="canBulkAssign"
          v-model:selected-ids="selectedIds"
          @sort="onSort"
          @update-cell="onUpdateCell"
          @open-assign="openAssignModal"
          @view-history="openHistoryModal"
          @resubmit="onResubmit"
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

    <RecordHistoryModal
      :visible="historyModalVisible"
      :record-id="historyRecordId"
      :record-label="historyRecordLabel"
      module-name="Customer Support"
      :fetch-fn="fetchSupportAudits"
      @close="closeHistoryModal"
    />

    <AssignModal
      :visible="assignModalVisible"
      :row="assignRow"
      :bulk-ids="assignBulkIds"
      title="Assign to CSR"
      bulk-title="Assign {count} request(s) to CSR"
      select-label="Select CSR"
      :load-options="loadCsrOptions"
      :on-assign-single="onAssignSingle"
      :on-assign-bulk="onAssignBulk"
      @close="onAssignModalClose"
      @saved="onAssignModalSaved"
    />

    <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="4000" @dismiss="showToast = false" />
  </div>
</template>
