<script setup>
/**
 * Verifiers Detail – manage verifier directory for DSP Tracker.
 * Add (manual or CSV import), export CSV, datatable with double-click edit, sortable columns, delete with permission.
 */
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useTablePageSize } from '@/composables/useTablePageSize'
import api from '@/lib/axios'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import Toast from '@/components/Toast.vue'
import { toDdMonYyyy } from '@/lib/dateFormat'

const auth = useAuthStore()
const list = ref([])
const meta = ref({ current_page: 1, last_page: 1, per_page: 10, total: 0 })
const loading = ref(false)
const searchQ = ref('')
const sortBy = ref('id')
const sortOrder = ref('desc')
const { perPage, perPageOptions, perPageReady, setPerPage } = useTablePageSize('verifiers')
const successMessage = ref('')
const errorMessage = ref('')

const canAdd = computed(() => {
  const roles = auth.user?.roles ?? []
  const perms = auth.user?.permissions ?? []
  if (Array.isArray(roles) && roles.includes('superadmin')) return true
  return Array.isArray(perms) && (perms.includes('verifiers.add') || perms.includes('verifiers.create'))
})

const canDelete = computed(() => {
  const roles = auth.user?.roles ?? []
  const perms = auth.user?.permissions ?? []
  if (Array.isArray(roles) && roles.includes('superadmin')) return true
  return Array.isArray(perms) && perms.includes('verifiers.delete')
})

// Inline edit (double-click)
const editingCell = ref(null) // { id, field }
const editValue = ref('')
const savingCell = ref(false)
const EDITABLE_FIELDS = ['verifier_name', 'verifier_number', 'remarks']

function canEditCell(row, field) {
  return canAdd.value && row?.id && EDITABLE_FIELDS.includes(field)
}

function startEdit(row, field) {
  if (!canEditCell(row, field)) return
  editingCell.value = { id: row.id, field }
  editValue.value = row[field] ?? ''
}

function cancelEdit() {
  editingCell.value = null
  editValue.value = ''
}

async function saveEdit() {
  const { id, field } = editingCell.value || {}
  if (!id || !field) return
  const row = list.value.find((r) => r.id === id)
  if (!row) return
  savingCell.value = true
  try {
    const payload = {
      verifier_name: field === 'verifier_name' ? (editValue.value?.trim() || '') : row.verifier_name,
      verifier_number: field === 'verifier_number' ? (editValue.value?.trim() || null) : (row.verifier_number ?? null),
      remarks: field === 'remarks' ? (editValue.value?.trim() || null) : (row.remarks ?? null),
    }
    await api.put(`/verifiers/${id}`, payload)
    row.verifier_name = payload.verifier_name
    row.verifier_number = payload.verifier_number
    row.remarks = payload.remarks
    cancelEdit()
    successMessage.value = 'Verifier updated.'
    setTimeout(() => { successMessage.value = '' }, 3000)
  } catch (e) {
    errorMessage.value = e?.response?.data?.message || 'Failed to update verifier.'
  } finally {
    savingCell.value = false
  }
}

function isEditing(id, field) {
  const e = editingCell.value
  return e && e.id === id && e.field === field
}

// Add Verifier modal
const showAddModal = ref(false)
const addForm = ref({ verifier_name: '', verifier_number: '', remarks: '' })
const addSaving = ref(false)
const addError = ref('')

function openAddModal() {
  resetAddForm()
  showAddModal.value = true
}

// Toast state for add verifier
const showToast = ref(false)
const toastType = ref('success')
const toastMessage = ref('')
let addSuccessCloseTimer = null

function closeAddModal() {
  if (addSaving.value) return
  if (addSuccessCloseTimer) {
    clearTimeout(addSuccessCloseTimer)
    addSuccessCloseTimer = null
  }
  showAddModal.value = false
  resetAddForm()
}

function resetAddForm() {
  addForm.value = { verifier_name: '', verifier_number: '', remarks: '' }
  addError.value = ''
}

function dismissToast() {
  showToast.value = false
}

