<script setup>
/**
 * Cisco Extensions listing – Advanced Filters, sortable table, Import/Export, Add Extension (modal), Customize Columns, Delete.
 */
import { ref, computed, onMounted, nextTick, defineAsyncComponent } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import extensionsApi from '@/services/extensionsApi'
import { useAuthStore } from '@/stores/auth'
import api from '@/lib/axios'
import Toast from '@/components/Toast.vue'
import DeleteOtpModal from '@/components/DeleteOtpModal.vue'
import { useProgressiveHydration } from '@/composables/useProgressiveHydration'
import { useDeferredQuery } from '@/composables/useDeferredQuery'
import { canModuleAction } from '@/lib/accessControl'

const FiltersBar = defineAsyncComponent(() => import('@/components/extensions/AdvancedFilters.vue'))
const HorizontalScrollToolbar = defineAsyncComponent(() => import('@/components/common/HorizontalScrollToolbar.vue'))
const ColumnCustomizerModal = defineAsyncComponent(() => import('@/components/lead-submissions/ColumnCustomizerModal.vue'))
const AddExtensionModal = defineAsyncComponent(() => import('@/components/extensions/AddExtensionModal.vue'))
const EditExtensionModal = defineAsyncComponent(() => import('@/components/extensions/EditExtensionModal.vue'))
const ViewExtensionModal = defineAsyncComponent(() => import('@/components/extensions/ViewExtensionModal.vue'))
const ExtensionsTable = defineAsyncComponent(() => import('@/components/extensions/ExtensionsTable.vue'))
const RecordHistoryModal = defineAsyncComponent(() => import('@/components/RecordHistoryModal.vue'))

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const permissions = computed(() => auth.user?.permissions ?? [])
const isSuperAdmin = computed(() => {
  const r = auth.user?.roles ?? []
  return Array.isArray(r) && r.some((x) => (typeof x === 'string' ? x === 'superadmin' : x?.name === 'superadmin'))
})
const canCreate = computed(() => canModuleAction(auth.user, 'extensions', 'create'))
const canImport = computed(() => canModuleAction(auth.user, 'extensions', 'import'))
const canExport = computed(() => canModuleAction(auth.user, 'extensions', 'export'))
const canViewAction = computed(() => canModuleAction(auth.user, 'extensions', 'view'))
const canEditAction = computed(() => canModuleAction(auth.user, 'extensions', 'edit'))
const canDeleteAction = computed(() => canModuleAction(auth.user, 'extensions', 'delete'))
const canSample = computed(() => canImport.value)
const canBulkStatus = computed(() => canEditAction.value)
const canBulkDelete = computed(() => canDeleteAction.value)
const summaryHydration = useProgressiveHydration({ strategy: 'visible-or-idle', idleTimeout: 900 })
const advancedFiltersHydration = useProgressiveHydration({ strategy: 'visible-or-idle', idleTimeout: 1200 })

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
  'status', 'manager', 'team_leader', 'assigned_to_name', 'usage', 'comment', 'updated_at',
])
const sort = ref('extension')
const order = ref('asc')
const advancedVisible = ref(false)
const columnModalVisible = ref(false)
const exportLoading = ref(false)
const importLoading = ref(false)
const importFileInput = ref(null)
const importResult = ref(null)
const selectedExtensionIds = ref([])
const bulkStatus = ref('')
const bulkActionLoading = ref(false)
const extensionToDelete = ref(null)
const deleting = ref(false)
const addModalVisible = ref(false)
const editModalVisible = ref(false)
const editModalExtensionId = ref(null)
const viewModalVisible = ref(false)
const viewModalExtensionId = ref(null)
const assignableEmployees = ref([])
const managerOptions = ref([])
const teamLeaderOptions = ref([])
const historyModalExtension = ref(null)
const summary = ref({
  total_extensions: 0,
  assigned: 0,
  unassigned: 0,
  active_status: 0,
})
const selectedCount = computed(() => selectedExtensionIds.value.length)

