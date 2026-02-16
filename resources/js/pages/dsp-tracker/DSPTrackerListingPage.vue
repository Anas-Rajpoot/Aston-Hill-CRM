<script setup>
/**
 * DSP Tracker listing – data from API (dsp_tracker_entries). Filters, advanced filters,
 * customize columns, sortable table. Upload CSV saves to DB; Delete Old File deletes last batch.
 */
import { ref, computed, onMounted } from 'vue'
import { fromDdMmYyyy } from '@/lib/dateFormat'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import AdvancedFilters from '@/components/dsp-tracker/AdvancedFilters.vue'
import ColumnCustomizerModal from '@/components/lead-submissions/ColumnCustomizerModal.vue'
import DSPTrackerDetailModal from '@/components/dsp-tracker/DSPTrackerDetailModal.vue'
import dspTrackerApi from '@/services/dspTrackerApi'
import Toast from '@/components/Toast.vue'

const COLUMNS = [
  'activity_number',
  'company_name',
  'account_number',
  'request_type',
  'appointment_date',
  'appointment_time',
  'product',
  'so_number',
  'request_status',
  'rejection_reason',
  'verifier_name',
  'verifier_number',
]

const COLUMN_LABELS = {
  activity_number: 'Activity Number',
  company_name: 'Company Name',
  account_number: 'Account Number',
  request_type: 'Request Type',
  appointment_date: 'Appointment Date',
  appointment_time: 'Appointment Time',
  product: 'Product',
  so_number: 'SO Number',
  request_status: 'Request Status',
  rejection_reason: 'Rejection Reason',
  verifier_name: 'Verifier Name',
  verifier_number: 'Verifier Number',
}

/** Optional CSV columns (not required in file): DSP OM ID, Uploaded By, Uploaded At. */
const OPTIONAL_CSV_COLUMNS = ['dsp_om_id', 'uploaded_by', 'uploaded_at']

/** Columns that must be present in the CSV header (normalized match). */
const REQUIRED_CSV_COLUMNS = [...COLUMNS]

const loading = ref(false)
const loadError = ref(null)
const csvUploadError = ref('')
const data = ref([])
const lastUploadedBatchId = ref(null)
const csvInputRef = ref(null)
const sortBy = ref('activity_number')
const sortOrder = ref('asc')
const advancedVisible = ref(false)
const columnModalVisible = ref(false)
const detailModalVisible = ref(false)
const selectedRecord = ref(null)
const visibleColumns = ref([...COLUMNS])

const showToast = ref(false)
const toastType = ref('success')
const toastMsg  = ref('')
function toast(t, m) { toastType.value = t; toastMsg.value = m; showToast.value = true }

const filters = ref({
  activity_number: '',
  company_name: '',
  account_number: '',
  request_type: '',
  appointment_date_from: '',
  appointment_date_to: '',
  product: '',
  so_number: '',
  request_status: '',
  rejection_reason: '',
  verifier_name: '',
})

const filterOptions = computed(() => {
  const statusSet = new Set()
  data.value.forEach((row) => {
    const v = row.request_status
    if (v != null && String(v).trim() !== '') statusSet.add(String(v).trim())
  })
  return {
    request_status_options: [...statusSet].sort().map((v) => ({ value: v, label: v })),
  }
})

function parseDateForCompare(val) {
  if (val == null || val === '') return null
  const s = String(val).trim()
  const ymd = fromDdMmYyyy(s) || (s.match(/^\d{4}-\d{2}-\d{2}/) ? s.slice(0, 10) : '')
  if (!ymd) return null
  const t = new Date(ymd).getTime()
  return Number.isNaN(t) ? null : t
}

function buildParams() {
  const f = filters.value
  const p = { sort: sortBy.value, order: sortOrder.value }
  if (f.activity_number) p.activity_number = f.activity_number
  if (f.company_name) p.company_name = f.company_name
  if (f.account_number) p.account_number = f.account_number
  if (f.request_type) p.request_type = f.request_type
  if (f.appointment_date_from) p.appointment_date_from = f.appointment_date_from
  if (f.appointment_date_to) p.appointment_date_to = f.appointment_date_to
  if (f.product) p.product = f.product
  if (f.so_number) p.so_number = f.so_number
  if (f.request_status) p.request_status = f.request_status
  if (f.rejection_reason) p.rejection_reason = f.rejection_reason
  if (f.verifier_name) p.verifier_name = f.verifier_name
  return p
}

async function load() {
  loading.value = true
  loadError.value = null
  try {
    const { data: res } = await dspTrackerApi.index(buildParams())
    data.value = res?.data ?? []
    lastUploadedBatchId.value = res?.meta?.last_import_batch_id ?? null
  } catch (e) {
    loadError.value = e?.response?.data?.message || 'Failed to load DSP tracker data.'
    data.value = []
  } finally {
    loading.value = false
  }
}

