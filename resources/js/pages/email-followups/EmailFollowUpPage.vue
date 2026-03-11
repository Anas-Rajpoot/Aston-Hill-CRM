<script setup>
/**
 * Email Follow-Up – add form + listing on same page. Added By auto-filled from logged-in user.
 */
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import emailFollowUpsApi from '@/services/emailFollowUpsApi'
import FiltersBar from '@/components/email-followups/FiltersBar.vue'
import AdvancedFilters from '@/components/email-followups/AdvancedFilters.vue'
import ColumnCustomizerModal from '@/components/lead-submissions/ColumnCustomizerModal.vue'
import EmailFollowUpTable from '@/components/email-followups/EmailFollowUpTable.vue'
import Toast from '@/components/Toast.vue'
import DateInputDdMmYyyy from '@/components/DateInputDdMmYyyy.vue'
import DeleteOtpModal from '@/components/DeleteOtpModal.vue'
import api from '@/lib/axios'
import { canModuleAction } from '@/lib/accessControl'

const auth = useAuthStore()
const canView = computed(() =>
  canModuleAction(auth.user, 'email-follow-up', 'view', [
    'emails_followup.list',
    'emails_followup.view',
  ])
)
const canCreate = computed(() =>
  canModuleAction(auth.user, 'email-follow-up', 'create', [
    'emails_followup.create',
    'emails_followup.add',
  ])
)
const canEdit = computed(() =>
  canModuleAction(auth.user, 'email-follow-up', 'edit', [
    'emails_followup.edit',
    'emails_followup.update',
  ])
)
const canDelete = computed(() =>
  canModuleAction(auth.user, 'email-follow-up', 'delete', [
    'emails_followup.delete',
  ])
)
const canExport = computed(() =>
  canModuleAction(auth.user, 'email-follow-up', 'export', [
    'emails_followup.export',
    'emails_followup.export_reports',
  ])
)
const TABLE_MODULE = 'email-followups'
const perPageOptions = ref([10, 20, 25, 50, 100])
const addedByName = ref('')
const loading = ref(true)
const submitLoading = ref(false)
const exportLoading = ref(false)
const bulkLoading = ref(false)
const selectedIds = ref([])
const bulkStatus = ref('approved')

/* ───── Toast ───── */
const showToast = ref(false)
const toastType = ref('success')
const toastMsg  = ref('')
function toast(t, m) { toastType.value = t; toastMsg.value = m; showToast.value = true }
const deleteModalVisible = ref(false)
const deleteLoading = ref(false)
const deleteTargetIds = ref([])
const deleteTargetLabel = ref('selected records')
const filterOptions = ref({ statuses: [], categories: [] })
const submissions = ref([])
const meta = ref({ current_page: 1, last_page: 1, per_page: auth.defaultTablePageSize || 25, total: 0 })
const allColumns = ref([])
const visibleColumns = ref(['id', 'email_date', 'subject', 'request_from', 'sent_to', 'creator', 'status', 'status_date'])
const sort = ref('email_date')
const order = ref('desc')
const advancedVisible = ref(false)
const columnModalVisible = ref(false)

const form = ref({
  email_date: '',
  status: 'pending',
  subject: '',
  request_from: '',
  sent_to: '',
})

const filters = ref({
  q: '',
  status: 'pending',
  from: '',
  to: '',
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
  if (f.q) p.q = f.q
  if (f.status) p.status = f.status
  if (f.from) p.from = f.from
  if (f.to) p.to = f.to
  return p
}

async function load() {
  if (!canView.value) {
    submissions.value = []
    loading.value = false
    return
  }
  window.scrollTo(0, 0)
  loading.value = true
  try {
    const data = await emailFollowUpsApi.index(buildParams())
    submissions.value = data.data ?? []
    meta.value = data.meta ?? meta.value
    const visibleIdSet = new Set(submissions.value.map((row) => Number(row.id)))
    selectedIds.value = selectedIds.value.filter((id) => visibleIdSet.has(Number(id)))
  } finally {
    loading.value = false
  }
}

async function loadFilters() {
  if (!canView.value) return
  try {
    const data = await emailFollowUpsApi.filters()
    const statuses = (data.statuses ?? []).map((status) => {
      if (String(status?.value || '').toLowerCase() === 'pending') {
        return { ...status, label: 'Open' }
      }
      if (String(status?.value || '').toLowerCase() === 'approved') {
        return { ...status, label: 'Closed' }
      }
      return status
    })
    filterOptions.value = {
      statuses,
    }
  } catch {
    //
  }
}

async function loadColumns() {
  if (!canView.value) return
  try {
    const data = await emailFollowUpsApi.columns()
    allColumns.value = (data.all_columns ?? []).filter((c) => c?.key !== 'category')
    visibleColumns.value = (data.visible_columns ?? visibleColumns.value).filter((c) => c !== 'category')
  } catch {
    //
  }
}

function applyFilters() {
  meta.value.current_page = 1
  load()
}

function resetFilters() {
  filters.value = { q: '', status: 'pending', category: '', from: '', to: '' }
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
    const safeCols = cols.filter((c) => c !== 'category')
    await emailFollowUpsApi.saveColumns(safeCols)
    visibleColumns.value = safeCols
    meta.value.current_page = 1
    load()
  } catch {
    //
  }
}

