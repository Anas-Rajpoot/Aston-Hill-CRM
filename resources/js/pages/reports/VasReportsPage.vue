<script setup>
/**
 * VAS Reports: search, KPIs (total, pending, completed today, SLA %), filters, table from vas_request_submissions.
 */
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/axios'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import { useTablePageSize } from '@/composables/useTablePageSize'

const router = useRouter()
const { perPage, perPageOptions, perPageReady, setPerPage } = useTablePageSize('vas-reports')
const statsLoading = ref(true)
const tableLoading = ref(false)
const stats = ref({
  total_vas_requests: 0,
  pending_requests: 0,
  completed_today: 0,
  sla_compliance_pct: 0,
})
const filterOptions = ref({ request_types: [], statuses: [] })
const filtersVisible = ref(false)
const filters = ref({
  from: '',
  to: '',
  submitted_from: '',
  submitted_to: '',
  status: '',
  request_type: '',
})
const searchQ = ref('')
const tableData = ref([])
const tableMeta = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 })
const SLA_DAYS = 7

const params = computed(() => {
  const p = {
    page: tableMeta.value.current_page,
    per_page: perPage.value,
    sort: 'submitted_at',
    order: 'desc',
    columns: ['id', 'submitted_at', 'request_type', 'company_name', 'account_number', 'status', 'executive', 'sales_agent', 'approved_at'],
  }
  if (searchQ.value?.trim()) p.q = searchQ.value.trim()
  if (filters.value.from) p.from = filters.value.from
  if (filters.value.to) p.to = filters.value.to
  if (filters.value.submitted_from) p.submitted_from = filters.value.submitted_from
  if (filters.value.submitted_to) p.submitted_to = filters.value.submitted_to
  if (filters.value.status) p.status = filters.value.status
  if (filters.value.request_type) p.request_type = filters.value.request_type
  return p
})

async function loadFilterOptions() {
  try {
    const { data } = await api.get('/vas-requests/filters')
    filterOptions.value.request_types = data.request_types ?? []
    filterOptions.value.statuses = data.statuses ?? []
  } catch {
    filterOptions.value = { request_types: [], statuses: [] }
  }
}

async function loadStats() {
  statsLoading.value = true
  try {
    const params = {}
    if (searchQ.value?.trim()) params.q = searchQ.value.trim()
    if (filters.value.from) params.from = filters.value.from
    if (filters.value.to) params.to = filters.value.to
    if (filters.value.submitted_from) params.submitted_from = filters.value.submitted_from
    if (filters.value.submitted_to) params.submitted_to = filters.value.submitted_to
    if (filters.value.status) params.status = filters.value.status
    if (filters.value.request_type) params.request_type = filters.value.request_type
    const { data } = await api.get('/reports/vas-stats', { params })
    stats.value = data
  } catch {
    stats.value = { total_vas_requests: 0, pending_requests: 0, completed_today: 0, sla_compliance_pct: 0 }
  } finally {
    statsLoading.value = false
  }
}

async function loadTable() {
  tableLoading.value = true
  try {
    const { data } = await api.get('/vas-requests', { params: params.value })
    tableData.value = data.data ?? []
    tableMeta.value = data.meta ?? { current_page: 1, last_page: 1, per_page: 20, total: 0 }
  } catch {
    tableData.value = []
  } finally {
    tableLoading.value = false
  }
}

function applySearch() {
  tableMeta.value.current_page = 1
  loadStats()
  loadTable()
}

function resetFilters() {
  filters.value = { from: '', to: '', submitted_from: '', submitted_to: '', status: '', request_type: '' }
  tableMeta.value.current_page = 1
  loadStats()
  loadTable()
}

function applyFilters() {
  tableMeta.value.current_page = 1
  loadStats()
  loadTable()
}

function statusLabel(status) {
  const s = (status || '').toLowerCase()
  if (s === 'approved') return 'Completed'
  if (s === 'rejected') return 'Rejected'
  if (s === 'submitted') return 'Submitted'
  if (s === 'draft') return 'Pending'
  return status || '—'
}

function statusBadgeClass(status) {
  const s = (status || '').toLowerCase()
  if (s === 'approved') return 'bg-green-100 text-green-800'
  if (s === 'rejected') return 'bg-red-100 text-red-800'
  if (s === 'submitted') return 'bg-blue-100 text-blue-800'
  return 'bg-gray-100 text-gray-700'
}

function duStatusLabel(status) {
  const s = (status || '').toLowerCase()
  if (s === 'approved') return 'Approved'
  if (s === 'rejected') return 'Rejected'
  return 'Pending'
}

