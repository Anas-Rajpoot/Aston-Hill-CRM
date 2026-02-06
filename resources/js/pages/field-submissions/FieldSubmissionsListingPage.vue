<script setup>
/**
 * Field Submissions Listing – same design and functionality as Lead Submissions.
 */
import { ref, onMounted } from 'vue'
import fieldSubmissionsApi from '@/services/fieldSubmissionsApi'
import FiltersBar from '@/components/field-submissions/FiltersBar.vue'
import AdvancedFilters from '@/components/field-submissions/AdvancedFilters.vue'
import ColumnCustomizerModal from '@/components/lead-submissions/ColumnCustomizerModal.vue'
import AssignFieldTechnicianModal from '@/components/field-submissions/AssignFieldTechnicianModal.vue'
import FieldTable from '@/components/field-submissions/FieldTable.vue'
import Pagination from '@/components/Pagination.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'

const loading = ref(true)
const filterOptions = ref({
  statuses: [],
  products: [],
  emirates: [],
  managers: [],
  teamLeaders: [],
  salesAgents: [],
})
const submissions = ref([])
const meta = ref({ current_page: 1, last_page: 1, per_page: 10, total: 0 })
const allColumns = ref([])
const visibleColumns = ref([
  'submitted_at', 'company_name', 'emirates', 'product', 'sales_agent', 'team_leader', 'manager',
  'field_agent', 'field_status', 'target_date', 'sla_timer', 'sla_status', 'last_updated',
])
const sort = ref('created_at')
const order = ref('desc')
const advancedVisible = ref(false)
const columnModalVisible = ref(false)
const assignModalVisible = ref(false)
const assignSubmission = ref(null)
const fieldTechnicians = ref([])
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
  loading.value = true
  try {
    const data = await fieldSubmissionsApi.index(buildParams())
    submissions.value = data.data ?? []
    meta.value = data.meta ?? meta.value
  } finally {
    loading.value = false
    window.scrollTo(0, 0)
  }
}

async function loadFilters() {
  try {
    const [filtersRes, teamRes] = await Promise.all([
      fieldSubmissionsApi.filters(),
      fieldSubmissionsApi.getTeamOptions().catch(() => ({ data: {} })),
    ])
    const data = filtersRes
    const team = teamRes?.data ?? {}
    filterOptions.value = {
      statuses: data.statuses ?? [],
      products: data.products ?? [],
      emirates: data.emirates ?? [],
      managers: team.managers ?? [],
      teamLeaders: team.team_leaders ?? [],
      salesAgents: team.sales_agents ?? [],
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

onMounted(() => {
  loadFilters()
  loadColumns()
  load()
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-[#f0f2f5] py-6 px-4 sm:px-6">
    <div class="mx-auto max-w-7xl space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-xl font-semibold text-gray-900">Field Submissions</h1>
        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="inline-flex items-center rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
            title="Bulk Assign"
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
      <Breadcrumbs />

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
      />

      <AdvancedFilters
        :visible="advancedVisible"
        :filters="filters"
        :filter-options="filterOptions"
        :loading="loading"
        @apply="applyFilters"
        @reset="resetFilters"
      />

      <div class="flex flex-wrap items-center justify-end gap-2">
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
      </div>

      <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <FieldTable
          :columns="visibleColumns"
          :data="submissions"
          :sort="sort"
          :order="order"
          :loading="loading"
          @sort="onSort"
          @update-status="onUpdateStatus"
          @assign-technician="onOpenAssignTechnician"
        />
        <div
          class="flex flex-wrap items-center justify-between gap-4 border-t border-gray-200 bg-gray-100 px-4 py-3"
        >
          <p class="text-sm text-gray-600">
            Showing {{ meta.total ? ((meta.current_page - 1) * meta.per_page) + 1 : 0 }} to {{ Math.min(meta.current_page * meta.per_page, meta.total) }} of {{ meta.total }} results
          </p>
          <div v-if="meta.last_page > 1" class="flex items-center gap-2">
            <span class="text-sm text-gray-600">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
            <Pagination
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
    </div>

    <ColumnCustomizerModal
      :visible="columnModalVisible"
      :all-columns="allColumns"
      :visible-columns="visibleColumns"
      @update:visible="columnModalVisible = $event"
      @save="onSaveColumns"
    />
    <AssignFieldTechnicianModal
      :visible="assignModalVisible"
      :submission="assignSubmission"
      :field-technicians="fieldTechnicians"
      @close="assignModalVisible = false; assignSubmission = null"
      @assign="onAssignFieldTechnician"
    />
  </div>
</template>
