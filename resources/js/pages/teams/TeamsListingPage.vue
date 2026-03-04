<script setup>
/**
 * Teams Index – full CRUD listing with stats cards, search, advanced filters,
 * sortable/customizable columns, bulk actions (delete, status change),
 * and RBAC-aware action buttons.
 */
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/lib/axios'
import teamsApi from '@/services/teamsApi'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import ColumnCustomizerModal from '@/components/lead-submissions/ColumnCustomizerModal.vue'
import Toast from '@/components/Toast.vue'
import { canModuleAction } from '@/lib/accessControl'
import { formatUserDate } from '@/lib/dateFormat'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const TABLE_MODULE = 'teams'
const perPageOptions = ref([10, 20, 25, 50, 100])

/* ───── Toast ───── */
const showToast = ref(false)
const toastType = ref('success')
const toastMsg  = ref('')
function toast(t, m) { toastType.value = t; toastMsg.value = m; showToast.value = true }

/* ───── Auth ───── */
const isSuperAdmin = computed(() => auth.user?.roles?.includes('superadmin') ?? false)
const perms = computed(() => auth.user?.permissions ?? [])
const canCreate = computed(() => canModuleAction(auth.user, 'teams', 'create'))
const canEdit = computed(() => canModuleAction(auth.user, 'teams', 'edit'))
const canDelete = computed(() => canModuleAction(auth.user, 'teams', 'delete'))
const canViewAction = computed(() => canModuleAction(auth.user, 'teams', 'view'))
const hasSelectionColumn = computed(() => canDelete.value || canEdit.value)
const hasAnyRowAction = computed(() => canViewAction.value || canEdit.value || canDelete.value)

/* ───── State ───── */
const loading = ref(true)
const tableLoading = ref(false)
const stats = ref({ total: 0, active: 0, inactive: 0 })
const tableData = ref([])
const tableMeta = ref({ current_page: 1, last_page: 1, per_page: auth.defaultTablePageSize || 25, total: 0 })
const sort = ref('name')
const order = ref('asc')

/* ───── Filters ───── */
const filterOptions = ref({ statuses: [], departments: [], managers: [], team_leaders: [] })
const filtersVisible = ref(false)
const searchQ = ref('')
const filters = ref({ status: '', department: '', manager_id: '', team_leader_id: '' })

/* ───── Column customization ───── */
const columnModalVisible = ref(false)
const allColumns = ref([])
const visibleColumns = ref([
  'sr', 'name', 'manager', 'team_leader', 'department', 'status', 'members_count', 'created_at',
])
const defaultVisibleColumns = [
  'sr', 'name', 'manager', 'team_leader', 'department', 'status', 'members_count', 'created_at',
]

const COLUMN_LABELS = {
  sr: 'SR',
  name: 'Team Name',
  description: 'Description',
  manager: 'Manager',
  team_leader: 'Team Leader',
  department: 'Department',
  status: 'Status',
  max_members: 'Max Members',
  members_count: 'Members',
  created_at: 'Created',
}

/* ───── Selection & Bulk Actions ───── */
const selectedIds = ref([])
const bulkLoading = ref(false)

const allSelected = computed({
  get: () => selectedIds.value.length > 0 && selectedIds.value.length === tableData.value.length,
  set: (v) => { selectedIds.value = v ? tableData.value.map((r) => r.id) : [] },
})

/* ───── Delete modal ───── */
const deleteModal = ref({ visible: false, team: null, loading: false })

/* ───── Computed ───── */
const activeColumns = computed(() =>
  visibleColumns.value.map((key) => ({
    key,
    label: COLUMN_LABELS[key] || key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()),
  }))
)

const activeFilterCount = computed(() => {
  let count = 0
  if (filters.value.status) count++
  if (filters.value.department) count++
  if (filters.value.manager_id) count++
  if (filters.value.team_leader_id) count++
  return count
})

const params = computed(() => {
  const p = {
    page: tableMeta.value.current_page,
    per_page: tableMeta.value.per_page,
    sort: sort.value,
    order: order.value,
    columns: visibleColumns.value.map((c) => (c === 'sr' ? 'id' : c)),
  }
  if (searchQ.value?.trim()) p.q = searchQ.value.trim()
  if (filters.value.status) p.status = filters.value.status
  if (filters.value.department) p.department = filters.value.department
  if (filters.value.manager_id) p.manager_id = filters.value.manager_id
  if (filters.value.team_leader_id) p.team_leader_id = filters.value.team_leader_id
  return p
})

