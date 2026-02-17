<script setup>
/**
 * Cisco Extensions listing – Advanced Filters, sortable table, Import/Export, Add Extension (modal), Customize Columns, Delete.
 */
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import extensionsApi from '@/services/extensionsApi'
import { useAuthStore } from '@/stores/auth'
import FiltersBar from '@/components/extensions/AdvancedFilters.vue'
import ColumnCustomizerModal from '@/components/lead-submissions/ColumnCustomizerModal.vue'
import AddExtensionModal from '@/components/extensions/AddExtensionModal.vue'
import EditExtensionModal from '@/components/extensions/EditExtensionModal.vue'
import ViewExtensionModal from '@/components/extensions/ViewExtensionModal.vue'
import ExtensionsTable from '@/components/extensions/ExtensionsTable.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import api from '@/lib/axios'
import Toast from '@/components/Toast.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const permissions = computed(() => auth.user?.permissions ?? [])
const isSuperAdmin = computed(() => {
  const r = auth.user?.roles ?? []
  return Array.isArray(r) && r.some((x) => (typeof x === 'string' ? x === 'superadmin' : x?.name === 'superadmin'))
})
const canCreate = computed(() => isSuperAdmin.value || permissions.value.includes('extensions.create'))

const loading = ref(true)
const loadError = ref(null)
const filterOptions = ref({
  gateways: [],
  statuses: [],
  usage_options: [],
})
const extensions = ref([])
const meta = ref({ current_page: 1, last_page: 1, per_page: auth.defaultTablePageSize || 25, total: 0 })
const TABLE_MODULE = 'extensions'
const perPageOptions = ref([10, 20, 25, 50, 100])
const allColumns = ref([])
const visibleColumns = ref([
  'id', 'extension', 'landline_number', 'gateway', 'username', 'password',
  'status', 'team_leader', 'manager', 'usage', 'assigned_to_name', 'comment', 'updated_at',
])
const sort = ref('extension')
const order = ref('asc')
const advancedVisible = ref(false)
const columnModalVisible = ref(false)
const exportLoading = ref(false)
const importLoading = ref(false)
const importFileInput = ref(null)
const importResult = ref(null)
const extensionToDelete = ref(null)
const deleting = ref(false)
const addModalVisible = ref(false)
const editModalVisible = ref(false)
const editModalExtensionId = ref(null)
const viewModalVisible = ref(false)
const viewModalExtensionId = ref(null)
const assignableEmployees = ref([])
const historyModalExtension = ref(null)
const historyAuditLog = ref([])
const historyLoading = ref(false)
const summary = ref({
  total_extensions: 0,
  assigned: 0,
  unassigned: 0,
  active_status: 0,
})

const showToast = ref(false)
const toastType = ref('success')
const toastMsg  = ref('')
function toast(t, m) { toastType.value = t; toastMsg.value = m; showToast.value = true }