async function submitAdd() {
  const name = (addForm.value.verifier_name || '').trim()
  const number = (addForm.value.verifier_number || '').trim()
  if (!name) {
    addError.value = 'Verifier name is required.'
    return
  }
  if (!number) {
    addError.value = 'Verifier number is required.'
    return
  }
  addSaving.value = true
  addError.value = ''
  try {
    const res = await api.post('/verifiers', {
      verifier_name: name,
      verifier_number: number || null,
      remarks: (addForm.value.remarks || '').trim() || null,
    })
    // Success (201 or success: true)
    const ok = res?.status === 201 || res?.data?.success === true || res?.data?.message != null
    if (ok) {
      toastType.value = 'success'
      toastMessage.value = 'Verifier added successfully!'
      showToast.value = true
      await load()
      addSuccessCloseTimer = setTimeout(() => {
        addSuccessCloseTimer = null
        showAddModal.value = false
        resetAddForm()
        showToast.value = false
      }, 3000)
    } else {
      toastType.value = 'error'
      toastMessage.value = res?.data?.message || 'Failed to add verifier.'
      showToast.value = true
    }
  } catch (e) {
    const msg = e?.response?.data?.message || e?.response?.data?.errors ? Object.values(e.response.data.errors).flat().join(' ') : 'Failed to add verifier.'
    addError.value = msg
    toastType.value = 'error'
    toastMessage.value = msg
    showToast.value = true
  } finally {
    addSaving.value = false
  }
}

// CSV Import
const csvInputRef = ref(null)
const importError = ref('')
const importSuccess = ref('')

function triggerImport() {
  importError.value = ''
  importSuccess.value = ''
  csvInputRef.value?.click()
}

function parseCsvLine(line) {
  const out = []
  let cur = ''
  let inQuotes = false
  for (let i = 0; i < line.length; i++) {
    const c = line[i]
    if (c === '"') {
      inQuotes = !inQuotes
    } else if ((c === ',' && !inQuotes) || c === '\t') {
      out.push(cur.trim())
      cur = ''
    } else {
      cur += c
    }
  }
  out.push(cur.trim())
  return out
}

async function onCsvFileChange(event) {
  const file = event.target?.files?.[0]
  event.target.value = ''
  if (!file) return
  importError.value = ''
  importSuccess.value = ''
  try {
    const text = await file.text()
    const lines = text.split(/\r?\n/).filter((l) => l.trim())
    if (lines.length < 2) {
      importError.value = 'CSV must have a header row and at least one data row.'
      return
    }
    const headerRow = parseCsvLine(lines[0]).map((h) => h.toLowerCase().replace(/\s+/g, '_'))
    const nameIdx = headerRow.findIndex((h) => h === 'verifier_name' || h === 'verifier name' || h === 'verifiername')
    const numberIdx = headerRow.findIndex((h) => h === 'verifier_number' || h === 'verifier number' || h === 'verifiernumber' || h === 'number')
    const remarksIdx = headerRow.findIndex((h) => h === 'remarks')
    const noIdx = headerRow.findIndex((h) => h === 'no.' || h === 'no' || h === 'no')
    const rows = []
    for (let i = 1; i < lines.length; i++) {
      const cells = parseCsvLine(lines[i])
      const verifier_name = nameIdx >= 0 ? (cells[nameIdx] ?? '').trim() : (cells[1] ?? '').trim()
      const verifier_number = numberIdx >= 0 ? (cells[numberIdx] ?? '').trim() : (cells[2] ?? '').trim()
      const remarks = remarksIdx >= 0 ? (cells[remarksIdx] ?? '').trim() : (cells[3] ?? '').trim()
      if (verifier_name) {
        rows.push({ verifier_name, verifier_number: verifier_number || null, remarks: remarks || null })
      }
    }
    if (rows.length === 0) {
      importError.value = 'No valid rows (Verifier Name required).'
      return
    }
    const { data } = await api.post('/verifiers/import-csv', { rows })
    importSuccess.value = data?.message || `${rows.length} verifier(s) imported.`
    await load()
    setTimeout(() => { importSuccess.value = '' }, 4000)
  } catch (e) {
    importError.value = e?.response?.data?.message || 'Import failed.'
  }
}

