<script setup>
/**
 * Lead Submissions Listing – high-performance module with filters, column customization, inline editing.
 */
import { ref, watch, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import leadSubmissionsApi from '@/services/leadSubmissionsApi'
import FiltersBar from '@/components/lead-submissions/FiltersBar.vue'
import AdvancedFilters from '@/components/lead-submissions/AdvancedFilters.vue'
import ColumnCustomizerModal from '@/components/lead-submissions/ColumnCustomizerModal.vue'
import AssignBackOfficeModal from '@/components/lead-submissions/AssignBackOfficeModal.vue'
import LeadTable from '@/components/lead-submissions/LeadTable.vue'
import Pagination from '@/components/Pagination.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import Toast from '@/components/Toast.vue'

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
const visibleColumns = ref(['id', 'submitted_at', 'submission_type', 'account_number', 'company_name', 'category', 'type', 'product', 'mrc_aed', 'quantity', 'manager', 'team_leader', 'sales_agent', 'status', 'executive', 'sla_timer', 'status_changed_at', 'creator', 'email', 'contact_number_gsm'])
const sort = ref('submitted_at')
const order = ref('desc')
const advancedVisible = ref(false)
const columnModalVisible = ref(false)
const exportLoading = ref(false)
const assignModalVisible = ref(false)
const assignLeadRow = ref(null)
/** For bulk assign: lead IDs to assign. When set, modal runs in bulk mode. */
const assignBulkIds = ref([])
const selectedLeadIds = ref([])
/** Shown when user clicks Bulk Assign with no rows selected. */
const bulkAssignMessage = ref('')

const showToast = ref(false)
const toastType = ref('success')
const toastMsg  = ref('')
function toast(t, m) { toastType.value = t; toastMsg.value = m; showToast.value = true }
const canBulkAssign = (() => {
  const roles = auth.user?.roles ?? []
  if (!Array.isArray(roles)) return false
  return roles.some((r) => {
    const name = typeof r === 'string' ? r : r?.name
    return name === 'superadmin' || name === 'back_office' || name === 'backoffice'
  })
})()

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
  mrc: '',
  quantity: '',
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
  if (f.mrc !== '' && f.mrc != null) p.mrc = f.mrc
  if (f.quantity !== '' && f.quantity != null) p.quantity = f.quantity
  if (f.sales_agent_id) p.sales_agent_id = f.sales_agent_id
  if (f.team_leader_id) p.team_leader_id = f.team_leader_id
  if (f.manager_id) p.manager_id = f.manager_id
  return p
}

const COLUMN_LABELS = {
  id: 'ID',
  submitted_at: 'Lead Creation Date',
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
  sla_timer: 'SLA Timer',
  executive: 'Back Office Executive',
  status_changed_at: 'Last Updated',
  creator: 'Created By',
  call_verification: 'Call Verification',
  pending_from_sales: 'Pending From Sales',
  documents_verification: 'Documents Verification',
  submission_date_from: 'Submission Date From',
  back_office_notes: 'Back Office Notes',
  activity: 'Activity',
  back_office_account: 'Back Office Account',
  work_order: 'Work Order',
  du_status: 'DU Status',
  completion_date: 'Completion Date',
  du_remarks: 'DU Remarks',
  additional_note: 'Additional Note',
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
  window.scrollTo(0, 0)
  loading.value = true
  try {
    const data = await leadSubmissionsApi.index(buildParams())
    leads.value = data.data ?? []
    meta.value = data.meta ?? meta.value
  } finally {
    loading.value = false
    window.scrollTo(0, 0)
  }
}

async function loadFilters() {
  try {
    const [filtersRes, teamRes, backOfficeRes] = await Promise.all([
      leadSubmissionsApi.filters(),
      leadSubmissionsApi.getTeamOptions().catch(() => ({ data: {} })),
      leadSubmissionsApi.getBackOfficeOptions().catch(() => ({})),
    ])
    const data = filtersRes
    const team = teamRes?.data ?? teamRes ?? {}
    const bo = backOfficeRes || {}
    filterOptions.value = {
      categories: data.categories ?? [],
      types: data.types ?? [],
      statuses: data.statuses ?? [],
      products: data.products ?? [],
      managers: team.managers ?? [],
      teamLeaders: team.team_leaders ?? [],
      salesAgents: team.sales_agents ?? [],
      executives: bo.executives ?? [],
      call_verification_options: bo.call_verification_options ?? [],
      pending_from_sales_options: bo.pending_from_sales_options ?? [],
      documents_verification_options: bo.documents_verification_options ?? [],
      du_status_options: bo.du_status_options ?? [],
    }
  } catch {
    //
  }
}

/** Ensure sla_timer column always comes immediately after executive. */
function ensureSlaAfterExecutive(columns) {
  if (!Array.isArray(columns)) return columns
  const i = columns.indexOf('executive')
  const j = columns.indexOf('sla_timer')
  if (i === -1 || j === -1) return columns
  if (j === i + 1) return columns
  const withoutSla = columns.filter((c) => c !== 'sla_timer')
  const insertAt = withoutSla.indexOf('executive') + 1
  return [...withoutSla.slice(0, insertAt), 'sla_timer', ...withoutSla.slice(insertAt)]
}

