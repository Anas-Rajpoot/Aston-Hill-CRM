<script setup>
/**
 * Employees listing – filters, advanced filters, customize columns, bulk import/export, Add employee, View/Edit/Deactivate.
 */
import { ref, onMounted, watch } from 'vue'
import employeesApi from '@/services/employeesApi'
import usersApi from '@/services/usersApi'
import FiltersBar from '@/components/employees/FiltersBar.vue'
import AdvancedFilters from '@/components/employees/AdvancedFilters.vue'
import ColumnCustomizerModal from '@/components/lead-submissions/ColumnCustomizerModal.vue'
import EmployeeTable from '@/components/employees/EmployeeTable.vue'
import Toast from '@/components/Toast.vue'
import RecordHistoryModal from '@/components/RecordHistoryModal.vue'
import api from '@/lib/axios'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const historyModalVisible = ref(false)
const historyRecordId = ref(null)
const historyRecordLabel = ref('')
function openHistoryModal(row) {
  if (!row?.id) return
  historyRecordId.value = row.id
  historyRecordLabel.value = row.name || `Employee #${row.id}`
  historyModalVisible.value = true
}
function closeHistoryModal() {
  historyModalVisible.value = false
  historyRecordId.value = null
  historyRecordLabel.value = ''
}
async function fetchEmployeeAudits(id) {
  return await employeesApi.getAudits(id)
}

const loading = ref(true)
const loadError = ref(null)
const filterOptions = ref({
  statuses: [],
  departments: [],
  roles: [],
  managers: [],
  team_leaders: [],
})
const employees = ref([])
const meta = ref({ current_page: 1, last_page: 1, per_page: auth.defaultTablePageSize || 25, total: 0 })
const TABLE_MODULE = 'employees'
const perPageOptions = ref([10, 20, 25, 50, 100])
const allColumns = ref([])
const visibleColumns = ref([
  'id', 'employee_number', 'name', 'roles', 'team_leader', 'manager', 'department',
  'email', 'phone', 'cnic_number', 'extension', 'status', 'joining_date', 'terminate_date',
])
const sort = ref('name')
const order = ref('asc')
const advancedVisible = ref(false)
const columnModalVisible = ref(false)
const exportLoading = ref(false)
const importLoading = ref(false)
const importFileInput = ref(null)
const importResult = ref(null)
const selectedEmployeeIds = ref([])
const bulkDeactivateMessage = ref('')
const bulkActivateMessage = ref('')
const superAdminSelectMessage = ref('')
const employeeToDeactivate = ref(null)
const employeeToActivate = ref(null)
const deactivating = ref(false)
const activating = ref(false)

const showToast = ref(false)
const toastType = ref('success')
const toastMsg  = ref('')
function toast(t, m) { toastType.value = t; toastMsg.value = m; showToast.value = true }

const filters = ref({
  q: '',
  name: '',
  email: '',
  status: '',
  department: '',
  role: '',
  manager_id: null,
  team_leader_id: null,
  joining_from: '',
  joining_to: '',
  terminate_from: '',
  terminate_to: '',
})

const COLUMN_LABELS = {
  id: 'ID',
  employee_number: 'Employee ID',
  name: 'Employee Name',
  roles: 'Role(s)',
  team_leader: 'Team Leader',
  manager: 'Manager',
  department: 'Department',
  email: 'Primary Email',
  phone: 'Contact No',
  cnic_number: 'GMIC No',
  extension: 'Extension',
  status: 'Status',
  joining_date: 'Joining Date',
  terminate_date: 'Terminate Date',
}

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
  if (f.name) p.name = f.name
  if (f.email) p.email = f.email
  if (f.status) p.status = f.status
  if (f.department) p.department = f.department
  if (f.role) p.role = f.role
  if (f.manager_id) p.manager_id = f.manager_id
  if (f.team_leader_id) p.team_leader_id = f.team_leader_id
  if (f.joining_from) p.joining_from = f.joining_from
  if (f.joining_to) p.joining_to = f.joining_to
  if (f.terminate_from) p.terminate_from = f.terminate_from
  if (f.terminate_to) p.terminate_to = f.terminate_to
  return p
}