async function onUpdateCell(id, field, value) {
  if (!canEdit.value) return
  const row = submissions.value.find((r) => r.id === id)
  const prev = row ? { ...row } : null
  if (row) row[field] = value
  try {
    const res = await emailFollowUpsApi.patch(id, { [field]: value })
    if (res?.row && row) Object.assign(row, res.row)
  } catch {
    if (prev) Object.assign(row, prev)
    load()
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

const selectedCount = computed(() => selectedIds.value.length)
const visibleIds = computed(() => submissions.value.map((row) => Number(row.id)))
const allRowsSelected = computed(() => {
  const ids = visibleIds.value
  return ids.length > 0 && ids.every((id) => selectedIds.value.includes(id))
})
const someRowsSelected = computed(() => {
  const ids = visibleIds.value
  const selectedVisible = ids.filter((id) => selectedIds.value.includes(id)).length
  return selectedVisible > 0 && selectedVisible < ids.length
})

function toggleSelectAll() {
  if (allRowsSelected.value) {
    selectedIds.value = selectedIds.value.filter((id) => !visibleIds.value.includes(id))
    return
  }
  selectedIds.value = Array.from(new Set([...selectedIds.value, ...visibleIds.value]))
}

function toggleRowSelection(rowId) {
  const id = Number(rowId)
  if (selectedIds.value.includes(id)) {
    selectedIds.value = selectedIds.value.filter((v) => v !== id)
    return
  }
  selectedIds.value = [...selectedIds.value, id]
}

function clearSelection() {
  selectedIds.value = []
}

function openDeleteModal(ids, label) {
  const normalizedIds = Array.from(new Set((ids || []).map((v) => Number(v)).filter((v) => Number.isInteger(v) && v > 0)))
  if (!normalizedIds.length || !canDelete.value) return
  deleteTargetIds.value = normalizedIds
  deleteTargetLabel.value = label || (normalizedIds.length === 1 ? `Email Follow-Up #${normalizedIds[0]}` : `${normalizedIds.length} selected email follow-up entries`)
  deleteModalVisible.value = true
}

function closeDeleteModal() {
  if (deleteLoading.value) return
  deleteModalVisible.value = false
  deleteTargetIds.value = []
  deleteTargetLabel.value = 'selected records'
}

async function confirmOtpDelete() {
  if (!deleteTargetIds.value.length || !canDelete.value || deleteLoading.value) return
  deleteLoading.value = true
  try {
    const idsToDelete = [...deleteTargetIds.value]
    const res = await emailFollowUpsApi.bulkAction({
      action: 'delete',
      ids: idsToDelete,
    })
    toast('success', res?.message || 'Selected rows deleted.')
    selectedIds.value = selectedIds.value.filter((id) => !idsToDelete.includes(Number(id)))
    // Close immediately on success (do not rely on guarded close handler while loading).
    deleteModalVisible.value = false
    deleteTargetIds.value = []
    deleteTargetLabel.value = 'selected records'
    await load()
  } catch (e) {
    const msg = e.response?.data?.message || e.message || 'Failed to delete selected rows.'
    toast('error', msg)
  } finally {
    deleteLoading.value = false
  }
}

async function applyBulkStatus() {
  if (!selectedIds.value.length || !bulkStatus.value || !canEdit.value) return
  bulkLoading.value = true
  try {
    const res = await emailFollowUpsApi.bulkAction({
      action: 'status',
      ids: selectedIds.value,
      status: bulkStatus.value,
    })
    toast('success', res?.message || 'Status updated for selected rows.')
    clearSelection()
    await load()
  } catch (e) {
    const msg = e.response?.data?.message || e.message || 'Failed to update selected rows.'
    toast('error', msg)
  } finally {
    bulkLoading.value = false
  }
}

async function applyBulkDelete() {
  if (!selectedIds.value.length || !canDelete.value) return
  openDeleteModal(
    selectedIds.value,
    `${selectedIds.value.length} selected email follow-up entr${selectedIds.value.length === 1 ? 'y' : 'ies'}`
  )
}

async function onDeleteRow(row) {
  const id = Number(row?.id)
  if (!id || !canDelete.value) return
  const label = row?.subject ? `"${row.subject}"` : `Email Follow-Up #${id}`
  openDeleteModal([id], label)
}

async function loadTablePreference() {
  try {
    const { data } = await api.get(`/table-preferences/${TABLE_MODULE}`)
    if (data.per_page) meta.value.per_page = Number(data.per_page)
    if (Array.isArray(data.options) && data.options.length) perPageOptions.value = data.options
  } catch { /* use system default */ }
}

function clearForm() {
  form.value = {
    email_date: '',
    status: 'pending',
    subject: '',
    request_from: '',
    sent_to: '',
  }
}

async function submitForm() {
  if (!canCreate.value) return
  if (!form.value.email_date) return
  submitLoading.value = true
  try {
    await emailFollowUpsApi.store({
      email_date: form.value.email_date,
      status: form.value.status || 'pending',
      category: 'General',
      subject: form.value.subject || null,
      request_from: form.value.request_from || null,
      sent_to: form.value.sent_to || null,
    })
    clearForm()
    meta.value.current_page = 1
    load()
  } catch (e) {
    const msg = e.response?.data?.message || e.message || 'Failed to add entry.'
    toast('error', msg)
  } finally {
    submitLoading.value = false
  }
}

const COLUMN_LABELS = {
  id: 'ID',
  email_date: 'Email Date',
  subject: 'Subject',
  category: 'Category',
  request_from: 'Request From',
  sent_to: 'Sent To',
  creator: 'Added By',
  status: 'Status',
}

function escapeCsv(val) {
  if (val == null) return ''
  const s = String(val)
  if (s.includes(',') || s.includes('"') || s.includes('\n')) return '"' + s.replace(/"/g, '""') + '"'
  return s
}

async function onExport() {
  if (!canExport.value) return
  exportLoading.value = true
  try {
    const baseParams = { ...buildParams(), per_page: 100 }
    let currentPage = 1
    let lastPage = 1
    const rows = []

    do {
      const data = await emailFollowUpsApi.index({ ...baseParams, page: currentPage })
      rows.push(...(data.data ?? []))
      const metaInfo = data.meta ?? {}
      lastPage = Number(metaInfo.last_page || 1)
      currentPage += 1
    } while (currentPage <= lastPage)

    const cols = visibleColumns.value
    const headers = cols.map((c) => COLUMN_LABELS[c] ?? c)
    const csvRows = [headers.map(escapeCsv).join(',')]
    for (const row of rows) {
      csvRows.push(cols.map((c) => escapeCsv(row[c])).join(','))
    }
    const blob = new Blob([csvRows.join('\r\n')], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `email-follow-ups-${new Date().toISOString().slice(0, 10)}.csv`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    //
  } finally {
    exportLoading.value = false
  }
}

onMounted(async () => {
  await loadTablePreference()
  addedByName.value = auth.user?.name ?? ''
  if (canView.value) {
    loadFilters()
    loadColumns()
    load()
  } else {
    loading.value = false
  }
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-white py-6 px-4 sm:px-6">
    <div class="mx-auto max-w-7xl space-y-6">
      <!-- Add Email Follow-Up Entry -->
      <div v-if="canCreate" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold text-gray-900">Add Email Follow-Up Entry</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Added By <span class="text-red-500">*</span></label>
            <input
              :value="addedByName"
              type="text"
              readonly
              class="mt-1 block w-full rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Email Date <span class="text-red-500">*</span></label>
            <DateInputDdMmYyyy v-model="form.email_date" placeholder="DD-MMM-YYYY" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Status <span class="text-red-500">*</span></label>
            <select
              v-model="form.status"
              class="mt-1 block w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
            >
              <option value="pending">Open</option>
              <option value="approved">Closed</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Request From</label>
            <input
              v-model="form.request_from"
              type="text"
              placeholder="Company or person name"
              class="mt-1 block w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
            />
          </div>
          <div class="sm:col-span-2 lg:col-span-2">
            <label class="block text-sm font-medium text-gray-700">Subject</label>
            <input
              v-model="form.subject"
              type="text"
              placeholder="Enter email subject"
              class="mt-1 block w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Sent To</label>
            <input
              v-model="form.sent_to"
              type="text"
              placeholder="recipient@example.com"
              class="mt-1 block w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
            />
          </div>
          <div class="flex items-end justify-start gap-2">
            <button
              type="button"
              class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
              @click="clearForm"
            >
              Clear
            </button>
            <button
              type="button"
              class="inline-flex items-center rounded bg-brand-primary px-4 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover disabled:opacity-70"
              :disabled="submitLoading || !form.email_date"
              @click="submitForm"
            >
              <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              {{ submitLoading ? 'Adding...' : 'Add Entry' }}
            </button>
          </div>
        </div>
      </div>

      <div v-if="!canView" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        You do not have permission to view email follow-ups.
      </div>

      <FiltersBar
        v-if="canView"
        :filters="filters"
        :filter-options="filterOptions"
        :loading="loading"
        @apply="applyFilters"
        @reset="resetFilters"
      >
        <template #after-reset>
          <div
            v-if="selectedCount > 0"
            class="inline-flex flex-wrap items-center gap-2 rounded-lg border border-brand-primary/30 bg-brand-primary/5 px-2 py-1"
          >
            <span class="text-xs font-medium text-brand-primary">{{ selectedCount }} selected</span>
            <select
              v-model="bulkStatus"
              class="rounded border border-gray-300 bg-white px-2 py-1 text-xs text-gray-700 focus:border-brand-primary focus:ring-1 focus:ring-brand-primary"
              :disabled="bulkLoading"
            >
              <option value="pending">Open</option>
              <option value="approved">Closed</option>
            </select>
            <button
              type="button"
              class="rounded border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-60"
              :disabled="bulkLoading || !canEdit"
              @click="applyBulkStatus"
            >
              Change Status
            </button>
            <button
              type="button"
              class="rounded border border-red-300 bg-red-50 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-100 disabled:opacity-60"
              :disabled="bulkLoading || !canDelete"
              @click="applyBulkDelete"
            >
              Delete
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-60"
              :disabled="bulkLoading"
              @click="clearSelection"
            >
              Clear
            </button>
          </div>
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
          </button>
          <button
            v-if="canExport"
            type="button"
            class="inline-flex items-center rounded bg-brand-primary px-3 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover disabled:opacity-70"
            :disabled="loading || exportLoading"
            @click="onExport"
          >
            {{ exportLoading ? 'Exporting...' : 'Export Report' }}
          </button>
        </template>
      </FiltersBar>

      <AdvancedFilters
        v-if="canView"
        :visible="advancedVisible"
        :filters="filters"
        :filter-options="filterOptions"
        :loading="loading"
        @apply="applyFilters"
        @reset="resetFilters"
      />

      <div v-if="canView" class="overflow-hidden rounded-xl border-2 border-black bg-white shadow-sm">
        <EmailFollowUpTable
          :columns="visibleColumns"
          :data="submissions"
          :sort="sort"
          :order="order"
          :loading="loading"
          :current-page="meta.current_page"
          :per-page="meta.per_page"
          :edit-options="filterOptions"
          :can-inline-edit="canEdit"
          :selected-ids="selectedIds"
          :all-rows-selected="allRowsSelected"
          :some-rows-selected="someRowsSelected"
          @sort="onSort"
          @update-cell="onUpdateCell"
          @toggle-select-all="toggleSelectAll"
          @toggle-row-selection="toggleRowSelection"
          @delete="onDeleteRow"
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

    <DeleteOtpModal
      :visible="deleteModalVisible"
      title="Delete Email Follow-Up"
      :item-label="deleteTargetLabel"
      :loading="deleteLoading"
      @close="closeDeleteModal"
      @confirm="confirmOtpDelete"
    />

    <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="4000" @dismiss="showToast = false" />
  </div>
</template>