const EXTENSION_HISTORY_FIELD_LABELS = {
  extension: 'Extension',
  landline_number: 'Landline Number',
  gateway: 'Gateway',
  username: 'Username',
  password: 'Password',
  status: 'Status',
  manager: 'Manager',
  manager_id: 'Manager',
  team_leader: 'Team Leader',
  team_leader_id: 'Team Leader',
  assigned_to_name: 'Assigned To',
  assigned_to_id: 'Assigned To',
  usage: 'Usage',
  comment: 'Comment',
}

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
  manager_id: '',
  team_leader_id: '',
  status: '',
  usage: '',
  created_from: '',
  created_to: '',
})

const COLUMN_ORDER = [
  'id',
  'extension',
  'landline_number',
  'gateway',
  'username',
  'password',
  'status',
  'manager',
  'team_leader',
  'assigned_to_name',
  'usage',
  'comment',
  'updated_at',
]

function enforceColumnOrder(cols) {
  const set = new Set(Array.isArray(cols) ? cols : [])
  ;['manager', 'team_leader', 'assigned_to_name'].forEach((c) => set.add(c))
  const ordered = COLUMN_ORDER.filter((c) => set.has(c))
  const extra = [...set].filter((c) => !COLUMN_ORDER.includes(c))
  return [...ordered, ...extra]
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
  if (f.extension) p.extension = f.extension
  if (f.landline_number) p.landline_number = f.landline_number
  if (f.gateway) p.gateway = f.gateway
  if (f.username) p.username = f.username
  if (f.assigned_to_q) p.assigned_to_q = f.assigned_to_q
  if (f.manager_id !== '' && f.manager_id != null) p.manager_id = Number(f.manager_id)
  if (f.team_leader_id !== '' && f.team_leader_id != null) p.team_leader_id = Number(f.team_leader_id)
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
    const visibleIds = new Set((extensions.value ?? []).map((row) => Number(row.id)))
    selectedExtensionIds.value = selectedExtensionIds.value.filter((id) => visibleIds.has(Number(id)))
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
    visibleColumns.value = enforceColumnOrder(data.visible_columns ?? visibleColumns.value)
  } catch {
    //
  }
}

const deferredSecondaryBootstrap = useDeferredQuery(async () => {
  await Promise.all([loadFilters(), loadColumns(), loadAssignableEmployees()])
})

function hydrateSecondaryData() {
  deferredSecondaryBootstrap.run().catch(() => {})
}

function openAdvancedFilters() {
  advancedVisible.value = !advancedVisible.value
  if (advancedVisible.value) {
    advancedFiltersHydration.hydrateNow()
    hydrateSecondaryData()
  }
}

function openColumnCustomizer() {
  hydrateSecondaryData()
  columnModalVisible.value = true
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
  filters.value.manager_id = ''
  filters.value.team_leader_id = ''
  filters.value.status = ''
  filters.value.usage = ''
  filters.value.created_from = ''
  filters.value.created_to = ''
  meta.value.current_page = 1
  load()
}

function clearFiltersOnly() {
  filters.value.extension = ''
  filters.value.landline_number = ''
  filters.value.gateway = ''
  filters.value.username = ''
  filters.value.assigned_to_q = ''
  filters.value.manager_id = ''
  filters.value.team_leader_id = ''
  filters.value.status = ''
  filters.value.usage = ''
  filters.value.created_from = ''
  filters.value.created_to = ''
}

