<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import specialRequestsApi from '@/services/specialRequestsApi'
import FiltersBar from '@/components/vas-requests/FiltersBar.vue'
import AdvancedFilters from '@/components/vas-requests/AdvancedFilters.vue'
import ColumnCustomizerModal from '@/components/lead-submissions/ColumnCustomizerModal.vue'
import SpecialRequestTable from '@/components/special-requests/SpecialRequestTable.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import api from '@/lib/axios'
import Toast from '@/components/Toast.vue'
import RecordHistoryModal from '@/components/RecordHistoryModal.vue'
import { debounce } from '@/composables/useApiRequest'
import { useFilterCache } from '@/composables/useFilterCache'
import { canModuleAction } from '@/lib/accessControl'

const router = useRouter()
const auth = useAuthStore()
let listAbortController = null
const { loadBootstrap: loadCachedBootstrap } = useFilterCache('special-requests')

const loading = ref(true)
const loadError = ref(null)
const showToast = ref(false)
const toastType = ref('success')
const toastMsg = ref('')
function toast(t, m) { toastType.value = t; toastMsg.value = m; showToast.value = true }

const historyModalVisible = ref(false)
const historyRecordId = ref(null)
const historyRecordLabel = ref('')
function openHistoryModal(row) {
  if (!row?.id) return
  historyRecordId.value = row.id
  historyRecordLabel.value = row.company_name || `Special Request #${row.id}`
  historyModalVisible.value = true
}
function closeHistoryModal() {
  historyModalVisible.value = false
  historyRecordId.value = null
  historyRecordLabel.value = ''
}
async function fetchAudits(id) {
  return await specialRequestsApi.getAudits(id)
}

const filterOptions = ref({
  statuses: [],
  request_types: [],
  managers: [],
  team_leaders: [],
  sales_agents: [],
})

const submissions = ref([])
const TABLE_MODULE = 'special-requests-listing'
const meta = ref({ current_page: 1, last_page: 1, per_page: auth.defaultTablePageSize || 25, total: 0 })
const perPageOptions = ref([10, 20, 25, 50, 100])
const allColumns = ref([])
const visibleColumns = ref([
  'created_at', 'company_name', 'account_number', 'request_type',
  'complete_address', 'special_instruction', 'manager', 'team_leader', 'sales_agent', 'status', 'creator', 'updated_at',
])
const sort = ref('created_at')
const order = ref('desc')
const advancedVisible = ref(false)
const columnModalVisible = ref(false)
const exportLoading = ref(false)
const selectedIds = ref([])
const canExport = () => canModuleAction(auth.user, 'special', 'export')

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
  id: 'SR',
  submitted_at: 'Submission Date',
  created_at: 'Created',
  company_name: 'Company Name',
  account_number: 'Account Number',
  request_type: 'Request Type',
  complete_address: 'Address',
  special_instruction: 'Special Instruction',
  sales_agent: 'Sales Agent',
  team_leader: 'Team Leader',
  manager: 'Manager',
  status: 'Status',
  creator: 'Created By',
  updated_at: 'Last Updated',
}

const COLUMN_ORDER = [
  'created_at', 'company_name', 'account_number', 'request_type',
  'complete_address', 'special_instruction', 'manager', 'team_leader',
  'sales_agent', 'status', 'creator', 'updated_at',
]

function normalizeVisibleColumns(cols) {
  const set = new Set((cols || []).filter((c) => c !== 'id' && c !== 'submitted_at'))
  const ordered = COLUMN_ORDER.filter((c) => set.has(c))
  const extra = [...set].filter((c) => !COLUMN_ORDER.includes(c))
  return [...ordered, ...extra]
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
    const data = await specialRequestsApi.index(params)
    const rows = data.data ?? []
    const cols = visibleColumns.value
    const headers = cols.map((c) => COLUMN_LABELS[c] ?? c)
    const csvRows = [headers.map(escapeCsv).join(',')]
    for (const row of rows) {
      const cells = cols.map((col) => {
        const v = row[col]
        if (v == null) return ''
        if (typeof v === 'object' && 'name' in v) return v.name ?? ''
        return v
      })
      csvRows.push(cells.map(escapeCsv).join(','))
    }
    const blob = new Blob([csvRows.join('\r\n')], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `special-requests-${new Date().toISOString().slice(0, 10)}.csv`
    a.click()
    URL.revokeObjectURL(url)
  } catch { /* silent */ } finally {
    exportLoading.value = false
  }
}

async function load() {
  window.scrollTo(0, 0)
  if (listAbortController) listAbortController.abort()
  listAbortController = new AbortController()
  loading.value = true
  loadError.value = null
  try {
    const data = await specialRequestsApi.index(buildParams(), { signal: listAbortController.signal })
    submissions.value = data.data ?? []
    meta.value = data.meta ?? meta.value
  } catch (err) {
    if (err?.name === 'CanceledError' || err?.code === 'ERR_CANCELED') return
    loadError.value = err.response?.data?.message || err.message || 'Failed to load.'
    submissions.value = []
  } finally {
    loading.value = false
  }
}

async function loadFilters() {
  try {
    const data = await specialRequestsApi.filters()
    filterOptions.value = {
      statuses: data.statuses ?? [],
      request_types: data.request_types ?? [],
      managers: data.managers ?? filterOptions.value.managers,
      team_leaders: data.team_leaders ?? filterOptions.value.team_leaders,
      sales_agents: data.sales_agents ?? filterOptions.value.sales_agents,
    }
  } catch { /* silent */ }
}

