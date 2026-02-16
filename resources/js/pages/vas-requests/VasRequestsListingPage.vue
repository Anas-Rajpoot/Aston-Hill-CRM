<script setup>
/**
 * VAS Requests listing – same design as Field / Lead / Customer Support.
 */
import { ref, onMounted, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import vasRequestsApi from '@/services/vasRequestsApi'

const auth = useAuthStore()
import FiltersBar from '@/components/vas-requests/FiltersBar.vue'
import AdvancedFilters from '@/components/vas-requests/AdvancedFilters.vue'
import ColumnCustomizerModal from '@/components/lead-submissions/ColumnCustomizerModal.vue'
import VasRequestTable from '@/components/vas-requests/VasRequestTable.vue'
import AssignBackOfficeModal from '@/components/vas-requests/AssignBackOfficeModal.vue'
import Pagination from '@/components/Pagination.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import Toast from '@/components/Toast.vue'

const loading = ref(true)
const selectedSubmissionIds = ref([])
const bulkAssignMessage = ref('')
const assignModalVisible = ref(false)
const assignVasRow = ref(null)
const assignBulkIds = ref([])
const loadError = ref(null)

const showToast = ref(false)
const toastType = ref('success')
const toastMsg  = ref('')
function toast(t, m) { toastType.value = t; toastMsg.value = m; showToast.value = true }
const filterOptions = ref({
  statuses: [],
  request_types: [],
  managers: [],
  team_leaders: [],
  sales_agents: [],
  executives: [],
})
const submissions = ref([])
const meta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 })
const allColumns = ref([])
const visibleColumns = ref([
  'id', 'submitted_at', 'request_type', 'account_number', 'company_name',
  'manager', 'team_leader', 'sales_agent', 'executive', 'status', 'creator',
])
const sort = ref('submitted_at')
const order = ref('desc')
const advancedVisible = ref(false)
const columnModalVisible = ref(false)
const exportLoading = ref(false)

const filters = ref({
  q: '',
  company_name: '',
  account_number: '',
  request_type: '',
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
  if (f.request_type) p.request_type = f.request_type
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
  created_at: 'Created',
  request_type: 'Request Type',
  account_number: 'Account Number',
  company_name: 'Company Name',
  description: 'Description',
  manager: 'Manager',
  team_leader: 'Team Leader',
  sales_agent: 'Sales Agent',
  executive: 'Back Office Executive',
  status: 'Status',
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
    if (typeof v === 'object' && v !== null && 'name' in v) return v.name ?? ''
    return v
  })
}

async function onExport() {
  const params = { ...buildParams(), page: 1, per_page: 100 }
  exportLoading.value = true
  try {
    const data = await vasRequestsApi.index(params)
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
    a.download = `vas-requests-${new Date().toISOString().slice(0, 10)}.csv`
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
  loading.value = true
  loadError.value = null
  try {
    const data = await vasRequestsApi.index(buildParams())
    submissions.value = data.data ?? []
    meta.value = data.meta ?? meta.value
  } catch (err) {
    const msg = err.response?.data?.error || err.response?.data?.message || err.message || 'Failed to load VAS requests.'
    loadError.value = msg
    submissions.value = []
    meta.value = { current_page: 1, last_page: 1, per_page: meta.value.per_page ?? 15, total: 0 }
  } finally {
    loading.value = false
    window.scrollTo(0, 0)
  }
}

async function loadFilters() {
  try {
    const data = await vasRequestsApi.filters()
    filterOptions.value = {
      statuses: data.statuses ?? [],
      request_types: data.request_types ?? [],
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
    const data = await vasRequestsApi.columns()
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
    request_type: '',
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
    await vasRequestsApi.saveColumns(cols)
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
  executive: 'back_office_executive_id',
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
    } else if (field === 'executive') {
      row.back_office_executive_id = value
      row.executive = value == null ? null : (filterOptions.value.executives?.find((s) => s.id === value)?.name ?? row.executive)
    } else {
      row[field] = value
    }
  }
  try {
    const res = await vasRequestsApi.updateSubmissionFields(submissionId, payload)
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

const canBulkAssign = (() => {
  const roles = auth.user?.roles ?? []
  if (!Array.isArray(roles)) return false
  return roles.some((r) => {
    const name = typeof r === 'string' ? r : r?.name
    return name === 'superadmin' || name === 'back_office' || name === 'backoffice'
  })
})()

function openAssignModal(row) {
  if (!row) return
  assignVasRow.value = row
  assignBulkIds.value = []
  assignModalVisible.value = true
}

function openBulkAssign() {
  bulkAssignMessage.value = ''
  if (selectedSubmissionIds.value.length === 0) {
    bulkAssignMessage.value = 'Please select at least one row.'
    return
  }
  assignVasRow.value = null
  assignBulkIds.value = [...selectedSubmissionIds.value]
  assignModalVisible.value = true
}

function onAssignModalSaved() {
  toast('success', 'VAS request assigned successfully.')
  assignVasRow.value = null
  assignBulkIds.value = []
  selectedSubmissionIds.value = []
  load()
}

function onAssignModalClose() {
  assignModalVisible.value = false
  assignVasRow.value = null
  assignBulkIds.value = []
}

watch(selectedSubmissionIds, (ids) => {
  if (ids && ids.length > 0) bulkAssignMessage.value = ''
}, { deep: true })

onMounted(() => {
  loadFilters()
  loadColumns()
  load()
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-white py-6 px-4 sm:px-6">
    <div class="mx-auto max-w-7xl space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-baseline gap-2">
          <h1 class="text-xl font-semibold text-gray-900 leading-tight">VAS Requests</h1>
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
            type="button"
            class="inline-flex items-center rounded bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-70 disabled:cursor-wait"
            :disabled="loading || exportLoading"
            @click="onExport"
          >
          <svg v-if="exportLoading" class="mr-1.5 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
          </svg>
          <svg v-else class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
            class="inline-flex items-center rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
            @click="advancedVisible = !advancedVisible"
          >
            {{ advancedVisible ? 'Hide' : 'Advanced' }} Filters
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

      <div
        v-if="loadError"
        class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
      >
        {{ loadError }}
        <span class="block mt-1 text-xs">Set APP_DEBUG=true in .env to see the server error, or check storage/logs/laravel.log.</span>
      </div>

      <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <VasRequestTable
          :columns="visibleColumns"
          :data="submissions"
          :sort="sort"
          :order="order"
          :loading="loading"
          :current-page="meta.current_page"
          :per-page="meta.per_page"
          :edit-options="filterOptions"
          v-model:selected-ids="selectedSubmissionIds"
          @sort="onSort"
          @update-cell="onUpdateCell"
          @open-assign="openAssignModal"
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
      @update:visible="columnModalVisible = $event"
      @save="onSaveColumns"
    />

    <AssignBackOfficeModal
      :visible="assignModalVisible"
      :vas="assignVasRow"
      :bulk-vas-ids="assignBulkIds"
      @close="onAssignModalClose"
      @saved="onAssignModalSaved"
    />

    <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="4000" @dismiss="showToast = false" />
  </div>
</template>
