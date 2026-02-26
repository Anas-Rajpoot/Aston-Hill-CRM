<script setup>
/**
 * Attendance Log – table from login logs: Employee Name, ID, Role, Login Date/Time, Logout Time, Duration, Status.
 * Filters, Advanced Filters, Customize Columns, sortable columns, Export. Force Logout for active/missing sessions.
 * Permissions: view_attendance_logs, force_logout, export_attendance_data.
 */
import { ref, computed, onMounted } from 'vue'
import { useTablePageSize } from '@/composables/useTablePageSize'
import attendanceLogApi from '@/services/attendanceLogApi'
import { useAuthStore } from '@/stores/auth'
import { toDdMmYyyy, fromDdMmYyyy, toDdMonYyyyDash } from '@/lib/dateFormat'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import FiltersBar from '@/components/attendance/FiltersBar.vue'
import ColumnCustomizerModal from '@/components/lead-submissions/ColumnCustomizerModal.vue'
import Toast from '@/components/Toast.vue'

const auth = useAuthStore()
const permissions = computed(() => auth.user?.permissions ?? [])
const isSuperAdmin = computed(() => {
  const r = auth.user?.roles ?? []
  return Array.isArray(r) && r.some((x) => (typeof x === 'string' ? x === 'superadmin' : x?.name === 'superadmin'))
})
const canView = computed(() => isSuperAdmin.value || permissions.value.includes('view_attendance_logs'))
const canForceLogout = computed(() => isSuperAdmin.value || permissions.value.includes('force_logout'))
const canExport = computed(() => isSuperAdmin.value || permissions.value.includes('export_attendance_data'))

const ATTENDANCE_COLUMNS = [
  { key: 'sr', label: 'SR' },
  { key: 'employee_name', label: 'Employee Name' },
  { key: 'employee_id', label: 'Employee ID' },
  { key: 'role', label: 'Role' },
  { key: 'login_date', label: 'Login Date' },
  { key: 'login_time', label: 'Login Time' },
  { key: 'logout_time', label: 'Logout Time' },
  { key: 'duration_text', label: 'Total Duration' },
  { key: 'status', label: 'Status' },
]

const SORTABLE_COLUMNS = ['employee_name', 'employee_id', 'role', 'login_date', 'login_time', 'status']
/** Map visible column key to API sort param (backend uses login_at for date ordering). */
function sortKey(col) {
  if (col === 'login_date' || col === 'login_time') return 'login_at'
  return col
}

const loading = ref(true)
const loadError = ref(null)
const logs = ref([])
const meta = ref({ current_page: 1, last_page: 1, per_page: 10, total: 0 })
const filterOptions = ref({ users: [], roles: [] })
const summary = ref({
  total_users: 0,
  logged_in: 0,
  logged_out: 0,
  missing_logout: 0,
})
const filters = ref({
  user_id: '',
  role: '',
  from: '',
  to: '',
  status: '',
})
const { perPage, perPageOptions, perPageReady, setPerPage } = useTablePageSize('attendance')
const sort = ref('login_at')
const order = ref('desc')
const advancedVisible = ref(false)
const columnModalVisible = ref(false)
const visibleColumns = ref(ATTENDANCE_COLUMNS.map((c) => c.key))
const allColumns = ref([...ATTENDANCE_COLUMNS])
const forceLogoutLoading = ref(null)
const exportLoading = ref(false)
const forceLogoutConfirmRow = ref(null)

const showToast = ref(false)
const toastType = ref('success')
const toastMsg  = ref('')
function toast(t, m) { toastType.value = t; toastMsg.value = m; showToast.value = true }

function buildParams() {
  const p = {
    page: meta.value.current_page,
    per_page: perPage.value,
    sort: sort.value,
    order: order.value,
  }
  if (filters.value.user_id) p.user_id = filters.value.user_id
  if (filters.value.role) p.role = filters.value.role
  if (filters.value.from) p.from = filters.value.from
  if (filters.value.to) p.to = filters.value.to
  if (filters.value.status) p.status = filters.value.status
  return p
}