async function loadTeamOptions() {
  try {
    const res = await specialRequestsApi.getTeamOptions()
    const data = res?.data ?? res ?? {}
    filterOptions.value.managers = data.managers ?? []
    filterOptions.value.team_leaders = data.team_leaders ?? []
    filterOptions.value.sales_agents = data.sales_agents ?? []
  } catch { /* silent */ }
}

async function loadColumns() {
  try {
    const data = await specialRequestsApi.columns()
    allColumns.value = data.all_columns ?? []
    visibleColumns.value = normalizeVisibleColumns(data.visible_columns ?? visibleColumns.value)
  } catch { /* silent */ }
}

function applyFilters() { meta.value.current_page = 1; load() }
function resetFilters() {
  filters.value = { q: '', company_name: '', account_number: '', request_type: '', status: '', from: '', to: '', submitted_from: '', submitted_to: '', sales_agent_id: null, team_leader_id: null, manager_id: null }
  meta.value.current_page = 1
  load()
}

function onSort({ sort: s, order: o }) { sort.value = s; order.value = o; meta.value.current_page = 1; load() }

async function onSaveColumns(cols) {
  try {
    const normalized = normalizeVisibleColumns(cols)
    await specialRequestsApi.saveColumns(normalized)
    visibleColumns.value = normalized
    meta.value.current_page = 1
    load()
  } catch { /* silent */ }
}

async function onUpdateCell(submissionId, field, value) {
  const apiField = { manager: 'manager_id', team_leader: 'team_leader_id', sales_agent: 'sales_agent_id' }[field] ?? field
  try {
    const res = await specialRequestsApi.patchRequest(submissionId, { [apiField]: value })
    if (res?.row) {
      const idx = submissions.value.findIndex((r) => r.id === submissionId)
      if (idx >= 0) Object.assign(submissions.value[idx], res.row)
    }
    toast('success', 'Updated successfully.')
  } catch {
    load()
    toast('error', 'Failed to update.')
  }
}

function onPageChange(page) { meta.value.current_page = page; load() }

async function onPerPageChange(e) {
  meta.value.per_page = Number(e.target.value)
  meta.value.current_page = 1
  load()
  try { await api.post(`/table-preferences/${TABLE_MODULE}`, { per_page: meta.value.per_page }) } catch { /* silent */ }
}

async function loadTablePreference() {
  try {
    const { data } = await api.get(`/table-preferences/${TABLE_MODULE}`)
    if (data.per_page) meta.value.per_page = Number(data.per_page)
    if (Array.isArray(data.options) && data.options.length) perPageOptions.value = data.options
  } catch { /* silent */ }
}

const debouncedSearch = debounce(() => { meta.value.current_page = 1; load() }, 400)
watch(() => filters.value.q, (n, o) => { if (n !== o) debouncedSearch() })
onUnmounted(() => { if (listAbortController) listAbortController.abort(); debouncedSearch.cancel() })

onMounted(async () => {
  const bootstrapResult = await loadCachedBootstrap(buildParams())
  if (bootstrapResult) {
    const fd = bootstrapResult.filters ?? {}
    const team = bootstrapResult.team_options ?? {}
    filterOptions.value = {
      statuses: fd.statuses ?? [],
      request_types: fd.request_types ?? [],
      managers: team.managers ?? [],
      team_leaders: team.team_leaders ?? [],
      sales_agents: team.sales_agents ?? [],
    }
    const cd = bootstrapResult.columns ?? {}
    if (cd.all_columns) allColumns.value = cd.all_columns
    if (cd.visible_columns) visibleColumns.value = normalizeVisibleColumns(cd.visible_columns)
    const pd = bootstrapResult.page ?? {}
    submissions.value = pd.data ?? []
    meta.value = pd.meta ?? meta.value
    loading.value = false
  } else {
    await loadTablePreference()
    loadFilters()
    loadTeamOptions()
    loadColumns()
    load()
  }
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-white py-6 px-4 sm:px-6">
    <div class="mx-auto max-w-7xl space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-baseline gap-2">
          <h1 class="text-xl font-semibold text-gray-900 leading-tight">Special Requests</h1>
          <Breadcrumbs />
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button
            v-if="canExport()"
            type="button"
            class="inline-flex items-center rounded bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-70"
            :disabled="loading || exportLoading"
            @click="onExport"
          >
            <svg v-if="exportLoading" class="mr-1.5 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" /></svg>
            <svg v-else class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
            {{ exportLoading ? 'Exporting...' : 'Export' }}
          </button>
        </div>
      </div>

      <FiltersBar :filters="filters" :filter-options="filterOptions" :loading="loading" @apply="applyFilters" @reset="resetFilters">
        <template #after-reset>
          <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="advancedVisible = !advancedVisible">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
            Advanced Filters
          </button>
          <button type="button" class="inline-flex items-center rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="columnModalVisible = true">
            Customize Columns
            <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
          </button>
        </template>
      </FiltersBar>

      <AdvancedFilters :visible="advancedVisible" :filters="filters" :filter-options="filterOptions" :loading="loading" @apply="applyFilters" @reset="resetFilters" />

      <div v-if="loadError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ loadError }}</div>

      <div class="overflow-hidden rounded-lg border-2 border-black bg-white shadow-sm">
        <SpecialRequestTable
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
              <select :value="meta.per_page" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm min-w-[80px] text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500" @change="onPerPageChange">
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

    <ColumnCustomizerModal :visible="columnModalVisible" :all-columns="allColumns" :visible-columns="visibleColumns" @update:visible="columnModalVisible = $event" @save="onSaveColumns" />
    <RecordHistoryModal :visible="historyModalVisible" :record-id="historyRecordId" :record-label="historyRecordLabel" module-name="Special Requests" :fetch-fn="fetchAudits" @close="closeHistoryModal" />
    <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="4000" @dismiss="showToast = false" />
  </div>
</template>