function escapeCsv(val) {
  if (val == null) return ''
  const s = String(val)
  if (s.includes(',') || s.includes('"') || s.includes('\n')) return '"' + s.replace(/"/g, '""') + '"'
  return s
}

function rowToCsvCells(row, columns) {
  return columns.map((col) => {
    const v = col === 'roles' ? (row.roles || []).join('; ') : row[col]
    if (v == null) return ''
    if (typeof v === 'object' && v !== null && 'name' in v) return v.name ?? ''
    return v
  })
}

async function onExport() {
  const params = { ...buildParams(), page: 1, per_page: 1000 }
  exportLoading.value = true
  try {
    const data = await employeesApi.index(params)
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
    a.download = `employees-${new Date().toISOString().slice(0, 10)}.csv`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    //
  } finally {
    exportLoading.value = false
  }
}

function triggerImport() {
  importResult.value = null
  importFileInput.value?.click()
}

async function onImportFileChange(event) {
  const file = event.target.files?.[0]
  if (!file) return
  event.target.value = ''
  importLoading.value = true
  importResult.value = null
  try {
    const result = await employeesApi.bulkImport(file)
    importResult.value = result
    load()
  } catch (err) {
    importResult.value = { message: err.response?.data?.message || err.message || 'Import failed.', errors: [] }
  } finally {
    importLoading.value = false
  }
}

async function load() {
  window.scrollTo(0, 0)
  loading.value = true
  loadError.value = null
  try {
    const data = await employeesApi.index(buildParams())
    employees.value = data.data ?? []
    meta.value = data.meta ?? meta.value
  } catch (err) {
    const msg = err.response?.data?.error || err.response?.data?.message || err.message || 'Failed to load employees.'
    loadError.value = msg
    employees.value = []
    meta.value = { current_page: 1, last_page: 1, per_page: meta.value.per_page ?? 15, total: 0 }
  } finally {
    loading.value = false
    window.scrollTo(0, 0)
  }
}

async function loadFilters() {
  try {
    const data = await employeesApi.filters()
    filterOptions.value = {
      statuses: data.statuses ?? [],
      departments: data.departments ?? [],
      roles: data.roles ?? [],
      managers: data.managers ?? [],
      team_leaders: data.team_leaders ?? [],
    }
  } catch {
    //
  }
}

async function loadColumns() {
  try {
    const data = await employeesApi.columns()
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
    name: '',
    email: '',
    status: '',
    department: '',
    role: '',
    manager_id: null,
    team_leader_id: null,
    joining_from: '',
    joining_to: '',
    terminate_from: '',
    terminate_to: '',
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
    await employeesApi.saveColumns(cols)
    visibleColumns.value = cols
    meta.value.current_page = 1
    load()
  } catch {
    //
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

function onShowMessage(message) {
  superAdminSelectMessage.value = message
  setTimeout(() => { superAdminSelectMessage.value = '' }, 4000)
}

function openDeactivateConfirm(row) {
  employeeToDeactivate.value = row
}

function closeDeactivateConfirm() {
  employeeToDeactivate.value = null
}

function openActivateConfirm(row) {
  employeeToActivate.value = row
}

function closeActivateConfirm() {
  employeeToActivate.value = null
}

async function confirmDeactivate() {
  const row = employeeToDeactivate.value
  if (!row?.id) {
    closeDeactivateConfirm()
    return
  }
  if ((row.roles || []).includes('superadmin')) {
    closeDeactivateConfirm()
    return
  }
  deactivating.value = true
  try {
    await usersApi.bulkDeactivate([row.id])
    toast('success', 'Employee deactivated successfully.')
    closeDeactivateConfirm()
    load()
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to deactivate employee.')
  } finally {
    deactivating.value = false
  }
}

async function confirmActivate() {
  const row = employeeToActivate.value
  if (!row?.id) {
    closeActivateConfirm()
    return
  }
  if ((row.roles || []).includes('superadmin')) {
    closeActivateConfirm()
    return
  }
  activating.value = true
  try {
    await usersApi.bulkActivate([row.id])
    toast('success', 'Employee activated successfully.')
    closeActivateConfirm()
    load()
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to activate employee.')
  } finally {
    activating.value = false
  }
}

async function bulkDeactivate() {
  bulkDeactivateMessage.value = ''
  bulkActivateMessage.value = ''
  if (selectedEmployeeIds.value.length === 0) {
    bulkDeactivateMessage.value = 'Please select at least one row.'
    return
  }
  const superAdminIds = employees.value
    .filter((e) => (e.roles || []).includes('superadmin'))
    .map((e) => e.id)
  const idsToDeactivate = selectedEmployeeIds.value.filter((id) => !superAdminIds.includes(id))
  if (idsToDeactivate.length === 0) {
    bulkDeactivateMessage.value = 'Super admin cannot be deactivated. No other rows selected.'
    return
  }
  const activeIds = employees.value.filter((e) => e.status === 'approved').map((e) => e.id)
  const idsToDeactivateFiltered = idsToDeactivate.filter((id) => activeIds.includes(id))
  if (idsToDeactivateFiltered.length === 0) {
    bulkDeactivateMessage.value = 'No active employees selected to deactivate.'
    return
  }
  try {
    await usersApi.bulkDeactivate(idsToDeactivateFiltered)
    selectedEmployeeIds.value = []
    load()
  } catch {
    bulkDeactivateMessage.value = 'Failed to deactivate selected employees.'
  }
}

async function bulkActivate() {
  bulkActivateMessage.value = ''
  bulkDeactivateMessage.value = ''
  if (selectedEmployeeIds.value.length === 0) {
    bulkActivateMessage.value = 'Please select at least one row.'
    return
  }
  const superAdminIds = employees.value
    .filter((e) => (e.roles || []).includes('superadmin'))
    .map((e) => e.id)
  const idsExcludingSuperAdmin = selectedEmployeeIds.value.filter((id) => !superAdminIds.includes(id))
  if (idsExcludingSuperAdmin.length === 0) {
    bulkActivateMessage.value = 'Super admin cannot be activated. No other rows selected.'
    return
  }
  const inactiveEmployees = employees.value.filter((e) => e.status !== 'approved')
  const inactiveIds = inactiveEmployees.map((e) => e.id)
  const idsToActivate = idsExcludingSuperAdmin.filter((id) => inactiveIds.includes(id))
  if (idsToActivate.length === 0) {
    bulkActivateMessage.value = 'No inactive employees selected to activate.'
    return
  }
  try {
    await usersApi.bulkActivate(idsToActivate)
    selectedEmployeeIds.value = []
    load()
  } catch {
    bulkActivateMessage.value = 'Failed to activate selected employees.'
  }
}

watch(selectedEmployeeIds, (ids) => {
  if (ids?.length > 0) {
    bulkDeactivateMessage.value = ''
    bulkActivateMessage.value = ''
  }
}, { deep: true })

onMounted(async () => {
  await loadTablePreference()
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
          <h1 class="text-xl font-semibold text-gray-900 leading-tight">Employees</h1>        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="inline-flex items-center rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
            :disabled="loading || importLoading"
            @click="triggerImport"
          >
            <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
            </svg>
            {{ importLoading ? 'Importing...' : 'Bulk Import' }}
          </button>
          <input
            ref="importFileInput"
            type="file"
            accept=".csv,.txt"
            class="hidden"
            @change="onImportFileChange"
          />
          <button
            type="button"
            class="inline-flex items-center rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
            :disabled="loading || exportLoading"
            @click="onExport"
          >
            <svg v-if="exportLoading" class="mr-1.5 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
            </svg>
            <svg v-else class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            {{ exportLoading ? 'Exporting...' : 'Export' }}
          </button>
          <button
            type="button"
            class="inline-flex items-center rounded border border-brand-primary-muted bg-white px-3 py-2 text-sm text-brand-primary-hover hover:bg-brand-primary-light disabled:opacity-50"
            :disabled="loading || selectedEmployeeIds.length === 0"
            @click="bulkActivate"
          >
            <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Bulk Activate
          </button>
          <button
            type="button"
            class="inline-flex items-center rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-700 hover:bg-red-50 disabled:opacity-50"
            :disabled="loading || selectedEmployeeIds.length === 0"
            @click="bulkDeactivate"
          >
            <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
            </svg>
            Bulk Deactivate
          </button>
          <router-link
            to="/users/create"
            class="inline-flex items-center rounded bg-brand-primary px-3 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover"
          >
            <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add New Employee
          </router-link>
        </div>
      </div>

      <div v-if="importResult" class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-700">
        {{ importResult.message }}
        <span v-if="importResult.errors?.length" class="block mt-1 text-xs text-amber-700">
          {{ importResult.errors.join(' ') }}
        </span>
      </div>

      <div
        v-if="bulkDeactivateMessage"
        class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-800"
        role="alert"
      >
        {{ bulkDeactivateMessage }}
      </div>
      <div
        v-if="bulkActivateMessage"
        class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-800"
        role="alert"
      >
        {{ bulkActivateMessage }}
      </div>

      <div
        v-if="superAdminSelectMessage"
        class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-800"
        role="alert"
      >
        {{ superAdminSelectMessage }}
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

      <div
        v-if="loadError"
        class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
      >
        {{ loadError }}
      </div>

      <div class="overflow-hidden rounded-lg border-2 border-black bg-white shadow-sm">
        <EmployeeTable
          :columns="visibleColumns"
          :data="employees"
          :sort="sort"
          :order="order"
          :loading="loading"
          :current-page="meta.current_page"
          :per-page="meta.per_page"
          v-model:selected-ids="selectedEmployeeIds"
          @sort="onSort"
          @deactivate="openDeactivateConfirm"
          @activate="openActivateConfirm"
          @show-message="onShowMessage"
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
                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm min-w-[80px] text-gray-700 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
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

    <!-- Single deactivate confirmation -->
    <Teleport to="body">
      <div
        v-if="employeeToDeactivate"
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-500/50 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="confirm-deactivate-title"
        @click.self="closeDeactivateConfirm"
      >
        <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
            <h2 id="confirm-deactivate-title" class="text-lg font-semibold text-gray-900">Confirm Deactivation</h2>
            <button
              type="button"
              class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
              aria-label="Close"
              @click="closeDeactivateConfirm"
            >
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div class="px-6 py-4">
            <p class="text-sm text-gray-600">
              Are you sure you want to deactivate
              <span class="font-medium text-gray-900">{{ employeeToDeactivate?.name || 'this employee' }}</span>
              Employee?
            </p>
          </div>
          <div class="flex justify-end gap-3 border-t border-gray-200 px-6 py-4">
            <button
              type="button"
              class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
              :disabled="deactivating"
              @click="confirmDeactivate"
            >
              {{ deactivating ? 'Deactivating...' : 'Confirm' }}
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
              @click="closeDeactivateConfirm"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Single activate confirmation -->
    <Teleport to="body">
      <div
        v-if="employeeToActivate"
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-500/50 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="confirm-activate-title"
        @click.self="closeActivateConfirm"
      >
        <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
            <h2 id="confirm-activate-title" class="text-lg font-semibold text-gray-900">Confirm Activation</h2>
            <button
              type="button"
              class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
              aria-label="Close"
              @click="closeActivateConfirm"
            >
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div class="px-6 py-4">
            <p class="text-sm text-gray-600">
              Are you sure you want to activate
              <span class="font-medium text-gray-900">{{ employeeToActivate?.name || 'this employee' }}</span>
              Employee?
            </p>
          </div>
          <div class="flex justify-end gap-3 border-t border-gray-200 px-6 py-4">
            <button
              type="button"
              class="rounded bg-brand-primary px-4 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover disabled:opacity-50"
              :disabled="activating"
              @click="confirmActivate"
            >
              {{ activating ? 'Activating...' : 'Confirm' }}
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
              @click="closeActivateConfirm"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <RecordHistoryModal
      :visible="historyModalVisible"
      :record-id="historyRecordId"
      :record-label="historyRecordLabel"
      module-name="Employees"
      :fetch-fn="fetchEmployeeAudits"
      @close="closeHistoryModal"
    />

    <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="4000" @dismiss="showToast = false" />
  </div>
</template>