function completionDate(row) {
  return row.approved_at || '—'
}

function slaStatus(row) {
  if ((row.status || '').toLowerCase() !== 'approved' || !row.submitted_at || !row.approved_at) return null
  const sub = parseDate(row.submitted_at)
  const app = parseDate(row.approved_at)
  if (!sub || !app) return null
  const days = Math.floor((app - sub) / (24 * 60 * 60 * 1000))
  return days <= SLA_DAYS ? 'Within SLA' : 'Breached SLA'
}

function parseDate(str) {
  if (!str) return null
  const part = String(str).trim().split(/\s+/)[0] || str
  const match = part.match(/(\d{1,2})[\/\-](\w{3})[\/\-](\d{4})/)
  if (!match) return null
  const months = { Jan: 0, Feb: 1, Mar: 2, Apr: 3, May: 4, Jun: 5, Jul: 6, Aug: 7, Sep: 8, Oct: 9, Nov: 10, Dec: 11 }
  const m = months[match[2]]
  if (m === undefined) return null
  return new Date(parseInt(match[3], 10), m, parseInt(match[1], 10)).getTime()
}

function slaBadgeClass(sla) {
  if (!sla) return 'bg-gray-100 text-gray-700'
  return sla === 'Breached SLA' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'
}

function exportReport() {
  const q = new URLSearchParams({ ...params.value, per_page: 5000 })
  window.location.href = `/api/vas-requests?${q.toString()}`
}

function prevPage() {
  if (tableMeta.value.current_page > 1) {
    tableMeta.value.current_page--
    loadTable()
  }
}

function nextPage() {
  if (tableMeta.value.current_page < tableMeta.value.last_page) {
    tableMeta.value.current_page++
    loadTable()
  }
}

function goToDetail(id) {
  router.push(`/vas-requests/${id}`)
}

const showingCount = computed(() => tableData.value.length)
const totalCount = computed(() => tableMeta.value.total)
const fromEntry = computed(() => (tableMeta.value.current_page - 1) * perPage.value + 1)
const toEntry = computed(() => Math.min(tableMeta.value.current_page * perPage.value, tableMeta.value.total))

const paginationPages = computed(() => {
  const last = tableMeta.value.last_page
  if (last <= 5) return Array.from({ length: last }, (_, i) => i + 1)
  const cur = tableMeta.value.current_page
  const pages = []
  for (let i = Math.max(1, cur - 2); i <= Math.min(last, cur + 2); i++) pages.push(i)
  return pages
})

function onPerPageChange(e) {
  setPerPage(e.target.value)
  tableMeta.value.current_page = 1
  loadTable()
}

onMounted(async () => {
  await loadFilterOptions()
  await Promise.all([loadStats(), loadTable()])
})
</script>

