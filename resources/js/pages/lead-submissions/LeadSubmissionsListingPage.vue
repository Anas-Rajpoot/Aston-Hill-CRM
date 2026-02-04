<script setup>
/**
 * Lead Submissions Listing – high-performance module with filters, column customization, inline editing.
 */
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import leadSubmissionsApi from '@/services/leadSubmissionsApi'
import FiltersBar from '@/components/lead-submissions/FiltersBar.vue'
import AdvancedFilters from '@/components/lead-submissions/AdvancedFilters.vue'
import ColumnCustomizerModal from '@/components/lead-submissions/ColumnCustomizerModal.vue'
import EditSubmissionModal from '@/components/lead-submissions/EditSubmissionModal.vue'
import LeadTable from '@/components/lead-submissions/LeadTable.vue'
import Pagination from '@/components/Pagination.vue'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const loading = ref(true)
const filterOptions = ref({
  categories: [],
  types: [],
  statuses: [],
  products: [],
  managers: [],
  teamLeaders: [],
  salesAgents: [],
})
const leads = ref([])
const meta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 })
const allColumns = ref([])
const visibleColumns = ref(['submitted_at', 'created_at', 'account_number', 'company_name', 'category', 'type', 'product', 'mrc_aed', 'quantity', 'manager', 'team_leader', 'sales_agent', 'creator', 'email', 'contact_number_gsm', 'status', 'status_changed_at'])
const sort = ref('created_at')
const order = ref('desc')
const advancedVisible = ref(false)
const columnModalVisible = ref(false)
const exportLoading = ref(false)
const editModalVisible = ref(false)
const editLeadId = ref(null)

const filters = ref({
  q: '',
  account_number: '',
  company_name: '',
  service_category_id: null,
  service_type_id: null,
  status: '',
  product: '',
  from: '',
  to: '',
  submitted_from: '',
  submitted_to: '',
  updated_from: '',
  updated_to: '',
  mrc_min: '',
  mrc_max: '',
  quantity_min: '',
  quantity_max: '',
  sales_agent_id: null,
  team_leader_id: null,
  manager_id: null,
})

const canEdit = () => (auth.user?.permissions ?? []).includes('lead.edit')

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
  if (f.account_number) p.account_number = f.account_number
  if (f.company_name) p.company_name = f.company_name
  if (f.service_category_id) p.service_category_id = f.service_category_id
  if (f.service_type_id) p.service_type_id = f.service_type_id
  if (f.status) p.status = f.status
  if (f.product) p.product = f.product
  if (f.from) p.from = f.from
  if (f.to) p.to = f.to
  if (f.submitted_from) p.submitted_from = f.submitted_from
  if (f.submitted_to) p.submitted_to = f.submitted_to
  if (f.updated_from) p.updated_from = f.updated_from
  if (f.updated_to) p.updated_to = f.updated_to
  if (f.mrc_min !== '' && f.mrc_min != null) p.mrc_min = f.mrc_min
  if (f.mrc_max !== '' && f.mrc_max != null) p.mrc_max = f.mrc_max
  if (f.quantity_min !== '' && f.quantity_min != null) p.quantity_min = f.quantity_min
  if (f.quantity_max !== '' && f.quantity_max != null) p.quantity_max = f.quantity_max
  if (f.sales_agent_id) p.sales_agent_id = f.sales_agent_id
  if (f.team_leader_id) p.team_leader_id = f.team_leader_id
  if (f.manager_id) p.manager_id = f.manager_id
  return p
}

const COLUMN_LABELS = {
  id: 'ID',
  submitted_at: 'Submission Date',
  created_at: 'Created',
  account_number: 'Account Number',
  company_name: 'Company Name',
  category: 'Service Category',
  type: 'Service Type',
  product: 'Product',
  mrc_aed: 'MRC (AED)',
  quantity: 'Qty',
  sales_agent: 'Sales Agent',
  team_leader: 'Team Leader',
  manager: 'Manager',
  status: 'Status',
  status_changed_at: 'Last Updated',
  creator: 'Created By',
  email: 'Email',
  contact_number_gsm: 'Contact',
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
    const data = await leadSubmissionsApi.index(params)
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
    a.download = `lead-submissions-${new Date().toISOString().slice(0, 10)}.csv`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    // silent fail or could show toast
  } finally {
    exportLoading.value = false
  }
}

