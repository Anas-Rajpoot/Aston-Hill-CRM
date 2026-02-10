<script setup>
/**
 * Cisco Extensions listing – Advanced Filters, sortable table, Import/Export, Add Extension (modal), Customize Columns, Delete.
 */
import { ref, computed, onMounted } from 'vue'
import extensionsApi from '@/services/extensionsApi'
import { useAuthStore } from '@/stores/auth'
import FiltersBar from '@/components/extensions/AdvancedFilters.vue'
import ColumnCustomizerModal from '@/components/lead-submissions/ColumnCustomizerModal.vue'
import AddExtensionModal from '@/components/extensions/AddExtensionModal.vue'
import EditExtensionModal from '@/components/extensions/EditExtensionModal.vue'
import ExtensionsTable from '@/components/extensions/ExtensionsTable.vue'
import Pagination from '@/components/Pagination.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'

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
const meta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 })
const allColumns = ref([])
const visibleColumns = ref([
  'id', 'extension', 'landline_number', 'gateway', 'username', 'password',
  'status', 'team_leader', 'manager', 'usage', 'assigned_to_name', 'updated_at',
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
const assignableEmployees = ref([])
const historyModalExtension = ref(null)
const historyAuditLog = ref([])
const historyLoading = ref(false)

const filters = ref({
  extension: '',
  landline_number: '',
  gateway: '',
  assigned_to_q: '',
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
  if (f.assigned_to_q) p.assigned_to_q = f.assigned_to_q
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
    meta.value = data.meta ?? { current_page: 1, last_page: 1, per_page: 15, total: 0 }
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
  filters.value.assigned_to_q = ''
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
    closeDeleteConfirm()
    load()
  } catch {
    //
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
  closeEditModal()
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

onMounted(() => {
  loadFilters()
  loadColumns()
  loadAssignableEmployees()
  load()
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
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <svg v-else class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
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

      <FiltersBar
        :visible="advancedVisible"
        :filters="filters"
        :filter-options="filterOptions"
        :loading="loading"
        @apply="applyFilters"
        @reset="resetFilters"
      />

      <div class="flex justify-end gap-2">
        <button
          type="button"
          class="inline-flex items-center rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
          @click="advancedVisible = !advancedVisible"
        >
          Advanced Filters
          <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
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
          @edit="openEditModal"
        />
        <div
          class="flex flex-wrap items-center gap-4 border-t border-gray-200 bg-white px-4 py-3"
          :class="meta.last_page > 1 ? 'justify-between' : 'justify-start'"
        >
          <p class="text-sm text-gray-600">
            Showing {{ meta.total ? (meta.current_page - 1) * meta.per_page + 1 : 0 }} to {{ Math.min(meta.current_page * meta.per_page, meta.total) }} of {{ meta.total }} results
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

    <AddExtensionModal
      :visible="addModalVisible"
      :gateways="filterOptions.gateways"
      :statuses="filterOptions.statuses"
      @close="addModalVisible = false"
      @created="load()"
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
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-500/50 p-4"
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
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-500/50 p-4"
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
                <div v-if="entry.old_values && Object.keys(entry.old_values).length" class="mt-2 text-gray-600">
                  <span class="font-medium">Old:</span>
                  <pre class="mt-0.5 overflow-x-auto rounded bg-white p-2 text-xs">{{ JSON.stringify(entry.old_values, null, 2) }}</pre>
                </div>
                <div v-if="entry.new_values && Object.keys(entry.new_values).length" class="mt-2 text-gray-600">
                  <span class="font-medium">New:</span>
                  <pre class="mt-0.5 overflow-x-auto rounded bg-white p-2 text-xs">{{ JSON.stringify(entry.new_values, null, 2) }}</pre>
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
  </div>
</template>