const filters = ref({
  extension: '',
  landline_number: '',
  gateway: '',
  username: '',
  assigned_to_q: '',
  manager_q: '',
  team_leader_q: '',
  status: '',
  usage: '',
  created_from: '',
  created_to: '',
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
  if (f.extension) p.extension = f.extension
  if (f.landline_number) p.landline_number = f.landline_number
  if (f.gateway) p.gateway = f.gateway
  if (f.username) p.username = f.username
  if (f.assigned_to_q) p.assigned_to_q = f.assigned_to_q
  if (f.manager_q) p.manager_q = f.manager_q
  if (f.team_leader_q) p.team_leader_q = f.team_leader_q
  if (f.status) p.status = [f.status]
  if (f.usage) p.usage = [f.usage]
  if (f.created_from) p.created_from = f.created_from
  if (f.created_to) p.created_to = f.created_to
  return p
}

async function load() {
  loading.value = true
  loadError.value = null
  try {
    const { data } = await extensionsApi.index(buildParams())
    extensions.value = data.data ?? []
    meta.value = data.meta ?? { current_page: 1, last_page: 1, per_page: auth.defaultTablePageSize || 25, total: 0 }
  } catch (e) {
    loadError.value = e?.response?.data?.message || 'Failed to load extensions.'
    extensions.value = []
  } finally {
    loading.value = false
  }
}

async function loadFilters() {
  try {
    const { data } = await extensionsApi.filters()
    filterOptions.value = {
      gateways: data.gateways ?? [],
      statuses: data.statuses ?? [],
      usage_options: data.usage_options ?? [],
    }
  } catch {
    filterOptions.value = { gateways: [], statuses: [], usage_options: [] }
  }
}

async function loadSummary() {
  try {
    const { data } = await extensionsApi.summary()
    const raw = data?.data ?? data
    summary.value = {
      total_extensions: raw?.total_extensions ?? 0,
      assigned: raw?.assigned ?? 0,
      unassigned: raw?.unassigned ?? 0,
      active_status: raw?.active_status ?? 0,
    }
  } catch {
    // keep previous summary
  }
}

async function loadColumns() {
  try {
    const { data } = await extensionsApi.columns()
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
  filters.value.extension = ''
  filters.value.landline_number = ''
  filters.value.gateway = ''
  filters.value.username = ''
  filters.value.assigned_to_q = ''
  filters.value.manager_q = ''
  filters.value.team_leader_q = ''
  filters.value.status = ''
  filters.value.usage = ''
  filters.value.created_from = ''
  filters.value.created_to = ''
  meta.value.current_page = 1
  load()
}

function onSort({ sort: s, order: o }) {
  sort.value = s
  order.value = o
  load()
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

async function onSaveColumns(cols) {
  try {
    await extensionsApi.saveColumns(cols)
    visibleColumns.value = cols
    load()
  } catch {
    //
  }
}

function escapeCsv(val) {
  if (val == null) return ''
  const s = String(val)
  if (s.includes(',') || s.includes('"') || s.includes('\n')) return '"' + s.replace(/"/g, '""') + '"'
  return s
}

async function onExport() {
  const params = { ...buildParams(), page: 1, per_page: 1000 }
  exportLoading.value = true
  try {
    const { data } = await extensionsApi.index(params)
    const rows = data.data ?? []
    const cols = visibleColumns.value.filter((c) => c !== 'password')
    const headers = cols.map((c) => (c === 'assigned_to_name' ? 'Assigned To' : c.replace(/_/g, ' ')))
    const csvRows = [headers.map(escapeCsv).join(',')]
    for (const row of rows) {
      csvRows.push(cols.map((col) => escapeCsv(row[col])).join(','))
    }
    const blob = new Blob([csvRows.join('\r\n')], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `cisco-extensions-${new Date().toISOString().slice(0, 10)}.csv`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    //
  } finally {
    exportLoading.value = false
  }
}

function openDeleteConfirm(row) {
  extensionToDelete.value = row
}

function closeDeleteConfirm() {
  extensionToDelete.value = null
}

async function confirmDelete() {
  const row = extensionToDelete.value
  if (!row?.id) {
    closeDeleteConfirm()
    return
  }
  deleting.value = true
  try {
    await extensionsApi.destroy(row.id)
    toast('success', 'Extension deleted successfully.')
    closeDeleteConfirm()
    loadSummary()
    load()
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Failed to delete extension.')
  } finally {
    deleting.value = false
  }
}

async function loadAssignableEmployees() {
  try {
    const { data } = await extensionsApi.getAssignableEmployees()
    assignableEmployees.value = data.data ?? []
  } catch {
    assignableEmployees.value = []
  }
}

async function onUpdateCell(rowId, field, value) {
  try {
    const payload = {}
    if (field === 'assigned_to_name') {
      payload.assigned_to = value === '' || value == null ? null : Number(value)
    } else if (field === 'password') {
      // Only send password when user entered one; leave blank to keep current
      if (value !== '' && value != null) payload.password = value
    } else {
      payload[field] = value === '' ? null : value
    }
    const { data } = await extensionsApi.patch(rowId, payload)
    const updated = data.data
    if (updated) {
      const idx = extensions.value.findIndex((r) => r.id === rowId)
          if (idx >= 0) extensions.value[idx] = { ...extensions.value[idx], ...updated }
    }
  } catch {
    load()
  }
}

function openViewModal(row) {
  if (row?.id) {
    viewModalExtensionId.value = row.id
    viewModalVisible.value = true
  }
}

function onViewModalEdit() {
  const id = viewModalExtensionId.value
  viewModalVisible.value = false
  nextTick(() => {
    if (id) {
      editModalExtensionId.value = id
      editModalVisible.value = true
    }
    load()
    loadSummary()
  })
}

function openEditModal(row) {
  if (row?.id) {
    editModalExtensionId.value = row.id
    editModalVisible.value = true
  }
}

function closeEditModal() {
  editModalVisible.value = false
  editModalExtensionId.value = null
}

function onEditUpdated() {
  toast('success', 'Extension updated successfully.')
  closeEditModal()
  loadSummary()
  load()
}

function openHistoryModal(row) {
  historyModalExtension.value = row
  historyAuditLog.value = []
  if (row?.id) fetchAuditLog(row.id)
}

function closeHistoryModal() {
  historyModalExtension.value = null
  historyAuditLog.value = []
}

function getEntryChanges(entry) {
  const skipKeys = ['updated_at', 'created_at', 'id', 'deleted_at']
  const labels = entry.field_labels || {}
  const changes = []
  const oldV = entry.old_values || {}
  const newV = entry.new_values || {}
  const keys = new Set([...Object.keys(oldV), ...Object.keys(newV)])
  for (const key of keys) {
    if (skipKeys.includes(key)) continue
    const ov = oldV[key]
    const nv = newV[key]
    if (String(ov ?? '') !== String(nv ?? '')) {
      const label = labels[key] || key.replace(/_id$/, '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
      changes.push({ label, oldVal: ov, newVal: nv })
    }
  }
  return changes
}

async function fetchAuditLog(id) {
  historyLoading.value = true
  try {
    const { data } = await extensionsApi.getAuditLog(id)
    historyAuditLog.value = data.data ?? []
  } catch {
    historyAuditLog.value = []
  } finally {
    historyLoading.value = false
  }
}

onMounted(async () => {
  await loadTablePreference()
  loadFilters()
  loadColumns()
  loadAssignableEmployees()
  loadSummary()
  await load()
  const editId = route.query.edit
  if (editId) {
    editModalExtensionId.value = Number(editId) || editId
    editModalVisible.value = true
    await nextTick()
    router.replace({ path: '/cisco-extensions', query: {} })
  }
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-white py-6 px-4 sm:px-6">
    <div class="mx-auto max-w-7xl space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-baseline gap-2">
          <h1 class="text-xl font-semibold text-gray-900 leading-tight">Cisco Extensions</h1>
          <Breadcrumbs />
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button
            v-if="canCreate"
            type="button"
            class="inline-flex items-center rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
            :disabled="loading || importLoading"
            @click="triggerImport"
          >
            <svg v-if="importLoading" class="mr-1.5 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <svg v-else class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
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
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <svg v-else class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            {{ exportLoading ? 'Exporting...' : 'Export' }}
          </button>
          <button
            v-if="canCreate"
            type="button"
            class="inline-flex items-center rounded bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700"
            @click="addModalVisible = true"
          >
            <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add New Extension
          </button>
        </div>
      </div>

      <!-- Summary cards -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
          <div class="min-w-0">
            <p class="text-sm font-medium text-gray-500 leading-tight">Total Extensions</p>
            <p class="mt-0.5 min-h-[2rem] text-2xl font-bold tabular-nums leading-tight text-gray-900">{{ summary.total_extensions }}</p>
          </div>
          <div class="ml-3 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-teal-100 text-teal-600">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
            </svg>
          </div>
        </div>
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
          <div class="min-w-0">
            <p class="text-sm font-medium text-gray-500 leading-tight">Assigned</p>
            <p class="mt-0.5 min-h-[2rem] text-2xl font-bold tabular-nums leading-tight text-green-600">{{ summary.assigned }}</p>
          </div>
          <div class="ml-3 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green-100 text-green-600">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
          </div>
        </div>
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
          <div class="min-w-0">
            <p class="text-sm font-medium text-gray-500 leading-tight">Unassigned</p>
            <p class="mt-0.5 min-h-[2rem] text-2xl font-bold tabular-nums leading-tight text-orange-600">{{ summary.unassigned }}</p>
          </div>
          <div class="ml-3 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-orange-100 text-orange-600">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
            </svg>
          </div>
        </div>
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
          <div class="min-w-0">
            <p class="text-sm font-medium text-gray-500 leading-tight">Active Status</p>
            <p class="mt-0.5 min-h-[2rem] text-2xl font-bold tabular-nums leading-tight text-blue-600">{{ summary.active_status }}</p>
          </div>
          <div class="ml-3 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-600">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </div>
      </div>

      <div
        v-if="loadError"
        class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
      >
        {{ loadError }}
      </div>

      <div v-if="importResult" class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-700">
        {{ importResult.message }}
        <span v-if="importResult.errors?.length" class="mt-1 block text-xs text-amber-700">
          {{ importResult.errors.join(' ') }}
        </span>
      </div>

      <!-- Quick filters: Status, Landline Number + Apply / Reset -->
      <div class="flex flex-wrap items-end gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3">
        <div>
          <label class="block text-xs font-medium text-gray-600">Status</label>
          <select
            v-model="filters.status"
            class="mt-0.5 min-w-[140px] rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          >
            <option value="">All Statuses</option>
            <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600">Landline Number</label>
          <input
            v-model="filters.landline_number"
            type="text"
            placeholder="Search landline..."
            class="mt-0.5 min-w-[160px] rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
            :disabled="loading"
          />
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="inline-flex items-center rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 focus:ring-2 focus:ring-green-500 disabled:opacity-50"
            :disabled="loading"
            @click="applyFilters"
          >
            <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            Apply
          </button>
          <button
            type="button"
            class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
            :disabled="loading"
            @click="resetFilters"
          >
            Reset
          </button>
        </div>
        <div class="ml-auto flex items-center gap-2">
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
            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
            @click="columnModalVisible = true"
          >
            Customize Columns
            <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Advanced Filters (all filters) -->
      <FiltersBar
        :visible="advancedVisible"
        :filters="filters"
        :filter-options="filterOptions"
        :loading="loading"
        @apply="applyFilters"
        @reset="resetFilters"
      />

      <div class="overflow-hidden rounded-xl border-2 border-black bg-white shadow-sm">
        <ExtensionsTable
          :columns="visibleColumns"
          :data="extensions"
          :sort="sort"
          :order="order"
          :loading="loading"
          :current-page="meta.current_page"
          :per-page="meta.per_page"
          :filter-options="filterOptions"
          :assignable-employees="assignableEmployees"
          @sort="onSort"
          @delete="openDeleteConfirm"
          @update-cell="onUpdateCell"
          @open-history="openHistoryModal"
          @view="openViewModal"
          @edit="openEditModal"
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

    <AddExtensionModal
      :visible="addModalVisible"
      :gateways="filterOptions.gateways"
      :statuses="filterOptions.statuses"
      @close="addModalVisible = false"
      @created="() => { toast('success', 'Extension created successfully.'); loadSummary(); load() }"
    />

    <ViewExtensionModal
      :visible="viewModalVisible"
      :extension-id="viewModalExtensionId"
      :gateways="filterOptions.gateways"
      :statuses="filterOptions.statuses"
      @close="viewModalVisible = false"
      @edit="onViewModalEdit"
    />

    <EditExtensionModal
      :visible="editModalVisible"
      :extension-id="editModalExtensionId"
      :gateways="filterOptions.gateways"
      :statuses="filterOptions.statuses"
      @close="closeEditModal"
      @updated="onEditUpdated"
    />

    <ColumnCustomizerModal
      :visible="columnModalVisible"
      :all-columns="allColumns"
      :visible-columns="visibleColumns"
      @update:visible="columnModalVisible = $event"
      @save="onSaveColumns"
    />

    <!-- Delete confirmation -->
    <Teleport to="body">
      <div
        v-if="extensionToDelete"
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-500/50 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="confirm-delete-title"
        @click.self="closeDeleteConfirm"
      >
        <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
            <h2 id="confirm-delete-title" class="text-lg font-semibold text-gray-900">Confirm Delete</h2>
            <button
              type="button"
              class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
              aria-label="Close"
              @click="closeDeleteConfirm"
            >
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div class="px-6 py-4">
            <p class="text-sm text-gray-600">
              Are you sure you want to delete extension
              <span class="font-medium text-gray-900">{{ extensionToDelete?.extension || extensionToDelete?.id }}</span>
              ? This action cannot be undone.
            </p>
          </div>
          <div class="flex justify-end gap-3 border-t border-gray-200 px-6 py-4">
            <button
              type="button"
              class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
              :disabled="deleting"
              @click="confirmDelete"
            >
              {{ deleting ? 'Deleting...' : 'Delete' }}
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
              @click="closeDeleteConfirm"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- History (audit log) modal -->
    <Teleport to="body">
      <div
        v-if="historyModalExtension"
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-500/50 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="history-modal-title"
        @click.self="closeHistoryModal"
      >
        <div class="w-full max-w-2xl max-h-[85vh] flex flex-col rounded-xl bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
            <h2 id="history-modal-title" class="text-lg font-semibold text-gray-900">
              Extension History – {{ historyModalExtension?.extension ?? historyModalExtension?.id }}
            </h2>
            <button
              type="button"
              class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
              aria-label="Close"
              @click="closeHistoryModal"
            >
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div class="flex-1 overflow-auto px-6 py-4">
            <div v-if="historyLoading" class="flex justify-center py-8">
              <svg class="h-8 w-8 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
            </div>
            <div v-else-if="!historyAuditLog.length" class="py-8 text-center text-sm text-gray-500">
              No history recorded.
            </div>
            <ul v-else class="space-y-4">
              <li
                v-for="entry in historyAuditLog"
                :key="entry.id"
                class="rounded-lg border border-gray-200 bg-gray-50/50 p-4 text-sm"
              >
                <div class="flex flex-wrap items-center gap-2 text-gray-700">
                  <span class="font-medium capitalize">{{ entry.action }}</span>
                  <span class="text-gray-500">by {{ entry.user_name }}</span>
                  <span class="text-gray-400">– {{ entry.created_at ? new Date(entry.created_at).toLocaleString() : '' }}</span>
                </div>
                <div v-if="getEntryChanges(entry).length" class="mt-3 space-y-1.5">
                  <div
                    v-for="(c, ci) in getEntryChanges(entry)"
                    :key="ci"
                    class="flex flex-wrap items-center gap-1.5 text-sm"
                  >
                    <span class="font-medium text-gray-700">{{ c.label }}:</span>
                    <span class="text-red-500 line-through break-all">{{ c.oldVal ?? '(empty)' }}</span>
                    <span class="text-gray-400">&rarr;</span>
                    <span class="text-green-600 break-all">{{ c.newVal ?? '(empty)' }}</span>
                  </div>
                </div>
              </li>
            </ul>
          </div>
          <div class="border-t border-gray-200 px-6 py-4">
            <button
              type="button"
              class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
              @click="closeHistoryModal"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="4000" @dismiss="showToast = false" />
  </div>
</template>