const filteredData = computed(() => {
  const f = filters.value
  const list = data.value.filter((row) => {
    if (f.activity_number && !String(row.activity_number ?? '').toLowerCase().includes(f.activity_number.toLowerCase())) return false
    if (f.company_name && !String(row.company_name ?? '').toLowerCase().includes(f.company_name.toLowerCase())) return false
    if (f.account_number && !String(row.account_number ?? '').toLowerCase().includes(f.account_number.toLowerCase())) return false
    if (f.request_type && !String(row.request_type ?? '').toLowerCase().includes(f.request_type.toLowerCase())) return false
    if (f.product && !String(row.product ?? '').toLowerCase().includes(f.product.toLowerCase())) return false
    if (f.so_number && !String(row.so_number ?? '').toLowerCase().includes(f.so_number.toLowerCase())) return false
    if (f.request_status && (row.request_status ?? '') !== f.request_status) return false
    if (f.rejection_reason && !String(row.rejection_reason ?? '').toLowerCase().includes(f.rejection_reason.toLowerCase())) return false
    if (f.verifier_name && !String(row.verifier_name ?? '').toLowerCase().includes(f.verifier_name.toLowerCase())) return false
    const rowDate = parseDateForCompare(row.appointment_date)
    if (f.appointment_date_from) {
      const fromTs = parseDateForCompare(f.appointment_date_from) ?? 0
      if (rowDate == null || rowDate < fromTs) return false
    }
    if (f.appointment_date_to) {
      const toTs = parseDateForCompare(f.appointment_date_to)
      if (toTs == null) return false
      const toEnd = new Date(toTs)
      toEnd.setHours(23, 59, 59, 999)
      if (rowDate == null || rowDate > toEnd.getTime()) return false
    }
    return true
  })
  return list
})

const sortedData = computed(() => {
  const list = [...filteredData.value]
  const key = sortBy.value
  const dir = sortOrder.value === 'asc' ? 1 : -1
  list.sort((a, b) => {
    const va = a[key]
    const vb = b[key]
    if (va == null && vb == null) return 0
    if (va == null) return dir
    if (vb == null) return -dir
    if (typeof va === 'string' && typeof vb === 'string') return dir * va.localeCompare(vb)
    if (typeof va === 'number' && typeof vb === 'number') return dir * (va - vb)
    return dir * String(va).localeCompare(String(vb))
  })
  return list
})

const allColumnsForModal = computed(() =>
  COLUMNS.map((key) => ({ key, label: COLUMN_LABELS[key] }))
)

function applyFilters() {
  advancedVisible.value = false
  load()
}

function resetFilters() {
  filters.value = {
    activity_number: '',
    company_name: '',
    account_number: '',
    request_type: '',
    appointment_date_from: '',
    appointment_date_to: '',
    product: '',
    so_number: '',
    request_status: '',
    rejection_reason: '',
    verifier_name: '',
  }
  advancedVisible.value = false
  load()
}

function onColumnSave(columns) {
  visibleColumns.value = columns
  columnModalVisible.value = false
}

function toggleSort(col) {
  if (sortBy.value === col) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortBy.value = col
    sortOrder.value = 'asc'
  }
}

function formatCell(row, col) {
  const val = row[col]
  if (val == null || val === '') return '—'
  return val
}

function onView(row) {
  selectedRecord.value = row
  detailModalVisible.value = true
}
function onEdit(row) {
  // TODO: open edit modal or navigate to edit
}
function closeDetailModal() {
  detailModalVisible.value = false
  selectedRecord.value = null
}
function onDetailEdit(record) {
  detailModalVisible.value = false
  selectedRecord.value = null
  onEdit(record)
}
function onHistory(row) {
  // TODO: open history modal
}

function triggerImport() {
  if (lastUploadedBatchId.value) {
    alert('Before uploading, delete the previous record.')
    return
  }
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
    } else if (inQuotes) {
      cur += c
    } else if (c === ',' || c === '\t') {
      out.push(cur.trim())
      cur = ''
    } else {
      cur += c
    }
  }
  out.push(cur.trim())
  return out
}