async function load() {
  if (!canView.value) return
  loading.value = true
  loadError.value = null
  try {
    const { data } = await attendanceLogApi.index(buildParams())
    logs.value = data.data ?? []
    meta.value = data.meta ?? { current_page: 1, last_page: 1, per_page: 10, total: 0 }
  } catch (e) {
    loadError.value = e?.response?.data?.message || 'Failed to load attendance log.'
    logs.value = []
  } finally {
    loading.value = false
  }
}

async function loadFilters() {
  try {
    const { data } = await attendanceLogApi.filters()
    filterOptions.value = {
      users: data.users ?? [],
      roles: data.roles ?? [],
    }
  } catch {
    filterOptions.value = { users: [], roles: [] }
  }
}

async function loadSummary() {
  if (!canView.value) return
  try {
    const { data } = await attendanceLogApi.summary()
    // Support both flat response and nested data (e.g. { data: { total_users, ... } })
    const raw = data?.data ?? data
    summary.value = {
      total_users: raw?.total_users ?? 0,
      logged_in: raw?.logged_in ?? 0,
      logged_out: raw?.logged_out ?? 0,
      missing_logout: raw?.missing_logout ?? 0,
    }
  } catch {
    // Keep previous summary on error so numbers don't disappear
  }
}

function applyFilters() {
  meta.value.current_page = 1
  load()
}

function resetFilters() {
  filters.value = { user_id: '', role: '', from: '', to: '', status: '' }
  meta.value.current_page = 1
  load()
}

function onSort({ sort: s, order: o }) {
  sort.value = s
  order.value = o
  meta.value.current_page = 1
  load()
}

function onPageChange(page) {
  meta.value.current_page = page
  load()
}

function onPerPageChange(e) {
  setPerPage(e.target.value)
  meta.value.current_page = 1
  load()
}

function onSaveColumns(cols) {
  visibleColumns.value = cols
  columnModalVisible.value = false
}

function toggleSort(col) {
  const apiSort = sortKey(col)
  const nextOrder = sort.value === apiSort && order.value === 'asc' ? 'desc' : 'asc'
  sort.value = apiSort
  order.value = nextOrder
  meta.value.current_page = 1
  load()
}

function sortable(col) {
  return SORTABLE_COLUMNS.includes(col)
}

function columnLabel(key) {
  return ATTENDANCE_COLUMNS.find((c) => c.key === key)?.label ?? key
}

/** Display API login_date as dd-MMM-yyyy (e.g. 02-Feb-2027). */
function formatLoginDateDisplay(str) {
  if (!str) return '—'
  if (typeof str === 'string') {
    const ymd = str.trim().slice(0, 10)
    const formatted = toDdMonYyyyDash(ymd)
    if (formatted) return formatted
  }
  const d = new Date(str)
  if (Number.isNaN(d.getTime())) return str
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return toDdMonYyyyDash(`${y}-${m}-${day}`) || '—'
}

const fromDisplay = computed({
  get: () => toDdMmYyyy(filters.value.from),
  set: (v) => { filters.value.from = fromDdMmYyyy(v) || '' },
})
const toDisplay = computed({
  get: () => toDdMmYyyy(filters.value.to),
  set: (v) => { filters.value.to = fromDdMmYyyy(v) || '' },
})

function openForceLogoutConfirm(row) {
  if (!canForceLogout.value || !row?.id) return
  forceLogoutConfirmRow.value = row
}

function closeForceLogoutConfirm() {
  forceLogoutConfirmRow.value = null
}

async function confirmForceLogout() {
  const row = forceLogoutConfirmRow.value
  if (!row?.id) return
  forceLogoutLoading.value = row.id
  try {
    await attendanceLogApi.forceLogoutLog(row.id)
    toast('success', 'User logged out successfully.')
    closeForceLogoutConfirm()
    await loadSummary()
    await load()
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to force logout.')
  } finally {
    forceLogoutLoading.value = null
  }
}

function statusPillClass(status) {
  if (status === 'logged_out') return 'bg-gray-100 text-gray-800'
  if (status === 'logged_in') return 'bg-blue-100 text-blue-800'
  if (status === 'missing_logout') return 'bg-red-100 text-red-800'
  return 'bg-gray-100 text-gray-800'
}