async function loadColumns() {
  try {
    const data = await leadSubmissionsApi.columns()
    allColumns.value = data.all_columns ?? []
    const visible = data.visible_columns ?? visibleColumns.value
    let cols = Array.isArray(visible) ? visible.filter((c) => c !== 'created_at') : visibleColumns.value
    visibleColumns.value = ensureSlaAfterExecutive(cols)
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
    mrc: '',
    quantity: '',
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
    const ordered = ensureSlaAfterExecutive(cols)
    await leadSubmissionsApi.saveColumns(ordered)
    visibleColumns.value = ordered
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
    load().catch(() => { /* reload failed, e.g. server down; row already reverted */ })
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

/** Map display column name to API field name for updateBackOffice. */
const COLUMN_TO_API_FIELD = {
  category: 'service_category_id',
  type: 'service_type_id',
  executive: 'executive_id',
  manager: 'manager_id',
  team_leader: 'team_leader_id',
  sales_agent: 'sales_agent_id',
}

async function onUpdateCell(leadId, field, value) {
  const apiField = COLUMN_TO_API_FIELD[field] ?? field
  const row = leads.value.find((r) => r.id === leadId)
  const prev = row ? { ...row } : null
  if (row) {
    if (field === 'category' && value != null) {
      row.service_category_id = value
      row.category = filterOptions.value.categories.find((c) => c.id === value)?.name ?? row.category
    } else if (field === 'type' && value != null) {
      row.service_type_id = value
      row.type = filterOptions.value.types.find((t) => t.id === value)?.name ?? row.type
    } else if (field === 'executive' && value != null) {
      row.executive_id = value
      row.executive = filterOptions.value.executives.find((e) => e.id === value)?.name ?? 'Unassigned'
    } else if (field === 'manager' && value != null) {
      row.manager_id = value
      row.manager = filterOptions.value.managers.find((m) => m.id === value)?.name ?? row.manager
    } else if (field === 'team_leader' && value != null) {
      row.team_leader_id = value
      row.team_leader = filterOptions.value.teamLeaders.find((t) => t.id === value)?.name ?? row.team_leader
    } else if (field === 'sales_agent' && value != null) {
      row.sales_agent_id = value
      row.sales_agent = filterOptions.value.salesAgents.find((s) => s.id === value)?.name ?? row.sales_agent
    } else if (field === 'submission_type') {
      row.submission_type = value === 'resubmission' ? 'Resubmission' : 'New Submission'
    } else if (field === 'status') {
      row.status = value
    } else {
      row[field] = value
    }
  }
  try {
    await leadSubmissionsApi.updateBackOffice(leadId, { [apiField]: value })
  } catch {
    if (prev) Object.assign(row, prev)
    load()
  }
}

function onPageChange(page) {
  meta.value.current_page = page
  load()
}

function openEditPage(leadId) {
  const id = leadId != null ? Number(leadId) : null
  if (id == null) return
  router.push({ path: `/lead-submissions/${id}/edit` })
}

function openAssignModal(row) {
  if (!row) return
  assignLeadRow.value = row
  assignBulkIds.value = []
  assignModalVisible.value = true
}

function openBulkAssign() {
  bulkAssignMessage.value = ''
  if (selectedLeadIds.value.length === 0) {
    bulkAssignMessage.value = 'Please select at least one row.'
    return
  }
  assignLeadRow.value = null
  assignBulkIds.value = [...selectedLeadIds.value]
  assignModalVisible.value = true
}

function onAssignModalSaved() {
  toast('success', 'Lead assigned successfully.')
  assignLeadRow.value = null
  assignBulkIds.value = []
  selectedLeadIds.value = []
  load()
}

function onAssignModalClose() {
  assignModalVisible.value = false
  assignLeadRow.value = null
  assignBulkIds.value = []
}

watch(selectedLeadIds, (ids) => {
  if (ids && ids.length > 0) bulkAssignMessage.value = ''
}, { deep: true })

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
  <div class="min-h-[calc(100vh-4rem)] bg-white py-3">
    <div class="w-full space-y-3">
      <!-- Top: breadcrumbs + title (left), Bulk Assign + Export (right) -->
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-baseline gap-2">
          <h1 class="text-xl font-semibold text-gray-900 leading-tight">Lead Submissions</h1>
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
      <!-- Message when Bulk Assign clicked with no selection -->
      <div
        v-if="bulkAssignMessage"
        class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-800"
        role="alert"
      >
        {{ bulkAssignMessage }}
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

      <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <LeadTable
          :columns="visibleColumns"
          :data="leads"
          :sort="sort"
          :order="order"
          :loading="loading"
          :current-page="meta.current_page"
          :per-page="meta.per_page"
          :edit-options="filterOptions"
          v-model:selected-ids="selectedLeadIds"
          @sort="onSort"
          @update-status="onUpdateStatus"
          @update-status-changed-at="onUpdateStatusChangedAt"
          @update-cell="onUpdateCell"
          @open-edit="openEditPage"
          @open-assign="openAssignModal"
        />
        <div
          class="flex flex-wrap items-center gap-3 border-t border-black bg-white px-3 py-2"
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
      :lead="assignLeadRow"
      :bulk-lead-ids="assignBulkIds"
      @close="onAssignModalClose"
      @saved="onAssignModalSaved"
    />

    <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="4000" @dismiss="showToast = false" />
  </div>
</template>