// Export CSV (fetch with credentials so session is sent)
async function exportCsv() {
  const params = new URLSearchParams()
  if (searchQ.value.trim()) params.set('q', searchQ.value.trim())
  try {
    const { data, headers } = await api.get('/verifiers/export-csv', {
      params: Object.fromEntries(params),
      responseType: 'blob',
    })
    const disposition = headers['content-disposition']
    const filename = disposition?.match(/filename="?([^";]+)"?/)?.[1] || 'verifiers.csv'
    const url = URL.createObjectURL(data)
    const a = document.createElement('a')
    a.href = url
    a.download = filename
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    errorMessage.value = e?.response?.data?.message || 'Export failed.'
  }
}

// Sort
function setSort(col) {
  if (sortBy.value === col) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortBy.value = col
    sortOrder.value = 'asc'
  }
  load()
}

function sortIcon(col) {
  if (sortBy.value !== col) return '↕'
  return sortOrder.value === 'asc' ? '↑' : '↓'
}

// Load & pagination
async function load(page = null) {
  loading.value = true
  try {
    const params = {
      page: page ?? meta.value.current_page,
      per_page: perPage.value,
      sort: sortBy.value,
      order: sortOrder.value,
    }
    if (searchQ.value.trim()) params.q = searchQ.value.trim()
    const { data: res } = await api.get('/verifiers', { params })
    list.value = res?.data ?? []
    meta.value = res?.meta ?? { current_page: 1, last_page: 1, per_page: 10, total: 0 }
  } catch (e) {
    errorMessage.value = e?.response?.data?.message || 'Failed to load verifiers.'
    list.value = []
  } finally {
    loading.value = false
  }
}

function goPage(p) {
  if (p < 1 || p > meta.value.last_page) return
  load(p)
}

// Delete verifier: confirmation modal and toast feedback
const showDeleteModal = ref(false)
const verifierToDelete = ref(null)
const deleteInProgress = ref(false)

function openDeleteModal(row) {
  if (!canDelete.value) return
  verifierToDelete.value = row
  showDeleteModal.value = true
}

function closeDeleteModal() {
  if (deleteInProgress.value) return
  showDeleteModal.value = false
  verifierToDelete.value = null
}

async function confirmDeleteVerifier() {
  const row = verifierToDelete.value
  if (!row?.id || !canDelete.value) return
  deleteInProgress.value = true
  try {
    await api.delete(`/verifiers/${row.id}`)
    closeDeleteModal()
    toastType.value = 'success'
    toastMessage.value = 'Verifier deleted successfully.'
    showToast.value = true
    await load()
  } catch (e) {
    const msg = e?.response?.status === 403
      ? 'You do not have permission to delete verifiers.'
      : (e?.response?.data?.message || 'Failed to delete verifier.')
    closeDeleteModal()
    toastType.value = 'error'
    toastMessage.value = msg
    showToast.value = true
  } finally {
    deleteInProgress.value = false
  }
}

const fromEntry = computed(() => (meta.value.current_page - 1) * meta.value.per_page + 1)
const toEntry = computed(() => Math.min(meta.value.current_page * meta.value.per_page, meta.value.total))

// Verifier detail popup
const showDetailModal = ref(false)
const selectedVerifier = ref(null)

function formatVerifierId(id) {
  if (id == null) return '—'
  return 'VER-' + String(id).padStart(3, '0')
}

function formatDateTime(iso) {
  if (!iso || typeof iso !== 'string') return '—'
  const s = iso.trim()
  const datePart = s.slice(0, 10)
  const timePart = s.slice(11, 16)
  const ddMonYyyy = toDdMonYyyy(datePart)
  if (!ddMonYyyy) return '—'
  if (timePart && timePart.length === 5) return `${ddMonYyyy}, ${timePart}`
  return ddMonYyyy
}