<template>
  <div class="space-y-6">
    <div>
      <Breadcrumbs />
      <h1 class="text-2xl font-bold text-gray-900 mt-1">VAS Reports</h1>
      <p class="text-sm text-gray-500 mt-0.5">Generate and analyze VAS request performance and SLA compliance.</p>
    </div>

    <!-- Search -->
    <div class="relative">
      <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
      </span>
      <input
        v-model="searchQ"
        type="text"
        placeholder="Search by company name, activity number, or account number..."
        class="w-full rounded-xl border border-gray-300 py-2.5 pl-10 pr-4 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
        @keydown.enter="applySearch"
      />
      <button
        type="button"
        class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
        @click="applySearch"
      >
        Search
      </button>
    </div>

    <!-- KPI cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm flex items-center gap-4">
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-600">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
        </div>
        <div>
          <p class="text-sm font-medium text-gray-500">Total VAS Requests</p>
          <p class="text-2xl font-bold text-gray-900">{{ statsLoading ? '…' : stats.total_vas_requests.toLocaleString() }}</p>
        </div>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm flex items-center gap-4">
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-500">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        </div>
        <div>
          <p class="text-sm font-medium text-gray-500">Pending Requests</p>
          <p class="text-2xl font-bold text-gray-900">{{ statsLoading ? '…' : stats.pending_requests.toLocaleString() }}</p>
        </div>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm flex items-center gap-4">
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-600">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
        </div>
        <div>
          <p class="text-sm font-medium text-gray-500">Completed Today</p>
          <p class="text-2xl font-bold text-gray-900">{{ statsLoading ? '…' : stats.completed_today.toLocaleString() }}</p>
        </div>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm flex items-center gap-4">
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-green-100 text-green-600">
          <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h2v16H4V4z" /></svg>
        </div>
        <div>
          <p class="text-sm font-medium text-gray-500">SLA Compliance</p>
          <p class="text-2xl font-bold text-gray-900">{{ statsLoading ? '…' : stats.sla_compliance_pct }}%</p>
        </div>
      </div>
    </div>

    <!-- Filters + Export -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <button
        type="button"
        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
        @click="filtersVisible = !filtersVisible"
      >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
        {{ filtersVisible ? 'Hide Filters' : 'Show Filters' }}
      </button>
      <button
        type="button"
        class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
        @click="exportReport"
      >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
        Export Report
      </button>
    </div>

    <div v-if="filtersVisible" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
      <div class="flex flex-wrap items-end gap-4">
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Date from</label>
          <input v-model="filters.from" type="date" class="rounded border border-gray-300 px-3 py-2 text-sm w-40" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Date to</label>
          <input v-model="filters.to" type="date" class="rounded border border-gray-300 px-3 py-2 text-sm w-40" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
          <select v-model="filters.status" class="rounded border border-gray-300 px-3 py-2 text-sm w-40">
            <option value="">All status</option>
            <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Request type</label>
          <select v-model="filters.request_type" class="rounded border border-gray-300 px-3 py-2 text-sm w-48">
            <option value="">All types</option>
            <option v-for="t in filterOptions.request_types" :key="t.value" :value="t.value">{{ t.label }}</option>
          </select>
        </div>
        <button type="button" class="text-sm text-gray-500 hover:text-gray-700" @click="resetFilters">Reset all</button>
        <button type="button" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700" @click="applyFilters">Apply</button>
      </div>
    </div>

    <!-- Table -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
      <div class="px-4 py-3 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
        <h2 class="text-base font-semibold text-gray-900">Showing {{ showingCount }} of {{ totalCount }} reports</h2>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Submission date</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Request type</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Company name</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Account number</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Status</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">DU status</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">BO executive</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Sales agent</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Completion date</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">SLA status</th>
              <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="tableLoading">
              <td colspan="11" class="px-4 py-8 text-center text-gray-500">Loading…</td>
            </tr>
            <tr v-else-if="!tableData.length">
              <td colspan="11" class="px-4 py-8 text-center text-gray-500">No records</td>
            </tr>
            <tr v-else v-for="row in tableData" :key="row.id" class="hover:bg-gray-50">
              <td class="px-4 py-2 text-sm text-gray-700">{{ row.submitted_at || '—' }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ row.request_type || '—' }}</td>
              <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ row.company_name || '—' }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ row.account_number || '—' }}</td>
              <td class="px-4 py-2">
                <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', statusBadgeClass(row.status)]">{{ statusLabel(row.status) }}</span>
              </td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ duStatusLabel(row.status) }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ row.executive || '—' }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ row.sales_agent || '—' }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ completionDate(row) }}</td>
              <td class="px-4 py-2">
                <span v-if="slaStatus(row)" :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', slaBadgeClass(slaStatus(row))]">{{ slaStatus(row) }}</span>
                <span v-else class="text-sm text-gray-400">—</span>
              </td>
              <td class="px-4 py-2 text-right">
                <div class="relative inline-block">
                  <button
                    type="button"
                    class="p-1 rounded hover:bg-gray-200 text-gray-500"
                    aria-label="Actions"
                    @click="goToDetail(row.id)"
                  >
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" /></svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="px-4 py-3 border-t border-gray-200 flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-gray-500">
          Showing {{ fromEntry }} to {{ toEntry }} of {{ totalCount }} entries
        </p>
        <div class="flex items-center gap-4">
          <div class="flex items-center gap-2">
            <label class="text-sm text-gray-500">Per page</label>
            <select
              :value="perPage"
              class="rounded border border-gray-300 px-2 py-1 text-sm"
              @change="onPerPageChange"
            >
              <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
            </select>
          </div>
          <div class="flex gap-1">
            <button type="button" class="rounded border border-gray-300 px-3 py-1 text-sm disabled:opacity-50" :disabled="tableMeta.current_page <= 1" @click="prevPage">Previous</button>
            <button
              v-for="p in paginationPages"
              :key="p"
              type="button"
              :class="['rounded px-3 py-1 text-sm', tableMeta.current_page === p ? 'bg-indigo-600 text-white' : 'border border-gray-300 hover:bg-gray-50']"
              @click="tableMeta.current_page = p; loadTable()"
            >
              {{ p }}
            </button>
            <button type="button" class="rounded border border-gray-300 px-3 py-1 text-sm disabled:opacity-50" :disabled="tableMeta.current_page >= tableMeta.last_page" @click="nextPage">Next</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
