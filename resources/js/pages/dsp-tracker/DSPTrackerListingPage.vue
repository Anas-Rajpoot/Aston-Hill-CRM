<script setup>
/**
 * DSP Tracker listing – sortable table, same design as reference:
 * Light blue-grey header (sky-50), white rows, column order: Activity Number, Company Name, Account Number,
 * Request Type, Appointment Date, Appointment Time, Product, SO Number, Request Status, Rejection Reason, Verifier Name, Action.
 */
import { ref, computed } from 'vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'

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
}

const loading = ref(false)
const data = ref([
  { id: 1, activity_number: 'DSP-2024-001', company_name: 'Emirates Tech Solutions LLC', account_number: 'Naeem', request_type: 'Ali', appointment_date: 'Anas', appointment_time: 'new', product: 'Fixed', so_number: 'Internet', request_status: 'Internet', rejection_reason: 'Down town dubai', verifier_name: 'Business Internet' },
  { id: 2, activity_number: 'DSP-2024-001', company_name: 'Emirates Tech Solutions LLC', account_number: 'Naeem', request_type: 'Ali', appointment_date: 'Anas', appointment_time: 'new', product: 'Fixed', so_number: 'Internet', request_status: 'Internet', rejection_reason: 'Down town dubai', verifier_name: 'Business Internet' },
  { id: 3, activity_number: 'DSP-2024-001', company_name: 'Emirates Tech Solutions LLC', account_number: 'Naeem', request_type: 'Ali', appointment_date: 'Anas', appointment_time: 'new', product: 'Fixed', so_number: 'Internet', request_status: 'Internet', rejection_reason: 'Down town dubai', verifier_name: 'Business Internet' },
])
const nextId = ref(4)
const lastUploadedIds = ref([])
const csvInputRef = ref(null)
const sortBy = ref('activity_number')
const sortOrder = ref('asc')

const sortedData = computed(() => {
  const list = [...data.value]
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
  // TODO: open view modal or navigate to detail
}
function onEdit(row) {
  // TODO: open edit modal or navigate to edit
}
function onHistory(row) {
  // TODO: open history modal
}

function triggerImport() {
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
  const reader = new FileReader()
  reader.onload = (ev) => {
    const text = ev.target?.result
    if (!text || typeof text !== 'string') return
    const lines = text.split(/\r?\n/).filter((l) => l.trim())
    if (lines.length < 2) return
    const rawHeaders = parseCsvLine(lines[0])
    const norm = (s) => (s || '').toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '')
    const keyMap = {}
    COLUMNS.forEach((col) => {
      const i = rawHeaders.findIndex((h) => norm(h) === col)
      if (i !== -1) keyMap[col] = i
    })
    const newRows = []
    for (let i = 1; i < lines.length; i++) {
      const cells = parseCsvLine(lines[i])
      const row = { id: nextId.value++ }
      COLUMNS.forEach((col) => {
        const idx = keyMap[col]
        row[col] = idx !== undefined && cells[idx] !== undefined ? cells[idx] : ''
      })
      newRows.push(row)
    }
    data.value = [...data.value, ...newRows]
    lastUploadedIds.value = newRows.map((r) => r.id)
  }
  reader.readAsText(file, 'UTF-8')
  e.target.value = ''
}

function deleteOldFile() {
  if (!lastUploadedIds.value.length) return
  const ids = new Set(lastUploadedIds.value)
  data.value = data.value.filter((row) => !ids.has(row.id))
  lastUploadedIds.value = []
}
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
            <h1 class="text-xl font-bold text-gray-900 leading-tight">DSP Tracker - Status Check</h1>
            <p class="mt-0.5 text-sm text-gray-500">Search and track DSP-related activities and requests.</p>
            <Breadcrumbs class="mt-1" />
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
            :disabled="!lastUploadedIds.length"
            @click="deleteOldFile"
          >
            Delete Old File
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded bg-[#6BC100] px-4 py-2 text-sm font-medium text-white hover:bg-[#5da800]"
            @click="triggerImport"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            Import
          </button>
        </div>
      </div>

      <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full border-collapse">
          <thead>
            <tr class="border-b border-gray-200 bg-sky-50">
              <th
                v-for="col in COLUMNS"
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
              <td :colspan="COLUMNS.length + 1" class="px-4 py-12 text-center text-sm text-gray-500">
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
              <td :colspan="COLUMNS.length + 1" class="px-4 py-12 text-center text-sm text-gray-500">No records found.</td>
            </tr>
            <tr
              v-for="row in sortedData"
              :key="row.id"
              class="border-b border-gray-200 bg-white hover:bg-gray-50/50"
            >
              <td
                v-for="col in COLUMNS"
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
    </div>
  </div>
</template>
