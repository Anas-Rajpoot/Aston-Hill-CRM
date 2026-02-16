<script setup>
/**
 * Lead Reports: KPIs, filters, charts (by status, by category, monthly trend), lead submissions table.
 * Data from /api/reports/lead-stats and /api/lead-submissions.
 */
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/lib/axios'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import { toDdMonYyyyLower } from '@/lib/dateFormat'

const route = useRoute()
const loading = ref(true)
const statsLoading = ref(true)
const tableLoading = ref(false)
const stats = ref({
  total_leads: 0,
  new_submissions: 0,
  resubmissions: 0,
  sla_compliance_pct: 0,
  avg_processing_days: 0,
  by_status: {},
  by_category: [],
  monthly_trend: [],
})
const filterOptions = ref({ categories: [], statuses: [] })
const filters = ref({
  from: '',
  to: '',
  submitted_from: '',
  submitted_to: '',
  service_category_id: '',
  status: '',
  sales_agent_id: '',
  team_leader_id: '',
})
const tableData = ref([])
const tableMeta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 })

const params = computed(() => {
  const p = { page: tableMeta.value.current_page, per_page: tableMeta.value.per_page }
  if (filters.value.from) p.from = filters.value.from
  if (filters.value.to) p.to = filters.value.to
  if (filters.value.submitted_from) p.submitted_from = filters.value.submitted_from
  if (filters.value.submitted_to) p.submitted_to = filters.value.submitted_to
  if (filters.value.service_category_id) p.service_category_id = filters.value.service_category_id
  if (filters.value.status) p.status = filters.value.status
  if (filters.value.sales_agent_id) p.sales_agent_id = filters.value.sales_agent_id
  if (filters.value.team_leader_id) p.team_leader_id = filters.value.team_leader_id
  p.columns = ['id', 'submitted_at', 'company_name', 'category', 'status', 'sla_timer', 'sales_agent', 'executive']
  return p
})

async function loadFilterOptions() {
  try {
    const { data } = await api.get('/lead-submissions/filters')
    filterOptions.value.categories = data.categories ?? []
    filterOptions.value.statuses = data.statuses ?? []
  } catch {
    filterOptions.value = { categories: [], statuses: [] }
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
    if (filters.value.service_category_id) params.service_category_id = filters.value.service_category_id
    if (filters.value.status) params.status = filters.value.status
    if (filters.value.sales_agent_id) params.sales_agent_id = filters.value.sales_agent_id
    if (filters.value.team_leader_id) params.team_leader_id = filters.value.team_leader_id
    const { data } = await api.get('/reports/lead-stats', { params })
    stats.value = data
  } catch {
    stats.value = { total_leads: 0, new_submissions: 0, resubmissions: 0, sla_compliance_pct: 0, avg_processing_days: 0, by_status: {}, by_category: [], monthly_trend: [] }
  } finally {
    statsLoading.value = false
  }
}