function onAddCreated() {
  // Ensure the freshly created row is visible even if stale filters were applied.
  clearFiltersOnly()
  meta.value.current_page = 1
  toast('success', 'Extension created successfully.')
  loadSummary()
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
    const normalized = enforceColumnOrder(cols)
    await extensionsApi.saveColumns(normalized)
    visibleColumns.value = normalized
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

const SAMPLE_COLUMNS = [
  'id', 'extension', 'landline_number', 'gateway', 'username', 'password',
  'status', 'team_leader', 'manager', 'usage', 'assigned_to_name', 'comment', 'updated_at',
]

function sampleLabel(col) {
  const labels = {
    id: 'ID',
    extension: 'Extension',
    landline_number: 'Landline Number',
    gateway: 'Gateway',
    username: 'Username',
    password: 'Password',
    status: 'Status',
    team_leader: 'Team Leader',
    manager: 'Manager',
    usage: 'Usage',
    assigned_to_name: 'Assigned To',
    comment: 'Comment',
    updated_at: 'Updated At',
    other_1: 'Other 1',
    other_2: 'Other 2',
  }
  return labels[col] ?? col.replace(/_/g, ' ')
}

function downloadTemplateCsv() {
  if (!canSample.value) return
  const tableCols = [...visibleColumns.value]
  if (!tableCols.includes('extension')) tableCols.unshift('extension')
  if (!tableCols.includes('landline_number')) tableCols.push('landline_number')

  const extraCols = ['other_1', 'other_2']
  const exportCols = [...tableCols, ...extraCols]
  const headers = exportCols.map(sampleLabel)
  const seed = String(Date.now()).slice(-4)
  const rows = [
    {
      id: '',
      extension: `EXT-${seed}-01`,
      landline_number: `97150${seed}001`,
      gateway: 'Etisalat',
      username: `ext_user_${seed}_01`,
      password: 'Pass@123',
      status: 'active',
      team_leader: '',
      manager: '',
      usage: 'assigned',
      assigned_to_name: '',
      comment: 'Sample extension row 1',
      updated_at: '',
      other_1: 'Sample extra value A',
      other_2: 'Sample extra value B',
    },
    {
      id: '',
      extension: `EXT-${seed}-02`,
      landline_number: `97150${seed}002`,
      gateway: 'Du',
      username: `ext_user_${seed}_02`,
      password: 'Pass@456',
      status: 'active',
      team_leader: '',
      manager: '',
      usage: 'unassigned',
      assigned_to_name: '',
      comment: 'Sample extension row 2',
      updated_at: '',
      other_1: 'Sample extra value C',
      other_2: 'Sample extra value D',
    },
    {
      id: '',
      extension: `EXT-${seed}-03`,
      landline_number: `97150${seed}003`,
      gateway: 'Etisalat',
      username: `ext_user_${seed}_03`,
      password: 'Pass@789',
      status: 'active',
      team_leader: '',
      manager: '',
      usage: 'assigned',
      assigned_to_name: '',
      comment: 'Sample extension row 3',
      updated_at: '',
      other_1: 'Sample extra value E',
      other_2: 'Sample extra value F',
    },
  ]
  const csvRows = [
    headers.map(escapeCsv).join(','),
    ...rows.map((row) => exportCols.map((col) => escapeCsv(row[col] ?? '')).join(',')),
  ]
  const blob = new Blob([csvRows.join('\r\n')], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'cisco-extensions-template.csv'
  a.click()
  URL.revokeObjectURL(url)
}

function triggerImport() {
  if (!canImport.value) return
  importFileInput.value?.click()
}

async function openAddModal() {
  if (!canCreate.value) return
  hydrateSecondaryData()
  await deferredSecondaryBootstrap.run()
  addModalVisible.value = true
}

async function onImportFileChange(event) {
  if (!canImport.value) return
  const file = event.target?.files?.[0]
  if (!file) return
  importLoading.value = true
  importResult.value = null
  try {
    const { data } = await extensionsApi.bulkImport(file)
    importResult.value = data ?? { message: 'Import complete.' }
    toast('success', importResult.value.message || 'CSV imported successfully.')
    await loadSummary()
    await load()
  } catch (e) {
    const message = e?.response?.data?.message || 'Failed to import CSV.'
    const errors = Array.isArray(e?.response?.data?.errors) ? e.response.data.errors : []
    importResult.value = { message, errors }
    toast('error', message)
  } finally {
    importLoading.value = false
    if (event?.target) event.target.value = ''
  }
}

async function onExport() {
  if (!canExport.value) return
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

function onToggleSelectRow({ id, checked }) {
  const nId = Number(id)
  const set = new Set(selectedExtensionIds.value.map((x) => Number(x)))
  if (checked) set.add(nId)
  else set.delete(nId)
  selectedExtensionIds.value = Array.from(set)
}

function onToggleSelectAll({ checked }) {
  const visibleIds = (extensions.value ?? []).map((row) => Number(row.id))
  if (checked) {
    const set = new Set(selectedExtensionIds.value.map((x) => Number(x)))
    for (const id of visibleIds) set.add(id)
    selectedExtensionIds.value = Array.from(set)
    return
  }
  const visibleSet = new Set(visibleIds)
  selectedExtensionIds.value = selectedExtensionIds.value.filter((id) => !visibleSet.has(Number(id)))
}

async function onBulkStatusUpdate() {
  if (!canBulkStatus.value || bulkActionLoading.value) return
  if (!selectedCount.value) {
    toast('error', 'Select at least one extension.')
    return
  }
  if (!bulkStatus.value) {
    toast('error', 'Select a status first.')
    return
  }

  bulkActionLoading.value = true
  try {
    const { data } = await extensionsApi.bulkStatusUpdate(selectedExtensionIds.value, bulkStatus.value)
    toast('success', data?.message || 'Bulk status update completed.')
    selectedExtensionIds.value = []
    bulkStatus.value = ''
    await loadSummary()
    await load()
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Bulk status update failed.')
  } finally {
    bulkActionLoading.value = false
  }
}

async function onBulkDelete() {
  if (!canBulkDelete.value || bulkActionLoading.value) return
  if (!selectedCount.value) {
    toast('error', 'Select at least one extension.')
    return
  }
  if (!window.confirm(`Delete ${selectedCount.value} selected extension(s)?`)) return

  bulkActionLoading.value = true
  try {
    const { data } = await extensionsApi.bulkDelete(selectedExtensionIds.value)
    toast('success', data?.message || 'Bulk delete completed.')
    selectedExtensionIds.value = []
    await loadSummary()
    await load(1)
  } catch (e) {
    toast('error', e?.response?.data?.message || 'Bulk delete failed.')
  } finally {
    bulkActionLoading.value = false
  }
}

function openDeleteConfirm(row) {
  if (!canDeleteAction.value) return
  extensionToDelete.value = row
}

function closeDeleteConfirm() {
  extensionToDelete.value = null
}

async function confirmDelete() {
  if (!canDeleteAction.value) {
    closeDeleteConfirm()
    return
  }
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
    managerOptions.value = data.manager_options ?? []
    teamLeaderOptions.value = data.team_leader_options ?? []
  } catch {
    assignableEmployees.value = []
    managerOptions.value = []
    teamLeaderOptions.value = []
  }
}

async function onUpdateCell(rowId, field, value) {
  try {
    const payload = {}
    if (field === 'assigned_to_name') {
      payload.assigned_to = value === '' || value == null ? null : Number(value)
    } else if (field === 'manager') {
      payload.manager_id = value === '' || value == null ? null : Number(value)
    } else if (field === 'team_leader') {
      payload.team_leader_id = value === '' || value == null ? null : Number(value)
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
    toast('success', field === 'password' ? 'Password updated successfully.' : 'Extension updated successfully.')
  } catch {
    toast('error', 'Failed to update extension.')
    load()
  }
}

function openViewModal(row) {
  if (!canViewAction.value) return
  if (row?.id) {
    hydrateSecondaryData()
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
  if (!canEditAction.value) return
  if (row?.id) {
    hydrateSecondaryData()
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
  if (!canViewAction.value) return
  historyModalExtension.value = row
}

function closeHistoryModal() {
  historyModalExtension.value = null
}

async function fetchHistoryRows(id) {
  const { data } = await extensionsApi.getAuditLog(id)
  const audits = data?.data ?? []
  const skipKeys = new Set(['updated_at', 'created_at', 'deleted_at', 'id'])
  const rows = []

  for (const entry of audits) {
    const oldValues = entry?.old_values || {}
    const newValues = entry?.new_values || {}
    const allKeys = new Set([...Object.keys(oldValues), ...Object.keys(newValues)])
    const changedAt = entry?.created_at ?? null
    const changedByName = entry?.user_name ?? 'System'

    for (const key of allKeys) {
      if (skipKeys.has(key)) continue
      const oldValue = oldValues[key]
      const newValue = newValues[key]
      if (String(oldValue ?? '') === String(newValue ?? '')) continue

      rows.push({
        id: `${entry.id}-${key}`,
        field_name: key,
        old_value: oldValue,
        new_value: newValue,
        changed_at: changedAt,
        changed_by_name: changedByName,
      })
    }
  }
  return { data: rows }
}

onMounted(async () => {
  await loadTablePreference()
  loadSummary()
  await load()
  hydrateSecondaryData()
  const editId = route.query.edit
  if (editId) {
    advancedFiltersHydration.hydrateNow()
    await deferredSecondaryBootstrap.run()
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
      <!-- Summary cards -->
      <div ref="summaryHydration.targetRef" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <template v-if="summaryHydration.isHydrated">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
          <div class="min-w-0">
            <p class="text-sm font-medium text-gray-500 leading-tight">Total Extensions</p>
            <p class="mt-0.5 min-h-[2rem] text-2xl font-bold tabular-nums leading-tight text-gray-900">{{ summary.total_extensions }}</p>
          </div>
          <div class="ml-3 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-primary-light text-brand-primary">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
            </svg>
          </div>
        </div>
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
          <div class="min-w-0">
            <p class="text-sm font-medium text-gray-500 leading-tight">Assigned</p>
            <p class="mt-0.5 min-h-[2rem] text-2xl font-bold tabular-nums leading-tight text-brand-primary">{{ summary.assigned }}</p>
          </div>
          <div class="ml-3 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-primary-light text-brand-primary">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
          </div>
        </div>
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
          <div class="min-w-0">
            <p class="text-sm font-medium text-gray-500 leading-tight">Unassigned</p>
            <p class="mt-0.5 min-h-[2rem] text-2xl font-bold tabular-nums leading-tight text-amber-600">{{ summary.unassigned }}</p>
          </div>
          <div class="ml-3 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-600">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
            </svg>
          </div>
        </div>
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
          <div class="min-w-0">
            <p class="text-sm font-medium text-gray-500 leading-tight">Active Status</p>
            <p class="mt-0.5 min-h-[2rem] text-2xl font-bold tabular-nums leading-tight text-brand-primary">{{ summary.active_status }}</p>
          </div>
          <div class="ml-3 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-primary-light text-brand-primary">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </div>
        </template>
        <template v-else>
          <div v-for="n in 4" :key="`extensions-summary-skeleton-${n}`" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="h-3 w-24 animate-pulse rounded bg-gray-200" />
            <div class="mt-2 h-8 w-12 animate-pulse rounded bg-gray-100" />
          </div>
        </template>
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

      <!-- Single-row toolbar: filters + actions (with left/right arrows) -->
      <div class="rounded-lg border border-gray-200 bg-white px-2 py-2">
        <HorizontalScrollToolbar>
          <input
            ref="importFileInput"
            type="file"
            accept=".csv,.txt"
            class="hidden"
            @change="onImportFileChange"
          />

          <div class="w-[180px] shrink-0">
            <label class="block text-xs font-medium text-gray-600">Status</label>
            <select
              v-model="filters.status"
              class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
              :disabled="loading"
            >
              <option value="">All Statuses</option>
              <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
          </div>

          <div class="w-[220px] shrink-0">
            <label class="block text-xs font-medium text-gray-600">Landline Number</label>
            <input
              v-model="filters.landline_number"
              type="text"
              placeholder="Search landline..."
              class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
              :disabled="loading"
            />
          </div>

          <div class="shrink-0 flex items-center gap-2 pt-5">
            <button
              type="button"
              class="inline-flex items-center rounded bg-brand-primary px-4 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover focus:ring-2 focus:ring-brand-primary disabled:opacity-50"
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

          <div class="shrink-0 flex items-center gap-2 pt-5">
            <span class="rounded bg-gray-100 px-2 py-1 text-xs text-gray-700">Selected: {{ selectedCount }}</span>
            <select
              v-if="canBulkStatus"
              v-model="bulkStatus"
              class="rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
              :disabled="bulkActionLoading"
            >
              <option value="">Change Status</option>
              <option v-for="s in filterOptions.statuses" :key="`bulk-${s.value}`" :value="s.value">{{ s.label }}</option>
            </select>
            <button
              v-if="canBulkStatus"
              type="button"
              class="inline-flex items-center rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
              :disabled="bulkActionLoading"
              @click="onBulkStatusUpdate"
            >
              {{ bulkActionLoading ? 'Processing...' : 'Apply Status' }}
            </button>
            <button
              v-if="canBulkDelete"
              type="button"
              class="inline-flex items-center rounded border border-red-300 bg-white px-3 py-2 text-sm text-red-700 hover:bg-red-50 disabled:opacity-50"
              :disabled="bulkActionLoading"
              @click="onBulkDelete"
            >
              Bulk Delete
            </button>
          </div>

          <button
            v-if="canSample"
            type="button"
            class="shrink-0 inline-flex items-center rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 mt-5"
            @click="downloadTemplateCsv"
          >
            <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
            </svg>
            Template
          </button>

          <button
            v-if="canImport"
            type="button"
            class="shrink-0 inline-flex items-center rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50 mt-5"
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

          <button
            v-if="canExport"
            type="button"
            class="shrink-0 inline-flex items-center rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50 mt-5"
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
            class="shrink-0 inline-flex items-center rounded bg-brand-primary px-3 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover mt-5"
            @click="openAddModal"
          >
            <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add New Extension
          </button>

          <button
            type="button"
            class="shrink-0 inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 mt-5"
            @click="openAdvancedFilters"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
            Advanced Filters
          </button>

          <button
            type="button"
            class="shrink-0 inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 mt-5"
            @click="openColumnCustomizer"
          >
            Customize Columns
            <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
        </HorizontalScrollToolbar>
      </div>

      <!-- Advanced Filters (all filters) -->
      <div ref="advancedFiltersHydration.targetRef">
        <FiltersBar
          v-if="advancedFiltersHydration.isHydrated"
          :visible="advancedVisible"
          :filters="filters"
          :filter-options="filterOptions"
          :manager-options="managerOptions"
          :team-leader-options="teamLeaderOptions"
          :loading="loading"
          @apply="applyFilters"
          @reset="resetFilters"
        />
        <div v-else-if="advancedVisible" class="rounded-lg border border-gray-200 bg-white p-4">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div v-for="n in 6" :key="`extensions-advanced-skeleton-${n}`" class="space-y-2">
              <div class="h-3 w-20 animate-pulse rounded bg-gray-200" />
              <div class="h-9 w-full animate-pulse rounded bg-gray-100" />
            </div>
          </div>
        </div>
      </div>

      <div class="overflow-x-auto rounded-xl border-2 border-black bg-white shadow-sm">
        <ExtensionsTable
          :columns="visibleColumns"
          :data="extensions"
          :selected-ids="selectedExtensionIds"
          :sort="sort"
          :order="order"
          :loading="loading"
          :current-page="meta.current_page"
          :per-page="meta.per_page"
          :filter-options="filterOptions"
          :assignable-employees="assignableEmployees"
          :manager-options="managerOptions"
          :team-leader-options="teamLeaderOptions"
          @sort="onSort"
          @delete="openDeleteConfirm"
          @update-cell="onUpdateCell"
          @open-history="openHistoryModal"
          @view="openViewModal"
          @edit="openEditModal"
          @toggle-select-row="onToggleSelectRow"
          @toggle-select-all="onToggleSelectAll"
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

    <AddExtensionModal
      :visible="addModalVisible"
      :gateways="filterOptions.gateways"
      :statuses="filterOptions.statuses"
      @close="addModalVisible = false"
      @created="onAddCreated"
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

    <!-- Delete confirmation (OTP) -->
    <DeleteOtpModal
      :visible="!!extensionToDelete"
      title="Delete Extension"
      :item-label="extensionToDelete ? `Extension ${extensionToDelete.extension || extensionToDelete.id}` : 'this extension'"
      :loading="deleting"
      @confirm="confirmDelete"
      @close="closeDeleteConfirm"
    />

    <RecordHistoryModal
      :visible="Boolean(historyModalExtension)"
      :record-id="historyModalExtension?.id"
      :record-label="historyModalExtension ? `Extension ${historyModalExtension.extension ?? historyModalExtension.id}` : ''"
      module-name="Cisco Extensions"
      :fetch-fn="fetchHistoryRows"
      :field-labels="EXTENSION_HISTORY_FIELD_LABELS"
      @close="closeHistoryModal"
    />

    <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="4000" @dismiss="showToast = false" />
  </div>
</template>