function onCsvChange(e) {
  const file = e.target?.files?.[0]
  if (!file) return
  csvUploadError.value = ''

  const norm = (s) => (s || '').toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '')

  if (!file.name.toLowerCase().endsWith('.csv')) {
    csvUploadError.value = 'Please upload a valid CSV file (file must have .csv extension).'
    e.target.value = ''
    return
  }

  const reader = new FileReader()
  reader.onload = async (ev) => {
    const text = ev.target?.result
    if (!text || typeof text !== 'string') {
      csvUploadError.value = 'The file could not be read. Please ensure it is a valid text/CSV file.'
      e.target.value = ''
      return
    }
    const lines = text.split(/\r?\n/).filter((l) => l.trim())
    if (lines.length < 2) {
      csvUploadError.value = 'The file is empty or has no data rows. Please add a header row and at least one data row.'
      e.target.value = ''
      return
    }

    const rawHeaders = parseCsvLine(lines[0])
    const keyMap = {}
    COLUMNS.forEach((col) => {
      const i = rawHeaders.findIndex((h) => norm(h) === col)
      if (i !== -1) keyMap[col] = i
    })
    OPTIONAL_CSV_COLUMNS.forEach((col) => {
      const i = rawHeaders.findIndex((h) => norm(h) === col)
      if (i !== -1) keyMap[col] = i
    })

    const missingColumns = REQUIRED_CSV_COLUMNS.filter((col) => keyMap[col] === undefined)
    if (missingColumns.length > 0) {
      const names = missingColumns.map((c) => COLUMN_LABELS[c] || c).join(', ')
      csvUploadError.value = `The CSV file is missing the following required columns: ${names}. Please add these columns to your file. Expected headers (or similar): ${REQUIRED_CSV_COLUMNS.map((c) => COLUMN_LABELS[c]).join(', ')}.`
      e.target.value = ''
      return
    }

    const newRows = []
    const rowErrors = []
    for (let i = 1; i < lines.length; i++) {
      const lineNum = i + 1
      const cells = parseCsvLine(lines[i])
      const row = {}
      COLUMNS.forEach((col) => {
        const idx = keyMap[col]
        row[col] = idx !== undefined && cells[idx] !== undefined ? String(cells[idx]).trim() : ''
      })
      OPTIONAL_CSV_COLUMNS.forEach((col) => {
        const idx = keyMap[col]
        row[col] = idx !== undefined && cells[idx] !== undefined ? String(cells[idx]).trim() : ''
      })
      const activityNum = (row.activity_number ?? '').trim()
      const companyName = (row.company_name ?? '').trim()
      if (!activityNum && !companyName) {
        rowErrors.push(lineNum)
        continue
      }
      newRows.push(row)
    }

    if (rowErrors.length > 0 && newRows.length === 0) {
      csvUploadError.value = `No valid rows found. Row(s) ${rowErrors.slice(0, 10).join(', ')}${rowErrors.length > 10 ? ` and ${rowErrors.length - 10} more` : ''} are missing both Activity Number and Company Name (at least one is required).`
      e.target.value = ''
      return
    }
    if (rowErrors.length > 0) {
      csvUploadError.value = `${newRows.length} row(s) imported. ${rowErrors.length} row(s) skipped (missing Activity Number and Company Name): row(s) ${rowErrors.slice(0, 5).join(', ')}${rowErrors.length > 5 ? ` and ${rowErrors.length - 5} more` : ''}.`
    }

    loading.value = true
    csvUploadError.value = ''
    try {
      const { data: res } = await dspTrackerApi.import(newRows)
      lastUploadedBatchId.value = res?.batch_id ?? null
      toast('success', 'CSV uploaded successfully.')
      await load()
    } catch (err) {
      csvUploadError.value = err?.response?.data?.message || 'Failed to save uploaded data.'
      toast('error', csvUploadError.value)
    } finally {
      loading.value = false
    }
    if (!rowErrors.length) csvUploadError.value = ''
    e.target.value = ''
  }
  reader.readAsText(file, 'UTF-8')
  e.target.value = ''
}

async function deleteOldFile() {
  if (!lastUploadedBatchId.value) return
  loading.value = true
  csvUploadError.value = ''
  try {
    await dspTrackerApi.deleteBatch(lastUploadedBatchId.value)
    lastUploadedBatchId.value = null
    toast('success', 'Records deleted successfully.')
    await load()
  } catch (err) {
    csvUploadError.value = err?.response?.data?.message || 'Failed to delete records.'
    toast('error', csvUploadError.value)
  } finally {
    loading.value = false
  }
}