async function loadTable() {
  tableLoading.value = true
  try {
    const { data } = await api.get('/lead-submissions', { params: params.value })
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
    service_category_id: '',
    status: '',
    sales_agent_id: '',
    team_leader_id: '',
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

function slaBadgeClass(slaTimer) {
  if (!slaTimer) return 'bg-gray-100 text-gray-700'
  if (String(slaTimer).startsWith('Overdue')) return 'bg-red-100 text-red-800'
  if (String(slaTimer).includes('left') && String(slaTimer).includes('h')) return 'bg-amber-100 text-amber-800'
  return 'bg-green-100 text-green-800'
}

function slaLabel(slaTimer) {
  if (!slaTimer) return '—'
  if (String(slaTimer).startsWith('Overdue')) return 'Breached'
  if (String(slaTimer).includes('h left')) return 'Approaching'
  return 'On Time'
}

function statusBadgeClass(status) {
  const s = (status || '').toLowerCase()
  if (s === 'approved' || s === 'completed') return 'bg-green-100 text-green-800'
  if (s === 'rejected') return 'bg-red-100 text-red-800'
  if (s === 'submitted' || s === 'pending_for_ata' || s === 'pending_for_finance' || s === 'pending_from_sales') return 'bg-blue-100 text-blue-800'
  return 'bg-gray-100 text-gray-700'
}

const statusLabels = {
  draft: 'Draft',
  submitted: 'Submitted',
  approved: 'Approved',
  rejected: 'Rejected',
  pending_for_ata: 'Pending for ATA',
  pending_for_finance: 'Pending for Finance',
  pending_from_sales: 'Pending from Sales',
  unassigned: 'Unassigned',
}

function statusLabel(status) {
  return statusLabels[status] || status || '—'
}

const byStatusList = computed(() => {
  const by = stats.value.by_status || {}
  return Object.entries(by).map(([name, count]) => ({ name: statusLabel(name), count })).sort((a, b) => b.count - a.count)
})

const maxStatusCount = computed(() => Math.max(1, ...byStatusList.value.map((s) => s.count)))
const maxCategoryCount = computed(() => Math.max(1, ...(stats.value.by_category || []).map((c) => c.count)))
const maxTrendCount = computed(() => Math.max(1, ...(stats.value.monthly_trend || []).map((t) => t.count)))

function printReport() {
  window.print()
}

function exportExcel() {
  const q = new URLSearchParams({ ...params.value, per_page: 5000 })
  window.location.href = `/api/lead-submissions?${q.toString()}`
}

watch(
  () => tableMeta.value.current_page,
  () => loadTable()
)

onMounted(async () => {
  loading.value = true
  await loadFilterOptions()
  await Promise.all([loadStats(), loadTable()])
  loading.value = false
})
</script>

<template>
  <div class="space-y-6 bg-white -mx-4 -my-5 min-h-full px-6 py-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <Breadcrumbs />
        <h1 class="text-2xl font-bold text-gray-900 mt-1">Lead Reports</h1>
        <p class="text-sm text-gray-500 mt-0.5">Analyze Lead Submissions, Resubmissions, and conversion metrics by service category and sales team.</p>
      </div>
      <div class="flex gap-2">
        <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="printReport">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2h-2m-4-1v8m0 0l-4-4m4 4l4-4" /></svg>
          Print
        </button>
        <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700" @click="exportExcel">
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
            <p class="text-sm text-gray-300">Total Leads</p>
            <p class="text-2xl font-bold mt-1">{{ statsLoading ? '…' : stats.total_leads.toLocaleString() }}</p>
            <p class="text-xs text-emerald-400 mt-1">↑ trend</p>
          </div>
          <div class="rounded-full bg-emerald-500/20 p-2"><svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg></div>
        </div>
      </div>
      <div class="rounded-xl bg-gray-800 text-white p-5 shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-300">New Submissions</p>
            <p class="text-2xl font-bold mt-1">{{ statsLoading ? '…' : stats.new_submissions.toLocaleString() }}</p>
            <p class="text-xs text-emerald-400 mt-1">↑ trend</p>
          </div>
          <div class="rounded-full bg-emerald-500/20 p-2"><svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg></div>
        </div>
      </div>
      <div class="rounded-xl bg-gray-800 text-white p-5 shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-300">Resubmissions</p>
            <p class="text-2xl font-bold mt-1">{{ statsLoading ? '…' : stats.resubmissions.toLocaleString() }}</p>
            <p class="text-xs text-emerald-400 mt-1">↑ trend</p>
          </div>
          <div class="rounded-full bg-emerald-500/20 p-2"><svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg></div>
        </div>
      </div>
      <div class="rounded-xl bg-gray-800 text-white p-5 shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-300">SLA Compliance</p>
            <p class="text-2xl font-bold mt-1">{{ statsLoading ? '…' : stats.sla_compliance_pct }}%</p>
            <p class="text-xs text-emerald-400 mt-1">↑ trend</p>
          </div>
          <div class="rounded-full bg-emerald-500/20 p-2"><svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg></div>
        </div>
      </div>
      <div class="rounded-xl bg-gray-800 text-white p-5 shadow">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-300">Avg Processing Time</p>
            <p class="text-2xl font-bold mt-1">{{ statsLoading ? '…' : stats.avg_processing_days }} days</p>
            <p class="text-xs text-emerald-400 mt-1">↑ trend</p>
          </div>
          <div class="rounded-full bg-emerald-500/20 p-2"><svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg></div>
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
          <label class="block text-xs font-medium text-gray-500 mb-1">Service category</label>
          <select v-model="filters.service_category_id" class="rounded border border-gray-300 px-3 py-2 text-sm w-48">
            <option value="">All categories</option>
            <option v-for="c in filterOptions.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
          <select v-model="filters.status" class="rounded border border-gray-300 px-3 py-2 text-sm w-40">
            <option value="">All status</option>
            <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
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
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Leads by status</h3>
        <div class="space-y-3">
          <div v-for="s in byStatusList" :key="s.name" class="flex items-center gap-3">
            <span class="w-24 text-sm text-gray-600 truncate">{{ s.name }}</span>
            <div class="flex-1 h-6 bg-gray-100 rounded overflow-hidden">
              <div class="h-full bg-emerald-500 rounded" :style="{ width: (100 * s.count / maxStatusCount) + '%' }" />
            </div>
            <span class="text-sm font-medium text-gray-900 w-10">{{ s.count }}</span>
          </div>
          <p v-if="!byStatusList.length && !statsLoading" class="text-sm text-gray-500">No data</p>
        </div>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Service category distribution</h3>
        <div class="space-y-3">
          <div v-for="c in (stats.by_category || [])" :key="c.name" class="flex items-center gap-3">
            <span class="w-28 text-sm text-gray-600 truncate">{{ c.name }}</span>
            <div class="flex-1 h-6 bg-gray-100 rounded overflow-hidden">
              <div class="h-full bg-amber-500 rounded" :style="{ width: (100 * c.count / maxCategoryCount) + '%' }" />
            </div>
            <span class="text-sm font-medium text-gray-900 w-10">{{ c.count }}</span>
          </div>
          <p v-if="!(stats.by_category || []).length && !statsLoading" class="text-sm text-gray-500">No data</p>
        </div>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Monthly trend</h3>
        <div class="space-y-3">
          <div v-for="t in (stats.monthly_trend || []).slice(-6)" :key="t.label" class="flex items-center gap-3">
            <span class="w-20 text-sm text-gray-600">{{ t.label }}</span>
            <div class="flex-1 h-6 bg-gray-100 rounded overflow-hidden">
              <div class="h-full bg-blue-500 rounded" :style="{ width: (100 * t.count / maxTrendCount) + '%' }" />
            </div>
            <span class="text-sm font-medium text-gray-900 w-10">{{ t.count }}</span>
          </div>
          <p v-if="!(stats.monthly_trend || []).length && !statsLoading" class="text-sm text-gray-500">No data</p>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
      <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
        <h2 class="text-base font-semibold text-gray-900">Lead submissions details</h2>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Submission date</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Company name</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Service category</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Status</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">SLA status</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Sales agent</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">BO executive</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="tableLoading">
              <td colspan="7" class="px-4 py-8 text-center text-gray-500">Loading…</td>
            </tr>
            <tr v-else-if="!tableData.length">
              <td colspan="7" class="px-4 py-8 text-center text-gray-500">No records</td>
            </tr>
            <tr v-else v-for="row in tableData" :key="row.id" class="hover:bg-gray-50">
              <td class="px-4 py-2 text-sm text-gray-700">{{ formatDate(row.submitted_at) }}</td>
              <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ row.company_name || '—' }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ row.category || '—' }}</td>
              <td class="px-4 py-2">
                <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', statusBadgeClass(row.status)]">{{ statusLabel(row.status) }}</span>
              </td>
              <td class="px-4 py-2">
                <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', slaBadgeClass(row.sla_timer)]">{{ slaLabel(row.sla_timer) }}</span>
              </td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ row.sales_agent || '—' }}</td>
              <td class="px-4 py-2 text-sm text-gray-700">{{ row.executive || '—' }}</td>
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