/* ───── Data loading ───── */
async function loadFilterOptions() {
  try {
    filterOptions.value = await teamsApi.filters()
  } catch {
    filterOptions.value = { statuses: [], departments: [], managers: [], team_leaders: [] }
  }
}

async function loadTable() {
  tableLoading.value = true
  try {
    const data = await teamsApi.index(params.value)
    tableData.value = data.data ?? []
    tableMeta.value = data.meta ?? tableMeta.value
    stats.value = data.stats ?? stats.value
    selectedIds.value = []
  } catch {
    tableData.value = []
  } finally {
    tableLoading.value = false
  }
}

async function loadColumns() {
  try {
    const data = await teamsApi.columns()
    allColumns.value = (data.all_columns ?? []).map((col) =>
      col?.key === 'id' ? { ...col, key: 'sr', label: 'SR' } : col
    )
    const visible = data.visible_columns
    if (Array.isArray(visible) && visible.length) {
      visibleColumns.value = visible.map((c) => (c === 'id' ? 'sr' : c))
    }
  } catch { /* keep defaults */ }
}

/* ───── Actions ───── */
function applySearch() {
  tableMeta.value.current_page = 1
  loadTable()
}

function applyFilters() {
  tableMeta.value.current_page = 1
  loadTable()
}

function resetFilters() {
  filters.value = { status: '', department: '', manager_id: '', team_leader_id: '' }
  searchQ.value = ''
  tableMeta.value.current_page = 1
  loadTable()
}

function onSort(colKey) {
  if (colKey === 'sr') return
  if (sort.value === colKey) {
    order.value = order.value === 'asc' ? 'desc' : 'asc'
  } else {
    sort.value = colKey
    order.value = 'asc'
  }
  tableMeta.value.current_page = 1
  loadTable()
}

function onPageChange(page) {
  tableMeta.value.current_page = page
}

async function onPerPageChange(e) {
  const val = Number(e.target.value)
  tableMeta.value.per_page = val
  tableMeta.value.current_page = 1
  loadTable()
  try { await api.post(`/table-preferences/${TABLE_MODULE}`, { per_page: val }) } catch { /* silent */ }
}

watch(() => tableMeta.value.current_page, () => loadTable())

async function onSaveColumns(cols) {
  try {
    await teamsApi.saveColumns(cols.map((c) => (c === 'sr' ? 'id' : c)))
    visibleColumns.value = cols
    tableMeta.value.current_page = 1
    loadTable()
  } catch { /* silent */ }
}

/* ───── Bulk Actions ───── */
async function bulkSetStatus(status) {
  if (!selectedIds.value.length) return
  bulkLoading.value = true
  try {
    await teamsApi.bulkStatusChange(selectedIds.value, status)
    toast('success', `${selectedIds.value.length} team(s) set to ${status}.`)
    loadTable()
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Bulk status change failed.')
  } finally {
    bulkLoading.value = false
  }
}

async function bulkDeleteSelected() {
  if (!selectedIds.value.length) return
  if (!confirm(`Delete ${selectedIds.value.length} team(s)? This cannot be undone.`)) return
  bulkLoading.value = true
  try {
    await teamsApi.bulkDelete(selectedIds.value)
    toast('success', `${selectedIds.value.length} team(s) deleted.`)
    loadTable()
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Bulk delete failed.')
  } finally {
    bulkLoading.value = false
  }
}

/* ───── Single Delete ───── */
function confirmDelete(team) {
  deleteModal.value = { visible: true, team, loading: false }
}

async function executeDelete() {
  if (!deleteModal.value.team) return
  deleteModal.value.loading = true
  try {
    await teamsApi.destroy(deleteModal.value.team.id)
    deleteModal.value.visible = false
    toast('success', `Team "${deleteModal.value.team.name}" deleted.`)
    loadTable()
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to delete team.')
  } finally {
    deleteModal.value.loading = false
  }
}

/* ───── Format helpers ───── */
function statusBadgeClass(status) {
  return status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
}

function formatDate(d) {
  return formatUserDate(d, '—')
}

function cellValue(row, key) {
  if (key === 'created_at') return formatDate(row[key])
  if (key === 'status') return null
  return row[key] != null ? String(row[key]) : '—'
}

function initials(name) {
  if (!name) return '?'
  return name.split(' ').map((w) => w[0]).join('').toUpperCase().slice(0, 2)
}