function openDetailModal(row) {
  selectedVerifier.value = row
  showDetailModal.value = true
}

function closeDetailModal() {
  showDetailModal.value = false
  selectedVerifier.value = null
}

// Edit Verifier modal (from detail popup)
const showEditModal = ref(false)
const editForm = ref({ id: null, verifier_name: '', verifier_number: '', remarks: '' })
const editSaving = ref(false)
const editError = ref('')

function openEditModal(row) {
  if (!row) return
  closeDetailModal()
  editForm.value = {
    id: row.id,
    verifier_name: row.verifier_name ?? '',
    verifier_number: row.verifier_number ?? '',
    remarks: row.remarks ?? '',
  }
  editError.value = ''
  showEditModal.value = true
}

function closeEditModal() {
  if (editSaving.value) return
  showEditModal.value = false
  editForm.value = { id: null, verifier_name: '', verifier_number: '', remarks: '' }
}

async function submitEdit() {
  const id = editForm.value.id
  if (!id) return
  const name = (editForm.value.verifier_name || '').trim()
  const number = (editForm.value.verifier_number || '').trim()
  if (!name) {
    editError.value = 'Verifier name is required.'
    return
  }
  if (!number) {
    editError.value = 'Verifier number is required.'
    return
  }
  editSaving.value = true
  editError.value = ''
  try {
    await api.put(`/verifiers/${id}`, {
      verifier_name: name,
      verifier_number: number || null,
      remarks: (editForm.value.remarks || '').trim() || null,
    })
    closeEditModal()
    await load()
    successMessage.value = 'Verifier updated.'
    setTimeout(() => { successMessage.value = '' }, 3000)
  } catch (e) {
    editError.value = e?.response?.data?.message || 'Failed to update verifier.'
  } finally {
    editSaving.value = false
  }
}

onMounted(() => load())
</script>

