<script setup>
/**
 * Field Operations Reports: KPIs, filters, charts (status, agent workload, completion rate), field submissions table.
 * Data from /api/reports/field-stats and /api/field-submissions.
 */
import { ref, computed, onMounted } from 'vue'
import api from '@/lib/axios'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import { toDdMonYyyyLower } from '@/lib/dateFormat'

const statsLoading = ref(true)
const tableLoading = ref(false)
const stats = ref({
  total_requests: 0,
  meetings_completed: 0,
  cancellations: 0,
  follow_ups: 0,
  sla_breaches: 0,
  by_status: [],
  by_agent_workload: [],
  completion_rate_by_month: [],
})
const filterOptions = ref({ statuses: [], emirates: [] })
const filters = ref({
  from: '',
  to: '',
  submitted_from: '',
  submitted_to: '',
  status: '',
  emirates: '',
  field_executive_id: '',
})
const tableData = ref([])
const tableMeta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 })

const params = computed(() => {
  const p = { page: tableMeta.value.current_page, per_page: tableMeta.value.per_page }
  if (filters.value.from) p.from = filters.value.from
  if (filters.value.to) p.to = filters.value.to
  if (filters.value.submitted_from) p.submitted_from = filters.value.submitted_from
  if (filters.value.submitted_to) p.submitted_to = filters.value.submitted_to
  if (filters.value.status) p.status = filters.value.status
  if (filters.value.emirates) p.emirates = filters.value.emirates
  const fid = parseInt(filters.value.field_executive_id, 10)
  if (!Number.isNaN(fid) && fid > 0) p.field_executive_id = fid
  p.columns = ['id', 'company_name', 'field_agent', 'field_status', 'meeting_date', 'emirates', 'sla_status']
  return p
})

async function loadFilterOptions() {
  try {
    const { data } = await api.get('/field-submissions/filters')
    filterOptions.value.statuses = data.statuses ?? []
    filterOptions.value.emirates = Array.isArray(data.emirates) ? data.emirates.map((e) => ({ value: e, label: e })) : []
  } catch {
    filterOptions.value = { statuses: [], emirates: [] }
  }
}

async function loadStats() {
  statsLoading.value = true
  try {
    const params = {}
    if (filters.value.from) params.from = filters.value.from
    if (filters.value.to) params.to = filters.value.to
    if (filters.value.submitted_from) params.submitted_from = filters.value.submitted_from
    if (filters.value.submitted_to) params.submitted_to = filters.value.submitted_to
    if (filters.value.status) params.status = filters.value.status
    if (filters.value.emirates) params.emirates = filters.value.emirates
    const fid = parseInt(filters.value.field_executive_id, 10)
    if (!Number.isNaN(fid) && fid > 0) params.field_executive_id = fid
    const { data } = await api.get('/reports/field-stats', { params })
    stats.value = data
  } catch {
    stats.value = { total_requests: 0, meetings_completed: 0, cancellations: 0, follow_ups: 0, sla_breaches: 0, by_status: [], by_agent_workload: [], completion_rate_by_month: [] }
  } finally {
    statsLoading.value = false
  }
}

async function loadTable() {
  tableLoading.value = true
  try {
    const { data } = await api.get('/field-submissions', { params: params.value })
    tableData.value = data.data ?? []
    tableMeta.value = data.meta ?? { current_page: 1, last_page: 1, per_page: 15, total: 0 }
  } catch {
    tableData.value = []
  } finally {
    tableLoading.value = false
  }
}

function resetFilters() {
  filters.value = {
    from: '',
    to: '',
    submitted_from: '',
    submitted_to: '',
    status: '',
    emirates: '',
    field_executive_id: '',
  }
}

function applyFilters() {
  tableMeta.value.current_page = 1
  loadStats()
  loadTable()
}

function formatDate(d) {
  if (!d) return '—'
  const str = typeof d === 'string' ? d.trim().slice(0, 10) : ''
  return toDdMonYyyyLower(str) || '—'
}

function formatMeetingDate(d) {
  if (!d) return '—'
  const str = typeof d === 'string' ? d.trim() : ''
  if (str.length >= 10) return toDdMonYyyyLower(str.slice(0, 10)) || '—'
  return '—'
}