/* ───── Load user table preference ───── */
async function loadTablePreference() {
  try {
    const { data } = await api.get(`/table-preferences/${TABLE_MODULE}`)
    if (data.per_page) tableMeta.value.per_page = Number(data.per_page)
    if (Array.isArray(data.options) && data.options.length) perPageOptions.value = data.options
  } catch { /* use system default */ }
}

/* ───── Init ───── */
onMounted(async () => {
  loading.value = true
  await Promise.all([loadFilterOptions(), loadColumns(), loadTablePreference()])
  await loadTable()
  loading.value = false

  if (route.query.created === '1') toast('success', 'Team created successfully.')
  if (route.query.updated === '1') toast('success', 'Team updated successfully.')
})
</script>

<template>
  <div class="space-y-6 bg-white -mx-4 -my-5 min-h-full px-6 py-6">
    <Toast :show="showToast" :type="toastType" :message="toastMsg" @dismiss="showToast = false" />

    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <h1 class="text-2xl font-bold text-gray-900">Teams Management</h1>
          <Breadcrumbs />
        </div>
        <p class="text-sm text-gray-500 mt-1">Manage teams, assign members, and organize your workforce.</p>
      </div>
      <button
        v-if="canCreate"
        type="button"
        class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-green-700 transition-colors"
        @click="router.push('/teams/create')"
      >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
        Create Team
      </button>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="rounded-xl bg-gray-800 text-white p-5 shadow cursor-pointer hover:bg-gray-700 transition-colors" @click="filters.status = ''; applyFilters()">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-300">Total Teams</p>
            <p class="text-2xl font-bold mt-1">{{ stats.total }}</p>
          </div>
          <div class="rounded-full bg-emerald-500/20 p-3">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
          </div>
        </div>
      </div>
      <div class="rounded-xl bg-gray-800 text-white p-5 shadow cursor-pointer hover:bg-gray-700 transition-colors" @click="filters.status = 'active'; applyFilters()">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-300">Active</p>
            <p class="text-2xl font-bold mt-1">{{ stats.active }}</p>
          </div>
          <div class="rounded-full bg-green-500/20 p-3">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          </div>
        </div>
      </div>
      <div class="rounded-xl bg-gray-800 text-white p-5 shadow cursor-pointer hover:bg-gray-700 transition-colors" @click="filters.status = 'inactive'; applyFilters()">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-300">Inactive</p>
            <p class="text-2xl font-bold mt-1">{{ stats.inactive }}</p>
          </div>
          <div class="rounded-full bg-red-500/20 p-3">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Toolbar: Search + Buttons -->
    <div class="flex flex-wrap items-center gap-3">
      <div class="relative flex-1 min-w-[200px] max-w-md">
        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
        </span>
        <input v-model="searchQ" type="text" placeholder="Search by name, description, department…" class="w-full rounded-lg border border-gray-300 py-2 pl-9 pr-4 text-sm focus:border-green-500 focus:ring-green-500" @keydown.enter="applySearch" />
      </div>

      <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="filtersVisible = !filtersVisible">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
        Advanced Filters
        <span v-if="activeFilterCount" class="ml-1 inline-flex h-5 w-5 items-center justify-center rounded-full bg-green-600 text-[10px] font-bold text-white">{{ activeFilterCount }}</span>
      </button>

      <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="columnModalVisible = true">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" /></svg>
        Customize Columns
      </button>

      <!-- Bulk actions (visible when items selected) -->
      <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 translate-x-2" enter-to-class="opacity-100 translate-x-0" leave-active-class="transition ease-in duration-100" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="selectedIds.length" class="flex items-center gap-2 ml-auto">
          <span class="text-sm text-gray-600 font-medium">{{ selectedIds.length }} selected</span>
          <button v-if="canEdit" type="button" class="rounded-lg bg-green-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-green-700" :disabled="bulkLoading" @click="bulkSetStatus('active')">Activate</button>
          <button v-if="canEdit" type="button" class="rounded-lg bg-yellow-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-yellow-600" :disabled="bulkLoading" @click="bulkSetStatus('inactive')">Deactivate</button>
          <button v-if="canDelete" type="button" class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-700" :disabled="bulkLoading" @click="bulkDeleteSelected">Delete</button>
        </div>
      </Transition>
    </div>

    <!-- Filters Panel -->
    <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0" leave-active-class="transition ease-in duration-100" leave-from-class="opacity-100" leave-to-class="opacity-0">
      <div v-if="filtersVisible" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-sm font-semibold text-gray-900">Advanced Filters</h3>
          <button type="button" class="text-sm text-green-600 hover:text-green-700 font-medium" @click="resetFilters">Reset All</button>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
            <select v-model="filters.status" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-green-500 focus:border-green-500">
              <option value="">All Statuses</option>
              <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Department</label>
            <select v-model="filters.department" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-green-500 focus:border-green-500">
              <option value="">All Departments</option>
              <option v-for="d in filterOptions.departments" :key="d.value" :value="d.value">{{ d.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Manager</label>
            <select v-model="filters.manager_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-green-500 focus:border-green-500">
              <option value="">All Managers</option>
              <option v-for="m in filterOptions.managers" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Team Leader</label>
            <select v-model="filters.team_leader_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-green-500 focus:border-green-500">
              <option value="">All Team Leaders</option>
              <option v-for="tl in filterOptions.team_leaders" :key="tl.id" :value="tl.id">{{ tl.name }}</option>
            </select>
          </div>
        </div>
        <div class="flex justify-end mt-3">
          <button type="button" class="rounded-lg bg-green-600 px-5 py-2 text-sm font-medium text-white hover:bg-green-700" @click="applyFilters">Apply Filters</button>
        </div>
      </div>
    </Transition>

    <!-- Table -->
    <div class="rounded-xl border-2 border-black bg-white shadow-sm overflow-hidden">
      <div class="relative overflow-x-auto">
        <div
          v-if="tableLoading"
          class="absolute inset-0 z-10 flex items-center justify-center bg-white/80"
          aria-live="polite"
          aria-busy="true"
        >
          <div class="flex flex-col items-center gap-2">
            <svg class="h-8 w-8 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
            </svg>
            <span class="text-sm font-medium text-gray-600">Updating...</span>
          </div>
        </div>
        <table class="min-w-full border-2 border-black border-collapse">
          <thead>
            <tr class="border-b-2 border-black bg-green-600">
              <th v-if="hasSelectionColumn" class="w-10 px-3 py-3 text-left">
                <input type="checkbox" v-model="allSelected" class="rounded border-gray-300 text-green-600 focus:ring-green-500" />
              </th>
              <th
                v-for="col in activeColumns"
                :key="col.key"
                class="whitespace-nowrap px-4 py-3 text-left text-sm font-semibold text-white cursor-pointer select-none"
                @click="onSort(col.key)"
              >
                <div class="flex items-center gap-1">
                  <span>{{ col.label }}</span>
                  <span v-if="sort === col.key" class="text-white">
                    <svg v-if="order === 'asc'" class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" /></svg>
                    <svg v-else class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                  </span>
                  <span v-else class="text-white/70">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" /></svg>
                  </span>
                </div>
              </th>
              <th v-if="hasAnyRowAction" class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold text-white w-28">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white">
            <tr v-if="!tableData.length" class="border-b border-black">
              <td :colspan="activeColumns.length + (hasSelectionColumn ? 1 : 0) + (hasAnyRowAction ? 1 : 0)" class="px-4 py-12 text-center text-sm text-gray-500">No teams found.</td>
            </tr>
            <tr v-for="(row, rowIndex) in tableData" :key="row.id" class="border-b border-black bg-white hover:bg-gray-50/50">
              <td v-if="hasSelectionColumn" class="w-10 px-3 py-3">
                <input type="checkbox" :value="row.id" v-model="selectedIds" class="rounded border-gray-300 text-green-600 focus:ring-green-500" />
              </td>
              <td v-for="col in activeColumns" :key="col.key" class="whitespace-nowrap px-4 py-3 text-sm text-gray-900">
                <template v-if="col.key === 'sr'">
                  <span class="text-gray-700">{{ ((tableMeta.current_page - 1) * tableMeta.per_page) + rowIndex + 1 }}</span>
                </template>
                <!-- Team Name: clickable link -->
                <template v-else-if="col.key === 'name'">
                  <router-link :to="`/teams/${row.id}`" class="font-medium text-green-600 hover:text-green-700 hover:underline">{{ row.name || '—' }}</router-link>
                </template>

                <!-- Status badge -->
                <template v-else-if="col.key === 'status'">
                  <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusBadgeClass(row.status)]">
                    {{ row.status === 'active' ? 'Active' : 'Inactive' }}
                  </span>
                </template>

                <!-- Manager / Team Leader: avatar + name -->
                <template v-else-if="col.key === 'manager' || col.key === 'team_leader'">
                  <div v-if="row[col.key]" class="flex items-center gap-2">
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-gray-200 text-[10px] font-bold text-gray-600 flex-shrink-0">{{ initials(row[col.key]) }}</span>
                    <span class="text-gray-700">{{ row[col.key] }}</span>
                  </div>
                  <span v-else class="text-gray-400">—</span>
                </template>

                <!-- Department badge -->
                <template v-else-if="col.key === 'department'">
                  <span v-if="row.department" class="inline-flex rounded-full bg-blue-50 border border-blue-200 px-2.5 py-0.5 text-xs font-medium text-blue-700">{{ row.department }}</span>
                  <span v-else class="text-gray-400">—</span>
                </template>

                <!-- Members count -->
                <template v-else-if="col.key === 'members_count'">
                  <span class="inline-flex items-center gap-1 text-gray-700">
                    <svg class="h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                    {{ row.members_count ?? 0 }}
                  </span>
                </template>

                <!-- Default cell -->
                <template v-else>
                  <span class="text-gray-700">{{ cellValue(row, col.key) }}</span>
                </template>
              </td>

              <!-- Actions column -->
              <td v-if="hasAnyRowAction" class="whitespace-nowrap px-4 py-3 text-right">
                <div class="inline-flex items-center justify-end gap-1">
                  <router-link
                    v-if="canViewAction"
                    :to="`/teams/${row.id}`"
                    class="rounded-full p-1.5 text-blue-600 hover:bg-blue-50"
                    title="View details"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </router-link>
                  <router-link
                    v-if="canEdit"
                    :to="`/teams/${row.id}/edit`"
                    class="rounded-full p-1.5 text-green-600 hover:bg-green-50"
                    title="Edit team"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                  </router-link>
                  <button
                    v-if="canDelete"
                    type="button"
                    class="rounded-full p-1.5 text-red-600 hover:bg-red-50"
                    title="Delete team"
                    @click="confirmDelete(row)"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination footer -->
      <div class="flex flex-wrap items-center justify-between gap-3 border-t border-black bg-white px-4 py-3">
        <!-- Left: entries info -->
        <p class="text-sm text-gray-600">
          Showing {{ tableMeta.total ? ((tableMeta.current_page - 1) * tableMeta.per_page) + 1 : 0 }}–{{ Math.min(tableMeta.current_page * tableMeta.per_page, tableMeta.total) }} of {{ tableMeta.total }}
        </p>

        <!-- Right: Number of rows + Previous / Page X of Y / Next -->
        <div class="flex items-center gap-4">
          <div class="flex items-center gap-2 text-sm text-gray-600">
            <span class="whitespace-nowrap font-medium">Number of rows</span>
            <select
              :value="tableMeta.per_page"
              class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm min-w-[80px] text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
              @change="onPerPageChange"
            >
              <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
            </select>
          </div>

          <div class="flex items-center gap-1.5">
            <button
              type="button"
              :disabled="tableMeta.current_page <= 1"
              class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
              @click="onPageChange(tableMeta.current_page - 1)"
            >Previous</button>
            <span class="rounded-md border border-gray-300 bg-gray-50 px-3 py-1.5 text-sm text-gray-700">
              Page {{ tableMeta.current_page }} of {{ tableMeta.last_page }}
            </span>
            <button
              type="button"
              :disabled="tableMeta.current_page >= tableMeta.last_page"
              class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
              @click="onPageChange(tableMeta.current_page + 1)"
            >Next</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Column Customizer Modal -->
    <ColumnCustomizerModal
      :visible="columnModalVisible"
      :all-columns="allColumns"
      :visible-columns="visibleColumns"
      :default-columns="defaultVisibleColumns"
      @update:visible="columnModalVisible = $event"
      @save="onSaveColumns"
    />

    <!-- Delete Confirmation Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="deleteModal.visible" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50" @click.self="deleteModal.visible = false">
          <div class="rounded-xl bg-white shadow-xl max-w-sm w-full p-6" @click.stop>
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100 mb-4">
              <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 text-center">Delete Team</h3>
            <p class="mt-2 text-sm text-gray-600 text-center">Are you sure you want to delete <strong>{{ deleteModal.team?.name }}</strong>? This cannot be undone.</p>
            <div class="flex justify-end gap-3 mt-6">
              <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" @click="deleteModal.visible = false">Cancel</button>
              <button type="button" class="rounded-lg bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700 disabled:opacity-50" :disabled="deleteModal.loading" @click="executeDelete">
                {{ deleteModal.loading ? 'Deleting…' : 'Delete Team' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