<template>
  <div class="space-y-6 bg-white -mx-4 -my-5 min-h-full px-6 py-6">
    <Toast
      :show="showToast"
      :type="toastType"
      :message="toastMessage"
      :duration="toastType === 'error' ? 5000 : 3000"
      @dismiss="dismissToast"
    />

    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <div class="flex flex-wrap items-baseline gap-2">
          <h1 class="text-2xl font-bold text-gray-900 leading-tight">Verifiers Detail</h1>
          <Breadcrumbs />
        </div>
        <p class="mt-1 text-sm text-gray-500">Manage verifier directory for DSP Tracker integration.</p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <input
          ref="csvInputRef"
          type="file"
          accept=".csv"
          class="hidden"
          @change="onCsvFileChange"
        />
        <button
          v-if="canAdd"
          type="button"
          @click="triggerImport"
          class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
          </svg>
          Import CSV
        </button>
        <button
          type="button"
          @click="exportCsv"
          class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l4-4m-4 4V4" />
          </svg>
          Export CSV
        </button>
        <button
          v-if="canAdd"
          type="button"
          @click="openAddModal"
          class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Add Verifier
        </button>
      </div>
    </div>

    <div v-if="successMessage" class="rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700 flex items-center justify-between">
      <span>{{ successMessage }}</span>
      <button type="button" @click="successMessage = ''" class="text-green-600 hover:text-green-800">×</button>
    </div>
    <div v-if="errorMessage" class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700 flex items-center justify-between">
      <span>{{ errorMessage }}</span>
      <button type="button" @click="errorMessage = ''" class="text-red-600 hover:text-red-800">×</button>
    </div>
    <div v-if="importSuccess" class="rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ importSuccess }}</div>
    <div v-if="importError" class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ importError }}</div>

    <!-- Search -->
    <div class="relative">
      <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </span>
      <input
        v-model="searchQ"
        type="search"
        placeholder="Search by verifier name, number, or ID..."
        class="w-full rounded-lg border border-gray-300 py-2 pl-10 pr-4 text-sm focus:border-indigo-500 focus:ring-indigo-500"
        @keydown.enter="load(1)"
      />
      <button
        type="button"
        class="absolute right-2 top-1/2 -translate-y-1/2 rounded px-3 py-1 text-sm text-indigo-600 hover:bg-indigo-50"
        @click="load(1)"
      >
        Search
      </button>
    </div>

    <!-- Table -->
    <div class="rounded-xl border-2 border-black bg-white shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full">
          <thead class="bg-gray-50 border-b-2 border-black">
            <tr>
              <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 tracking-wider w-14">no.</th>
              <th
                scope="col"
                class="px-4 py-3 text-left text-xs font-medium text-gray-500 tracking-wider cursor-pointer hover:bg-gray-100 select-none"
                @click="setSort('verifier_name')"
              >
                <span class="inline-flex items-center gap-1">Verifier Name {{ sortIcon('verifier_name') }}</span>
              </th>
              <th
                scope="col"
                class="px-4 py-3 text-left text-xs font-medium text-gray-500 tracking-wider cursor-pointer hover:bg-gray-100 select-none"
                @click="setSort('verifier_number')"
              >
                <span class="inline-flex items-center gap-1">Verifier Number {{ sortIcon('verifier_number') }}</span>
              </th>
              <th
                scope="col"
                class="px-4 py-3 text-left text-xs font-medium text-gray-500 tracking-wider cursor-pointer hover:bg-gray-100 select-none"
                @click="setSort('remarks')"
              >
                <span class="inline-flex items-center gap-1">Remarks {{ sortIcon('remarks') }}</span>
              </th>
              <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 tracking-wider w-24">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white">
            <tr v-if="loading" class="border-b border-black bg-gray-50">
              <td colspan="5" class="px-4 py-8 text-center text-gray-500">Loading...</td>
            </tr>
            <tr v-else-if="!list.length" class="border-b border-black">
              <td colspan="5" class="px-4 py-8 text-center text-gray-500">No verifiers found.</td>
            </tr>
            <tr v-for="(row, idx) in list" :key="row.id" class="border-b border-black hover:bg-gray-50">
              <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ fromEntry + idx }}</td>
              <td
                class="px-4 py-3 text-sm text-gray-900"
                :class="{ 'cursor-pointer hover:bg-gray-100': canEditCell(row, 'verifier_name') }"
                :title="canEditCell(row, 'verifier_name') ? 'Double-click to edit' : undefined"
                @dblclick="canEditCell(row, 'verifier_name') && startEdit(row, 'verifier_name')"
              >
                <template v-if="isEditing(row.id, 'verifier_name')">
                  <div class="flex flex-wrap items-center gap-1">
                    <input
                      v-model="editValue"
                      type="text"
                      class="rounded border border-gray-300 text-sm py-1 px-2 min-w-[140px]"
                      @keydown.enter="saveEdit"
                      @keydown.escape="cancelEdit"
                    />
                    <button type="button" class="rounded bg-green-600 px-2 py-1 text-xs text-white hover:bg-green-700 disabled:opacity-50" :disabled="savingCell" @click="saveEdit">Save</button>
                    <button type="button" class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50" :disabled="savingCell" @click="cancelEdit">Cancel</button>
                  </div>
                </template>
                <span v-else>{{ row.verifier_name || '—' }}</span>
              </td>
              <td
                class="px-4 py-3 text-sm text-gray-600"
                :class="{ 'cursor-pointer hover:bg-gray-100': canEditCell(row, 'verifier_number') }"
                :title="canEditCell(row, 'verifier_number') ? 'Double-click to edit' : undefined"
                @dblclick="canEditCell(row, 'verifier_number') && startEdit(row, 'verifier_number')"
              >
                <template v-if="isEditing(row.id, 'verifier_number')">
                  <div class="flex flex-wrap items-center gap-1">
                    <input
                      v-model="editValue"
                      type="text"
                      class="rounded border border-gray-300 text-sm py-1 px-2 min-w-[120px]"
                      @keydown.enter="saveEdit"
                      @keydown.escape="cancelEdit"
                    />
                    <button type="button" class="rounded bg-green-600 px-2 py-1 text-xs text-white hover:bg-green-700 disabled:opacity-50" :disabled="savingCell" @click="saveEdit">Save</button>
                    <button type="button" class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50" :disabled="savingCell" @click="cancelEdit">Cancel</button>
                  </div>
                </template>
                <span v-else>{{ row.verifier_number || '—' }}</span>
              </td>
              <td
                class="px-4 py-3 text-sm text-gray-600 max-w-md"
                :class="{ 'cursor-pointer hover:bg-gray-100': canEditCell(row, 'remarks') }"
                :title="canEditCell(row, 'remarks') ? 'Double-click to edit' : undefined"
                @dblclick="canEditCell(row, 'remarks') && startEdit(row, 'remarks')"
              >
                <template v-if="isEditing(row.id, 'remarks')">
                  <div class="flex flex-wrap items-center gap-1">
                    <input
                      v-model="editValue"
                      type="text"
                      class="rounded border border-gray-300 text-sm py-1 px-2 min-w-[180px] max-w-full"
                      @keydown.enter="saveEdit"
                      @keydown.escape="cancelEdit"
                    />
                    <button type="button" class="rounded bg-green-600 px-2 py-1 text-xs text-white hover:bg-green-700 disabled:opacity-50" :disabled="savingCell" @click="saveEdit">Save</button>
                    <button type="button" class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50" :disabled="savingCell" @click="cancelEdit">Cancel</button>
                  </div>
                </template>
                <span v-else>{{ row.remarks || '—' }}</span>
              </td>
              <td class="px-4 py-3 text-right whitespace-nowrap">
                <div class="flex items-center justify-end gap-1">
                  <button
                    type="button"
                    @click="openDetailModal(row)"
                    class="rounded px-2 py-1 text-xs font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100"
                  >
                    View
                  </button>
                  <button
                    v-if="canDelete"
                    type="button"
                    @click="openDeleteModal(row)"
                    class="rounded px-2 py-1 text-xs font-medium text-red-700 bg-red-50 hover:bg-red-100"
                  >
                    Delete
                  </button>
                  <span v-if="!canDelete" class="text-gray-400 text-xs">—</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Pagination -->
      <div class="flex flex-wrap items-center justify-between gap-3 border-t border-black bg-white px-4 py-3">
        <p class="text-sm text-gray-600">
          Showing {{ fromEntry }} to {{ toEntry }} of {{ meta.total }} entries
        </p>
        <div class="flex items-center gap-4">
          <div class="flex items-center gap-2 text-sm text-gray-600">
            <span class="whitespace-nowrap font-medium">Number of rows</span>
            <select
              :value="perPage"
              class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm min-w-[80px] text-gray-700 focus:border-green-500 focus:ring-1 focus:ring-green-500"
              @change="(e) => { setPerPage(Number(e.target.value)); load(1) }"
            >
              <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
            </select>
          </div>
          <div class="flex items-center gap-1.5">
            <button type="button" :disabled="meta.current_page <= 1" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50" @click="goPage(meta.current_page - 1)">Previous</button>
            <span class="rounded-md border border-gray-300 bg-gray-50 px-3 py-1.5 text-sm text-gray-700">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
            <button type="button" :disabled="meta.current_page >= meta.last_page" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50" @click="goPage(meta.current_page + 1)">Next</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Verifier modal popup -->
    <Teleport to="body">
      <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4 bg-black/50" @click.self="closeAddModal">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full max-h-[90vh] flex flex-col overflow-hidden" @click.stop>
          <div class="flex items-center justify-between px-6 pt-6 pb-4 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900">Add New Verifier</h3>
            <button
              type="button"
              class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
              aria-label="Close"
              :disabled="addSaving"
              @click="closeAddModal"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div class="px-6 py-5 overflow-y-auto flex-1 min-h-0">
            <div v-if="addError" class="mb-4 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">{{ addError }}</div>
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Verifier Name <span class="text-red-500">*</span></label>
                <input
                  v-model="addForm.verifier_name"
                  type="text"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-teal-500"
                  placeholder="Enter verifier full name"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Verifier Number <span class="text-red-500">*</span></label>
                <input
                  v-model="addForm.verifier_number"
                  type="text"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-teal-500"
                  placeholder="+971-50-123-4567"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional)</label>
                <textarea
                  v-model="addForm.remarks"
                  rows="3"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-teal-500"
                  placeholder="Add any additional notes or comments..."
                />
              </div>
            </div>
          </div>
          <div class="px-6 pb-6 pt-2 flex justify-end gap-3">
            <button
              type="button"
              class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
              :disabled="addSaving"
              @click="closeAddModal"
            >
              Cancel
            </button>
            <button
              type="button"
              class="rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50 transition-colors"
              :disabled="addSaving"
              @click="submitAdd"
            >
              Add Verifier
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Verifier Details popup -->
    <Teleport to="body">
      <div v-if="showDetailModal && selectedVerifier" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4 bg-black/50" @click.self="closeDetailModal">
        <div class="bg-white rounded-xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto" @click.stop>
          <div class="flex items-center justify-between px-6 pt-6 pb-4 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900">Verifier Details</h3>
            <button
              type="button"
              class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
              aria-label="Close"
              @click="closeDetailModal"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div class="px-6 py-5 space-y-5">
            <!-- Header: name, phone, Active badge -->
            <div class="rounded-lg bg-gray-50 border border-gray-100 p-4 flex items-start justify-between gap-3">
              <div>
                <h4 class="text-xl font-bold text-gray-900">{{ selectedVerifier.verifier_name || '—' }}</h4>
                <p class="mt-1 flex items-center gap-2 text-sm text-gray-600">
                  <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                  </svg>
                  {{ selectedVerifier.verifier_number || '—' }}
                </p>
              </div>
              <span class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-800 shrink-0">Active</span>
            </div>

            <!-- Verifier Information -->
            <div class="rounded-lg border border-gray-200 bg-white p-4">
              <h5 class="text-sm font-semibold text-gray-900 mb-3">Verifier Information</h5>
              <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                  <dt class="text-gray-500 font-medium">Verifier ID</dt>
                  <dd class="mt-0.5 text-gray-900">{{ formatVerifierId(selectedVerifier.id) }}</dd>
                </div>
                <div>
                  <dt class="text-gray-500 font-medium">Status</dt>
                  <dd class="mt-0.5"><span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Active</span></dd>
                </div>
                <div>
                  <dt class="text-gray-500 font-medium">Full Name</dt>
                  <dd class="mt-0.5 text-gray-900">{{ selectedVerifier.verifier_name || '—' }}</dd>
                </div>
                <div>
                  <dt class="text-gray-500 font-medium">Contact Number</dt>
                  <dd class="mt-0.5 text-gray-900">{{ selectedVerifier.verifier_number || '—' }}</dd>
                </div>
              </dl>
            </div>

            <!-- System Information -->
            <div class="rounded-lg border border-gray-200 bg-white p-4">
              <h5 class="text-sm font-semibold text-gray-900 mb-3">System Information</h5>
              <dl class="grid grid-cols-1 gap-y-3 text-sm">
                <div>
                  <dt class="text-gray-500 font-medium">Added By</dt>
                  <dd class="mt-0.5 text-gray-900">—</dd>
                </div>
                <div>
                  <dt class="text-gray-500 font-medium">Created Date</dt>
                  <dd class="mt-0.5 text-gray-900">{{ formatDateTime(selectedVerifier.created_at) }}</dd>
                </div>
                <div>
                  <dt class="text-gray-500 font-medium">Last Updated</dt>
                  <dd class="mt-0.5 text-gray-900">{{ formatDateTime(selectedVerifier.updated_at) }}</dd>
                </div>
              </dl>
            </div>

            <!-- Notes -->
            <div class="rounded-lg border border-gray-200 bg-white p-4">
              <h5 class="text-sm font-semibold text-gray-900 mb-2">Notes</h5>
              <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ selectedVerifier.remarks || '—' }}</p>
            </div>

            <!-- DSP Tracker Integration -->
            <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 flex gap-3">
              <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
              </svg>
              <div class="text-sm text-blue-800">
                <p class="font-medium text-blue-900 mb-1">DSP Tracker Integration</p>
                <p>This verifier's information is automatically linked to DSP Tracker records. When a CSV upload contains this verifier's name, their contact number will be automatically populated in the DSP Tracker results.</p>
              </div>
            </div>
          </div>
          <div class="px-6 pb-6 pt-2 flex justify-end gap-3 border-t border-gray-100">
            <button
              type="button"
              class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
              @click="closeDetailModal"
            >
              Close
            </button>
            <button
              v-if="canAdd"
              type="button"
              class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-green-700 transition-colors"
              @click="openEditModal(selectedVerifier)"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
              </svg>
              Edit Verifier
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Edit Verifier modal (same design as Add New Verifier, with pre-filled values) -->
    <Teleport to="body">
      <div v-if="showEditModal" class="fixed inset-0 z-[60] flex items-center justify-center overflow-y-auto p-4 bg-black/50" @click.self="closeEditModal">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full max-h-[90vh] flex flex-col overflow-hidden" @click.stop>
          <div class="flex items-center justify-between px-6 pt-6 pb-4 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900">Edit Verifier</h3>
            <button
              type="button"
              class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
              aria-label="Close"
              :disabled="editSaving"
              @click="closeEditModal"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div class="px-6 py-5 overflow-y-auto flex-1 min-h-0">
            <div v-if="editError" class="mb-4 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">{{ editError }}</div>
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Verifier Name <span class="text-red-500">*</span></label>
                <input
                  v-model="editForm.verifier_name"
                  type="text"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-teal-500"
                  placeholder="Enter verifier full name"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Verifier Number <span class="text-red-500">*</span></label>
                <input
                  v-model="editForm.verifier_number"
                  type="text"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-teal-500"
                  placeholder="+971-50-123-4567"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional)</label>
                <textarea
                  v-model="editForm.remarks"
                  rows="3"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-teal-500 focus:ring-teal-500"
                  placeholder="Add any additional notes or comments..."
                />
              </div>
            </div>
          </div>
          <div class="px-6 pb-6 pt-2 flex justify-end gap-3">
            <button
              type="button"
              class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
              :disabled="editSaving"
              @click="closeEditModal"
            >
              Cancel
            </button>
            <button
              type="button"
              class="rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50 transition-colors"
              :disabled="editSaving"
              @click="submitEdit"
            >
              Update Verifier
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Delete Verifier confirmation modal (only for users with delete permission) -->
    <Teleport to="body">
      <div v-if="showDeleteModal && verifierToDelete" class="fixed inset-0 z-[70] flex items-center justify-center overflow-y-auto p-4 bg-black/50" @click.self="closeDeleteModal">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full overflow-hidden" @click.stop>
          <div class="px-6 pt-6 pb-4">
            <div class="flex gap-3">
              <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </div>
              <div>
                <h3 class="text-lg font-semibold text-gray-900">Delete Verifier</h3>
                <p class="mt-2 text-sm text-gray-600">
                  Are you sure you want to delete <strong>{{ verifierToDelete.verifier_name || 'this verifier' }}</strong>? This action cannot be undone.
                </p>
              </div>
            </div>
          </div>
          <div class="px-6 pb-6 pt-2 flex justify-end gap-3">
            <button
              type="button"
              class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
              :disabled="deleteInProgress"
              @click="closeDeleteModal"
            >
              Cancel
            </button>
            <button
              type="button"
              class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50 transition-colors"
              :disabled="deleteInProgress"
              @click="confirmDeleteVerifier"
            >
              {{ deleteInProgress ? 'Deleting…' : 'Delete' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
