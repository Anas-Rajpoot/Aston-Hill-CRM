<script setup>
/**
 * Attendance Log – table from login logs: Employee Name, ID, Role, Department, Login Date/Time, Logout Time, Duration, Status.
 * Filters, Advanced Filters, Customize Columns, sortable columns, Export. Force Logout for active/missing sessions.
 * Permissions: view_attendance_logs, force_logout, export_attendance_data.
 */
import { ref, computed, onMounted } from 'vue'
import attendanceLogApi from '@/services/attendanceLogApi'
import { useAuthStore } from '@/stores/auth'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import Pagination from '@/components/Pagination.vue'
import FiltersBar from '@/components/attendance/FiltersBar.vue'
import ColumnCustomizerModal from '@/components/lead-submissions/ColumnCustomizerModal.vue'

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
  { key: 'employee_name', label: 'Employee Name' },
  { key: 'employee_id', label: 'Employee ID' },
  { key: 'role', label: 'Role' },
  { key: 'department', label: 'Department' },
  { key: 'login_date', label: 'Login Date' },
  { key: 'login_time', label: 'Login Time' },
  { key: 'logout_time', label: 'Logout Time' },
  { key: 'duration_text', label: 'Total Duration' },
  { key: 'status', label: 'Status' },
]

const SORTABLE_COLUMNS = ['employee_name', 'department', 'login_date']
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
const filters = ref({
  user_id: '',
  role: '',
  from: '',
  to: '',
  status: '',
})
const perPage = ref(10)
const sort = ref('login_at')
const order = ref('desc')
const advancedVisible = ref(false)
const columnModalVisible = ref(false)
const visibleColumns = ref(ATTENDANCE_COLUMNS.map((c) => c.key))
const allColumns = ref([...ATTENDANCE_COLUMNS])
const forceLogoutLoading = ref(null)
const exportLoading = ref(false)

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

function onPerPageChange() {
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

async function forceLogoutLog(row) {
  if (!canForceLogout.value || !row?.id) return
  if (!confirm('Force logout this session?')) return
  forceLogoutLoading.value = row.id
  try {
    await attendanceLogApi.forceLogoutLog(row.id)
    load()
  } catch {
    //
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
    const headers = ['Employee Name', 'Employee ID', 'Role', 'Department', 'Login Date', 'Login Time', 'Logout Time', 'Total Duration', 'Status']
    const escape = (v) => (v == null ? '' : String(v).includes(',') || String(v).includes('"') ? '"' + String(v).replace(/"/g, '""') + '"' : v)
    const lines = [headers.map(escape).join(',')]
    for (const r of rows) {
      lines.push([
        r.employee_name,
        r.employee_id,
        r.role,
        r.department,
        r.login_date,
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

        <div v-show="advancedVisible" class="rounded-lg border border-gray-200 bg-gray-50/50 p-4">
          <p class="text-sm text-gray-600">Use the date range (From / To) and Status filters above to narrow results.</p>
        </div>

        <!-- Table: green header, white body, borders like other tables -->
        <div class="overflow-hidden rounded-lg border border-gray-400 bg-white shadow-sm">
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
              <thead class="bg-green-700">
                <tr>
                  <th
                    v-for="col in visibleColumns"
                    :key="col"
                    class="whitespace-nowrap border-b border-gray-400 px-4 py-3 text-left text-sm font-bold uppercase tracking-wider text-white"
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
                  <th v-if="canForceLogout" class="whitespace-nowrap border-b border-gray-400 px-4 py-3 text-right text-sm font-bold uppercase tracking-wider text-white">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-white">
                <tr v-if="!loading && !logs.length" class="border-b border-gray-400">
                  <td :colspan="visibleColumns.length + (canForceLogout ? 1 : 0)" class="px-4 py-12 text-center text-sm text-gray-500">No attendance records found.</td>
                </tr>
                <tr
                  v-for="row in logs"
                  :key="row.id"
                  class="border-b border-gray-400 bg-white hover:bg-gray-50/50"
                  :class="rowClass(row)"
                >
                  <td v-for="col in visibleColumns" :key="col" class="whitespace-nowrap border-gray-400 px-4 py-3 text-sm text-gray-900">
                    <template v-if="col === 'logout_time'">
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
                  <td v-if="canForceLogout" class="whitespace-nowrap border-gray-400 px-4 py-3 text-right">
                    <button
                      v-if="row.status === 'logged_in' || row.status === 'missing_logout'"
                      type="button"
                      class="rounded bg-red-600 px-2 py-1 text-xs font-medium text-white hover:bg-red-700 disabled:opacity-50"
                      :disabled="forceLogoutLoading === row.id"
                      @click="forceLogoutLog(row)"
                    >
                      {{ forceLogoutLoading === row.id ? '...' : 'Force Logout' }}
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="flex flex-wrap items-center gap-4 border-t border-gray-400 bg-white px-4 py-3">
            <p class="text-sm text-gray-600">
              Showing {{ meta.total ? (meta.current_page - 1) * meta.per_page + 1 : 0 }} to {{ Math.min(meta.current_page * meta.per_page, meta.total) }} of {{ meta.total }} entries
            </p>
            <div class="flex items-center gap-2">
              <span class="text-sm text-gray-600">Per page</span>
              <select
                v-model.number="perPage"
                class="rounded border border-gray-300 px-2 py-1 text-sm"
                @change="onPerPageChange"
              >
                <option :value="10">10</option>
                <option :value="20">20</option>
                <option :value="50">50</option>
              </select>
            </div>
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
  </div>
</template>