onMounted(() => load())
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-white py-6 px-4 sm:px-6">
    <div class="mx-auto max-w-7xl space-y-4">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex flex-wrap items-start gap-3">
          <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-lg bg-[#6BC100] text-white">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
            </svg>
          </div>
          <div>
            <div class="flex flex-wrap items-baseline gap-4">
              <h1 class="text-xl font-bold text-gray-900 leading-tight">DSP Tracker - Status Check</h1>
              <Breadcrumbs class="text-sm text-gray-500" />
            </div>
            <p class="mt-0.5 text-sm text-gray-500">Search and track DSP-related activities and requests.</p>
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <input
            ref="csvInputRef"
            type="file"
            accept=".csv,text/csv"
            class="hidden"
            @change="onCsvChange"
          />
          <button
            type="button"
            class="inline-flex items-center rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
            :disabled="!lastUploadedBatchId"
            @click="deleteOldFile"
          >
            Delete Old File
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
            @click="triggerImport"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            Upload CSV file
          </button>
        </div>
      </div>

      <div v-if="loadError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ loadError }}
      </div>
      <div v-if="csvUploadError" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        {{ csvUploadError }}
      </div>

      <!-- Filters: Request Status, Company Name, Apply/Reset, Advanced Filters, Customize Columns (Activity Number only in Advanced) -->
      <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-end gap-4">
          <div class="min-w-[140px] max-w-[180px]">
            <label class="mb-1 block text-xs font-medium text-gray-600">Request Status</label>
            <select
              v-model="filters.request_status"
              class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              :disabled="loading"
            >
              <option value="">All</option>
              <option v-for="o in filterOptions.request_status_options" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </div>
          <div class="min-w-[140px] max-w-[200px]">
            <label class="mb-1 block text-xs font-medium text-gray-600">Company Name</label>
            <input
              v-model="filters.company_name"
              type="text"
              placeholder="Search company..."
              class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500"
              :disabled="loading"
            />
          </div>
          <div class="flex gap-2">
            <button
              type="button"
              class="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
              :disabled="loading"
              @click="applyFilters"
            >
              Apply Filters
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
          <div class="flex gap-2 ml-auto">
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
        </div>
      </div>

      <AdvancedFilters
        :visible="advancedVisible"
        :filters="filters"
        :loading="loading"
        @apply="applyFilters"
        @reset="resetFilters"
      />

      <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full border-collapse">
          <thead>
            <tr class="border-b border-gray-200 bg-sky-50">
              <th
                v-for="col in visibleColumns"
                :key="col"
                scope="col"
                class="whitespace-nowrap px-4 py-3 text-left text-sm font-semibold text-gray-800"
              >
                <button
                  type="button"
                  class="inline-flex items-center gap-1 font-semibold text-gray-800 hover:text-gray-600"
                  @click="toggleSort(col)"
                >
                  {{ COLUMN_LABELS[col] }}
                  <svg v-if="sortBy === col" class="h-4 w-4" :class="sortOrder === 'asc' ? '' : 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                  </svg>
                </button>
              </th>
              <th scope="col" class="whitespace-nowrap px-4 py-3 text-center text-sm font-semibold text-gray-800 bg-sky-50">
                Action
              </th>
            </tr>
          </thead>
          <tbody class="bg-white">
            <tr v-if="loading" class="border-b border-gray-200">
              <td :colspan="visibleColumns.length + 1" class="px-4 py-12 text-center text-sm text-gray-500">
                <span class="inline-flex items-center gap-2">
                  <svg class="h-5 w-5 animate-spin text-gray-400" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                  </svg>
                  Loading...
                </span>
              </td>
            </tr>
            <tr v-else-if="!sortedData.length" class="border-b border-gray-200">
              <td :colspan="visibleColumns.length + 1" class="px-4 py-12 text-center text-sm text-gray-500">No records found.</td>
            </tr>
            <tr
              v-for="row in sortedData"
              :key="row.id"
              class="border-b border-gray-200 bg-white hover:bg-gray-50/50"
            >
              <td
                v-for="col in visibleColumns"
                :key="col"
                class="whitespace-nowrap px-4 py-3 text-sm text-gray-900"
              >
                {{ formatCell(row, col) }}
              </td>
              <td class="whitespace-nowrap px-4 py-3">
                <div class="inline-flex items-center justify-center gap-1">
                  <button
                    type="button"
                    class="rounded p-1.5 text-blue-600 hover:bg-blue-50"
                    title="View"
                    @click="onView(row)"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </button>
                  <button
                    type="button"
                    class="rounded p-1.5 text-green-600 hover:bg-green-50"
                    title="Edit"
                    @click="onEdit(row)"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                  </button>
                  <button
                    type="button"
                    class="rounded p-1.5 text-amber-600 hover:bg-amber-50"
                    title="History"
                    @click="onHistory(row)"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        <div class="border-t border-gray-200 bg-white px-4 py-2 text-sm text-gray-500">
          Showing {{ sortedData.length }} of {{ data.length }} entries
        </div>
      </div>

      <ColumnCustomizerModal
        :visible="columnModalVisible"
        :all-columns="allColumnsForModal"
        :visible-columns="visibleColumns"
        :default-columns="COLUMNS"
        @update:visible="columnModalVisible = $event"
        @save="onColumnSave"
      />

      <DSPTrackerDetailModal
        :visible="detailModalVisible"
        :record="selectedRecord"
        @close="closeDetailModal"
        @edit="onDetailEdit"
      />
    </div>

    <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="4000" @dismiss="showToast = false" />
  </div>
</template>
