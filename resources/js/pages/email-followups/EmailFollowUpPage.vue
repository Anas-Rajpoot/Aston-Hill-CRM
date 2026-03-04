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
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import DateInputDdMmYyyy from '@/components/DateInputDdMmYyyy.vue'
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
const filterOptions = ref({ statuses: [], categories: [] })
const submissions = ref([])
const meta = ref({ current_page: 1, last_page: 1, per_page: auth.defaultTablePageSize || 25, total: 0 })
const allColumns = ref([])
const visibleColumns = ref(['id', 'email_date', 'subject', 'category', 'request_from', 'sent_to', 'creator', 'status', 'status_date'])
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
  category: '',
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
  if (f.category) p.category = f.category
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
  } finally {
    loading.value = false
  }
}

async function loadFilters() {
  if (!canView.value) return
  try {
    const data = await emailFollowUpsApi.filters()
    filterOptions.value = {
      statuses: data.statuses ?? [],
      categories: data.categories ?? [],
    }
  } catch {
    //
  }
}

async function loadColumns() {
  if (!canView.value) return
  try {
    const data = await emailFollowUpsApi.columns()
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
    await emailFollowUpsApi.saveColumns(cols)
    visibleColumns.value = cols
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
    alert(msg)
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
  const params = { ...buildParams(), page: 1, per_page: 500 }
  exportLoading.value = true
  try {
    const data = await emailFollowUpsApi.index(params)
    const rows = data.data ?? []
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
      <div class="flex flex-wrap items-baseline gap-2">
        <h1 class="text-xl font-semibold text-gray-900 leading-tight">Email Follow-Up</h1>
        <Breadcrumbs />
      </div>
      <p class="text-sm text-gray-600">Record outgoing follow-up emails and track communication history.</p>

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
              class="mt-1 block w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            >
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Request From</label>
            <input
              v-model="form.request_from"
              type="text"
              placeholder="Company or person name"
              class="mt-1 block w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            />
          </div>
          <div class="sm:col-span-2 lg:col-span-2">
            <label class="block text-sm font-medium text-gray-700">Subject</label>
            <input
              v-model="form.subject"
              type="text"
              placeholder="Enter email subject"
              class="mt-1 block w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Sent To</label>
            <input
              v-model="form.sent_to"
              type="text"
              placeholder="recipient@example.com"
              class="mt-1 block w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
            />
          </div>
        </div>
        <div class="mt-4 flex justify-end gap-2">
          <button
            type="button"
            class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            @click="clearForm"
          >
            Clear
          </button>
          <button
            type="button"
            class="inline-flex items-center rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-70"
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

      <!-- Listing -->
      <div v-if="canView" class="flex flex-wrap items-center justify-between gap-4">
        <span class="text-sm text-gray-600">Listing</span>
        <button
          v-if="canExport"
          type="button"
          class="inline-flex items-center rounded bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-70"
          :disabled="loading || exportLoading"
          @click="onExport"
        >
          {{ exportLoading ? 'Exporting...' : 'Export Report' }}
        </button>
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
          @sort="onSort"
          @update-cell="onUpdateCell"
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

    <ColumnCustomizerModal
      :visible="columnModalVisible"
      :all-columns="allColumns"
      :visible-columns="visibleColumns"
      @update:visible="columnModalVisible = $event"
      @save="onSaveColumns"
    />
  </div>
</template>