async function load() {
  loading.value = true
  try {
    const data = await leadSubmissionsApi.index(buildParams())
    leads.value = data.data ?? []
    meta.value = data.meta ?? meta.value
  } finally {
    loading.value = false
  }
}

async function loadFilters() {
  try {
    const [filtersRes, teamRes] = await Promise.all([
      leadSubmissionsApi.filters(),
      leadSubmissionsApi.getTeamOptions().catch(() => ({ data: {} })),
    ])
    const data = filtersRes
    const team = teamRes?.data ?? {}
    filterOptions.value = {
      categories: data.categories ?? [],
      types: data.types ?? [],
      statuses: data.statuses ?? [],
      products: data.products ?? [],
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
    const data = await leadSubmissionsApi.columns()
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
    account_number: '',
    company_name: '',
    service_category_id: null,
    service_type_id: null,
    status: '',
    product: '',
    from: '',
    to: '',
    submitted_from: '',
    submitted_to: '',
    updated_from: '',
    updated_to: '',
    mrc_min: '',
    mrc_max: '',
    quantity_min: '',
    quantity_max: '',
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
    await leadSubmissionsApi.saveColumns(cols)
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

async function onUpdateStatus(leadId, newStatus) {
  const row = leads.value.find((r) => r.id === leadId)
  const prevStatus = row?.status
  const prevChangedAt = row?.status_changed_at
  if (row) {
    row.status = newStatus
    row.status_changed_at = formatDateForDisplay(new Date())
  }
  try {
    const res = await leadSubmissionsApi.updateStatus(leadId, newStatus)
    if (row) {
      row.status = res.status
      row.status_changed_at = res.status_changed_at ?? row.status_changed_at
    }
  } catch {
    if (row) {
      row.status = prevStatus
      row.status_changed_at = prevChangedAt
    }
    load()
  }
}

async function onUpdateStatusChangedAt(leadId, statusChangedAtIso) {
  const row = leads.value.find((r) => r.id === leadId)
  const prev = row?.status_changed_at
  if (row) row.status_changed_at = statusChangedAtIso
  try {
    const res = await leadSubmissionsApi.updateStatusChangedAt(leadId, statusChangedAtIso)
    if (row) row.status_changed_at = res.status_changed_at ?? statusChangedAtIso
  } catch {
    if (row) row.status_changed_at = prev
    load()
  }
}

function onPageChange(page) {
  meta.value.current_page = page
  load()
}

function openEditModal(leadId) {
  const id = leadId != null ? Number(leadId) : null
  if (id == null) return
  editLeadId.value = id
  editModalVisible.value = true
}

function onEditModalSaved() {
  load()
}

onMounted(() => {
  loadFilters()
  loadColumns()
  load()
  const openEditId = route.query.openEdit
  if (openEditId != null && openEditId !== '') {
    openEditModal(Number(openEditId))
    router.replace({ path: '/lead-submissions', query: {} })
  }
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-[#f0f2f5] py-6 px-4 sm:px-6">
    <div class="mx-auto max-w-7xl space-y-4">
      <!-- Top: title + Bulk Assign, Export -->
      <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-xl font-semibold text-gray-900">Lead Submissions</h1>
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

      <!-- Export in progress message -->
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

      <!-- Above data table: Advanced Filters + Customize Columns -->
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
        <LeadTable
          :columns="visibleColumns"
          :data="leads"
          :sort="sort"
          :order="order"
          :loading="loading"
          @sort="onSort"
          @update-status="onUpdateStatus"
          @update-status-changed-at="onUpdateStatusChangedAt"
          @open-edit="openEditModal"
        />
        <div
          class="flex flex-wrap items-center gap-4 border-t border-gray-200 bg-gray-100 px-4 py-3"
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

    <EditSubmissionModal
      :visible="editModalVisible"
      :lead-id="editLeadId"
      @close="editModalVisible = false"
      @saved="onEditModalSaved"
    />
  </div>
</template>