function statusBadgeClass(status) {
  const s = (status || '').toLowerCase()
  if (s.includes('completed') || s === 'visited') return 'bg-green-100 text-green-800'
  if (s === 'cancelled') return 'bg-red-100 text-red-800'
  if (s === 'scheduled' || s.includes('scheduled')) return 'bg-blue-100 text-blue-800'
  if (s === 'rescheduled' || s.includes('follow')) return 'bg-amber-100 text-amber-800'
  return 'bg-gray-100 text-gray-700'
}

function slaBadgeClass(slaStatus) {
  const s = (slaStatus || '').toLowerCase()
  if (s.includes('breach')) return 'bg-red-100 text-red-800'
  if (s.includes('approach')) return 'bg-amber-100 text-amber-800'
  return 'bg-green-100 text-green-800'
}

const maxAgentCount = computed(() => Math.max(1, ...(stats.value.by_agent_workload || []).map((a) => a.count)))
const maxStatusCount = computed(() => Math.max(1, ...(stats.value.by_status || []).map((s) => s.count)))

function printReport() {
  window.print()
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

onMounted(async () => {
  await loadFilterOptions()
  await Promise.all([loadStats(), loadTable()])
})
</script>

<template>
  <div class="space-y-6 bg-white -mx-4 -my-5 min-h-full px-6 py-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <Breadcrumbs />
        <h1 class="text-2xl font-bold text-gray-900 mt-1">Field Operations Reports</h1>
        <p class="text-sm text-gray-500 mt-0.5">Track field meetings, agent workload, and completion rates.</p>
      </div>
      <div class="flex gap-2">
        <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="printReport">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2h-2m-4-1v8m0 0l-4-4m4 4l4-4" /></svg>
          Print
        </button>
        <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
          Export to Excel
        </button>
      </div>
    </div>

    <!-- KPI cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
      <div class="rounded-xl bg-gray-800 text-white p-5 shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-300">Total Field Requests</p>
            <p class="text-2xl font-bold mt-1">{{ statsLoading ? '…' : stats.total_requests.toLocaleString() }}</p>
            <p class="text-xs text-emerald-400 mt-1">↑ trend</p>
          </div>
          <div class="rounded-full bg-emerald-500/20 p-2"><svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /></svg></div>
        </div>
      </div>
      <div class="rounded-xl bg-gray-800 text-white p-5 shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-300">Meetings Completed</p>
            <p class="text-2xl font-bold mt-1">{{ statsLoading ? '…' : stats.meetings_completed.toLocaleString() }}</p>
            <p class="text-xs text-emerald-400 mt-1">↑ trend</p>
          </div>
          <div class="rounded-full bg-emerald-500/20 p-2"><svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg></div>
        </div>
      </div>
      <div class="rounded-xl bg-gray-800 text-white p-5 shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-300">Cancellations</p>
            <p class="text-2xl font-bold mt-1">{{ statsLoading ? '…' : stats.cancellations.toLocaleString() }}</p>
            <p class="text-xs text-emerald-400 mt-1">↑ trend</p>
          </div>
          <div class="rounded-full bg-emerald-500/20 p-2"><svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></div>
        </div>
      </div>
      <div class="rounded-xl bg-gray-800 text-white p-5 shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-300">Follow-ups</p>
            <p class="text-2xl font-bold mt-1">{{ statsLoading ? '…' : stats.follow_ups.toLocaleString() }}</p>
            <p class="text-xs text-emerald-400 mt-1">↑ trend</p>
          </div>
          <div class="rounded-full bg-emerald-500/20 p-2"><svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg></div>
        </div>
      </div>
      <div class="rounded-xl bg-gray-800 text-white p-5 shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-300">SLA Breaches</p>
            <p class="text-2xl font-bold mt-1">{{ statsLoading ? '…' : stats.sla_breaches.toLocaleString() }}</p>
            <p class="text-xs text-emerald-400 mt-1">↑ trend</p>
          </div>
          <div class="rounded-full bg-emerald-500/20 p-2"><svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg></div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
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
          <label class="block text-xs font-medium text-gray-500 mb-1">Field agent</label>
          <input v-model="filters.field_executive_id" type="text" placeholder="Search agent..." class="rounded border border-gray-300 px-3 py-2 text-sm w-40" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
          <select v-model="filters.status" class="rounded border border-gray-300 px-3 py-2 text-sm w-40">
            <option value="">All status</option>
            <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Emirates</label>
          <select v-model="filters.emirates" class="rounded border border-gray-300 px-3 py-2 text-sm w-40">
            <option value="">All emirates</option>
            <option v-for="e in filterOptions.emirates" :key="e.value" :value="e.value">{{ e.label }}</option>
          </select>
        </div>
        <div class="ml-auto flex gap-2">
          <button type="button" class="text-sm text-gray-500 hover:text-gray-700" @click="resetFilters">Reset all</button>
          <button type="button" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700" @click="applyFilters">Apply</button>
        </div>
      </div>
    </div>

    <!-- Charts row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Status distribution</h3>
        <div class="space-y-3">
          <div v-for="s in (stats.by_status || [])" :key="s.name" class="flex items-center gap-3">
            <span class="w-28 text-sm text-gray-600 truncate">{{ s.name }}</span>
            <div class="flex-1 h-6 bg-gray-100 rounded overflow-hidden">
              <div class="h-full bg-amber-500 rounded" :style="{ width: (100 * s.count / maxStatusCount) + '%' }" />
            </div>
            <span class="text-sm font-medium text-gray-900 w-10">{{ s.count }}</span>
          </div>
          <p v-if="!(stats.by_status || []).length && !statsLoading" class="text-sm text-gray-500">No data</p>
        </div>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Field agent workload</h3>
        <div class="space-y-3">
          <div v-for="a in (stats.by_agent_workload || [])" :key="a.name" class="flex items-center gap-3">
            <span class="w-32 text-sm text-gray-600 truncate">{{ a.name }}</span>
            <div class="flex-1 h-6 bg-gray-100 rounded overflow-hidden">
              <div class="h-full bg-blue-500 rounded" :style="{ width: (100 * a.count / maxAgentCount) + '%' }" />
            </div>
            <span class="text-sm font-medium text-gray-900 w-10">{{ a.count }}</span>
          </div>
          <p v-if="!(stats.by_agent_workload || []).length && !statsLoading" class="text-sm text-gray-500">No data</p>
        </div>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Meeting completion rate</h3>
        <div class="space-y-3">
          <div v-for="m in (stats.completion_rate_by_month || [])" :key="m.label" class="flex items-center gap-3">
            <span class="w-20 text-sm text-gray-600">{{ m.label }}</span>
            <div class="flex-1 h-6 bg-gray-100 rounded overflow-hidden">
              <div class="h-full bg-emerald-500 rounded" :style="{ width: (m.pct || 0) + '%' }" />
            </div>
            <span class="text-sm font-medium text-gray-900 w-12">{{ m.pct }}%</span>
          </div>
          <p v-if="!(stats.completion_rate_by_month || []).length && !statsLoading" class="text-sm text-gray-500">No data</p>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
      <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
        <h2 class="text-base font-semibold text-gray-900">Field submissions details</h2>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Company name</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Field agent</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Status</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Meeting date</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Emirates</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">SLA status</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="tableLoading">
              <td colspan="6" class="px-4 py-8 text-center text-gray-500">Loading…</td>
            </tr>
            <tr v-else-if="!tableData.length">
              <td colspan="6" class="px-4 py-8 text-center text-gray-500">No records</td>
            </tr>
            <tr v-else v-for="row in tableData" :key="row.id" class="hover:bg-gray-50">
              <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ row.company_name || '—' }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ row.field_agent || '—' }}</td>
              <td class="px-4 py-2">
                <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', statusBadgeClass(row.field_status)]">{{ row.field_status || '—' }}</span>
              </td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ formatMeetingDate(row.meeting_date || row.target_date) }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ row.emirates || '—' }}</td>
              <td class="px-4 py-2">
                <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', slaBadgeClass(row.sla_status)]">{{ row.sla_status || '—' }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="tableMeta.last_page > 1" class="px-4 py-2 border-t border-gray-200 flex justify-between items-center">
        <p class="text-sm text-gray-500">Page {{ tableMeta.current_page }} of {{ tableMeta.last_page }} ({{ tableMeta.total }} total)</p>
        <div class="flex gap-2">
          <button type="button" class="rounded border border-gray-300 px-3 py-1 text-sm disabled:opacity-50" :disabled="tableMeta.current_page <= 1" @click="prevPage">Previous</button>
          <button type="button" class="rounded border border-gray-300 px-3 py-1 text-sm disabled:opacity-50" :disabled="tableMeta.current_page >= tableMeta.last_page" @click="nextPage">Next</button>
        </div>
      </div>
    </div>
  </div>
</template>