function statusLabel(status) {
  if (status === 'logged_out') return 'Logged Out'
  if (status === 'logged_in') return 'Logged In'
  if (status === 'missing_logout') return 'Missing Logout'
  return status ?? '—'
}

function rowClass(row) {
  if (row.status === 'missing_logout') return 'bg-red-50'
  return ''
}

async function onExport() {
  if (!canExport.value) return
  exportLoading.value = true
  try {
    const params = { ...buildParams(), page: 1, per_page: 5000 }
    const { data } = await attendanceLogApi.index(params)
    const rows = data.data ?? []
    const headers = ['Employee Name', 'Employee ID', 'Role', 'Login Date', 'Login Time', 'Logout Time', 'Total Duration', 'Status']
    const escape = (v) => (v == null ? '' : String(v).includes(',') || String(v).includes('"') ? '"' + String(v).replace(/"/g, '""') + '"' : v)
    const lines = [headers.map(escape).join(',')]
    for (const r of rows) {
      lines.push([
        r.employee_name,
        r.employee_id,
        r.role,
        formatLoginDateDisplay(r.login_date),
        r.login_time,
        r.logout_time || 'Not logged out',
        r.duration_text,
        statusLabel(r.status),
      ].map(escape).join(','))
    }
    const blob = new Blob([lines.join('\r\n')], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `attendance-log-${new Date().toISOString().slice(0, 10)}.csv`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    //
  } finally {
    exportLoading.value = false
  }
}

onMounted(() => {
  if (canView.value) {
    loadFilters()
    loadSummary()
    load()
  }
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-white py-6 px-4 sm:px-6">
    <div class="mx-auto max-w-7xl space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-baseline gap-2">
          <h1 class="text-xl font-semibold text-gray-900 leading-tight">Attendance Log</h1>
          <Breadcrumbs />
        </div>
        <div v-if="canExport" class="flex items-center gap-2">
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
        </div>
      </div>
      <p class="text-sm text-gray-500">Track employee login and logout activity.</p>

      <div v-if="!canView" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        You do not have permission to view the attendance log.
      </div>

      <template v-else>
        <div v-if="loadError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {{ loadError }}
        </div>

        <!-- Summary cards: Total Users, Logged In, Logged Out, Missing Logout -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="min-w-0">
              <p class="text-sm font-medium text-gray-500 leading-tight">Total Users</p>
              <p class="mt-0.5 min-h-[2rem] text-2xl font-bold tabular-nums leading-tight text-gray-900">{{ summary.total_users }}</p>
            </div>
            <div class="ml-3 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16" />
              </svg>
            </div>
          </div>
          <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="min-w-0">
              <p class="text-sm font-medium text-gray-500 leading-tight">Logged In</p>
              <p class="mt-0.5 min-h-[2rem] text-2xl font-bold tabular-nums leading-tight text-blue-600">{{ summary.logged_in }}</p>
            </div>
            <div class="ml-3 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-600">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
              </svg>
            </div>
          </div>
          <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="min-w-0">
              <p class="text-sm font-medium text-gray-500 leading-tight">Logged Out</p>
              <p class="mt-0.5 min-h-[2rem] text-2xl font-bold tabular-nums leading-tight text-gray-900">{{ summary.logged_out }}</p>
            </div>
            <div class="ml-3 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
            </div>
          </div>
          <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="min-w-0">
              <p class="text-sm font-medium text-gray-500 leading-tight">Missing Logout</p>
              <p class="mt-0.5 min-h-[2rem] text-2xl font-bold tabular-nums leading-tight text-red-600">{{ summary.missing_logout }}</p>
            </div>
            <div class="ml-3 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-red-100 text-red-600">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
            </div>
          </div>
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
              class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm text-gray-700 hover:bg-gray-50"
              @click="advancedVisible = !advancedVisible"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
              Advanced Filters
            </button>
            <button
              type="button"
              class="inline-flex shrink-0 items-center whitespace-nowrap rounded border border-gray-300 bg-white px-2.5 py-2 text-sm text-gray-700 hover:bg-gray-50"
              @click="columnModalVisible = true"
            >
              Customize Columns
              <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
          </template>
        </FiltersBar>

        <div v-show="advancedVisible" class="rounded-lg border border-gray-200 bg-gray-50/50 p-4">
          <h3 class="mb-3 text-sm font-medium text-gray-700">Advanced Filters</h3>
          <div class="flex flex-wrap items-end gap-4">
            <div>
              <label class="block text-xs font-medium text-gray-600">From</label>
              <input
                v-model="fromDisplay"
                type="text"
                placeholder="dd-mm-yyyy"
                class="mt-0.5 min-w-[120px] rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                :disabled="loading"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600">To</label>
              <input
                v-model="toDisplay"
                type="text"
                placeholder="dd-mm-yyyy"
                class="mt-0.5 min-w-[120px] rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
                :disabled="loading"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600">Status</label>
              <select
                v-model="filters.status"
                class="mt-0.5 min-w-[140px] rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
                :disabled="loading"
              >
                <option value="">All</option>
                <option value="logged_in">Logged In</option>
                <option value="logged_out">Logged Out</option>
                <option value="missing_logout">Missing Logout</option>
              </select>
            </div>
            <button
              type="button"
              class="rounded bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
              :disabled="loading"
              @click="applyFilters"
            >
              Apply
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
              :disabled="loading"
              @click="resetFilters"
            >
              Reset
            </button>
          </div>
        </div>

        <!-- Table: green header, white body, borders like other tables -->
        <div class="overflow-hidden rounded-lg border-2 border-black bg-white shadow-sm">
          <div class="relative overflow-x-auto">
            <div
              v-if="loading"
              class="absolute inset-0 z-10 flex items-center justify-center bg-white/80"
            >
              <svg class="h-8 w-8 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
            </div>
            <table class="min-w-full border-collapse">
              <thead class="bg-green-700 border-b-2 border-black">
                <tr>
                  <th
                    v-for="col in visibleColumns"
                    :key="col"
                    class="whitespace-nowrap px-4 py-3 text-left text-sm font-bold uppercase tracking-wider text-white"
                  >
                    <button
                      v-if="sortable(col)"
                      type="button"
                      class="inline-flex items-center gap-1 font-bold text-white hover:text-white/90"
                      @click="toggleSort(col)"
                    >
                      {{ columnLabel(col) }}
                      <svg v-if="sortKey(col) === sort" class="h-4 w-4" :class="order === 'asc' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                      </svg>
                    </button>
                    <span v-else class="font-bold text-white">{{ columnLabel(col) }}</span>
                  </th>
                  <th v-if="canForceLogout" class="whitespace-nowrap px-4 py-3 text-right text-sm font-bold uppercase tracking-wider text-white">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-white">
                <tr v-if="!loading && !logs.length" class="border-b border-black">
                  <td :colspan="visibleColumns.length + (canForceLogout ? 1 : 0)" class="px-4 py-12 text-center text-sm text-gray-500">No attendance records found.</td>
                </tr>
                <tr
                  v-for="(row, rowIndex) in logs"
                  :key="row.id"
                  class="border-b border-black bg-white hover:bg-gray-50/50"
                  :class="rowClass(row)"
                >
                  <td v-for="col in visibleColumns" :key="col" class="whitespace-nowrap border-black px-4 py-3 text-sm text-gray-900">
                    <template v-if="col === 'sr'">
                      {{ (meta.current_page - 1) * meta.per_page + rowIndex + 1 }}
                    </template>
                    <template v-else-if="col === 'login_date'">
                      {{ formatLoginDateDisplay(row.login_date) }}
                    </template>
                    <template v-else-if="col === 'logout_time'">
                      <span v-if="row.logout_time">{{ row.logout_time }}</span>
                      <span v-else class="italic text-gray-500">Not logged out</span>
                    </template>
                    <template v-else-if="col === 'duration_text'">
                      <span v-if="row.duration_state === 'missing'" class="font-bold text-red-600">{{ row.duration_text }}</span>
                      <span v-else-if="row.duration_state === 'in_progress'" class="italic text-gray-500">{{ row.duration_text }}</span>
                      <span v-else>{{ row.duration_text }}</span>
                    </template>
                    <template v-else-if="col === 'status'">
                      <span
                        class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                        :class="statusPillClass(row.status)"
                      >
                        {{ statusLabel(row.status) }}
                      </span>
                    </template>
                    <template v-else>
                      {{ row[col] }}
                    </template>
                  </td>
                  <td v-if="canForceLogout" class="whitespace-nowrap border-black px-4 py-3 text-right">
                    <button
                      v-if="row.status === 'logged_in' || row.status === 'missing_logout'"
                      type="button"
                      class="rounded bg-red-600 px-2 py-1 text-xs font-medium text-white hover:bg-red-700 disabled:opacity-50"
                      :disabled="forceLogoutLoading === row.id"
                      @click="openForceLogoutConfirm(row)"
                    >
                      {{ forceLogoutLoading === row.id ? '...' : 'Force Logout' }}
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-3 border-t border-black bg-white px-4 py-3">
            <!-- Left: entries info -->
            <p class="text-sm text-gray-600">
              Showing {{ meta.total ? (meta.current_page - 1) * meta.per_page + 1 : 0 }} to {{ Math.min(meta.current_page * meta.per_page, meta.total) }} of {{ meta.total }} entries
            </p>

            <!-- Right: Number of rows + Previous / Page X of Y / Next -->
            <div class="flex items-center gap-4">
              <div class="flex items-center gap-2 text-sm text-gray-600">
                <span class="whitespace-nowrap font-medium">Number of rows</span>
                <select
                  :value="perPage"
                  class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm min-w-[80px] text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
                  @change="onPerPageChange"
                >
                  <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
                </select>
              </div>

              <div class="flex items-center gap-1.5">
                <button
                  type="button"
                  :disabled="meta.current_page <= 1"
                  class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                  @click="onPageChange(meta.current_page - 1)"
                >Previous</button>
                <span class="rounded-md border border-gray-300 bg-gray-50 px-3 py-1.5 text-sm text-gray-700">
                  Page {{ meta.current_page }} of {{ meta.last_page }}
                </span>
                <button
                  type="button"
                  :disabled="meta.current_page >= meta.last_page"
                  class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                  @click="onPageChange(meta.current_page + 1)"
                >Next</button>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>

    <ColumnCustomizerModal
      :visible="columnModalVisible"
      :all-columns="allColumns"
      :visible-columns="visibleColumns"
      :default-columns="ATTENDANCE_COLUMNS.map((c) => c.key)"
      @update:visible="columnModalVisible = $event"
      @save="onSaveColumns"
    />

    <!-- Force Logout confirmation modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="forceLogoutConfirmRow"
          class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-500/50 p-4"
          role="dialog"
          aria-modal="true"
          aria-labelledby="force-logout-title"
          @click.self="closeForceLogoutConfirm"
        >
          <div class="w-full max-w-md rounded-xl bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
              <h2 id="force-logout-title" class="text-lg font-semibold text-gray-900">Force logout session</h2>
              <button
                type="button"
                class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                aria-label="Close"
                @click="closeForceLogoutConfirm"
              >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <div class="px-6 py-4">
              <p class="text-sm text-gray-600">
                This will end the session for
                <span class="font-medium text-gray-900">{{ forceLogoutConfirmRow?.employee_name ?? 'this user' }}</span>.
                They will need to log in again to access the system.
              </p>
              <p class="mt-2 text-sm text-gray-600">Do you want to continue?</p>
            </div>
            <div class="flex justify-end gap-3 border-t border-gray-200 px-6 py-4">
              <button
                type="button"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                @click="closeForceLogoutConfirm"
              >
                Cancel
              </button>
              <button
                type="button"
                class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                :disabled="forceLogoutLoading === forceLogoutConfirmRow?.id"
                @click="confirmForceLogout"
              >
                {{ forceLogoutLoading === forceLogoutConfirmRow?.id ? 'Logging out...' : 'Force Logout' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="4000" @dismiss="showToast = false" />
  </div>
</template>
