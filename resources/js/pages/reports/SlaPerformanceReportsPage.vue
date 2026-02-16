<script setup>
/**
 * SLA Performance Report – comprehensive dashboard with KPIs, department breakdown,
 * category compliance bars, priority stats, recent breaches (sortable + customizable columns),
 * monthly trend, and dynamic insights. Super admins see all; others see own submissions.
 */
import { ref, computed, watch, onMounted } from 'vue'
import api from '@/lib/axios'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import Pagination from '@/components/Pagination.vue'
import ColumnCustomizerModal from '@/components/lead-submissions/ColumnCustomizerModal.vue'

/* ───── Loading & filter state ───── */
const loading = ref(true)
const filtersVisible = ref(false)
const filters = ref({ from: '', to: '' })
const exportLoading = ref(false)

/* ───── Data state ───── */
const kpis = ref({ total_requests: 0, on_time_pct: 0, on_time_count: 0, at_risk_pct: 0, at_risk_count: 0, breached_pct: 0, breached_count: 0 })
const departments = ref([])
const categories = ref([])
const priority = ref([])
const monthlyTrend = ref([])
const allBreaches = ref([])
const insights = ref([])

/* ───── Breaches table state ───── */
const breachSort = ref('submitted_date')
const breachOrder = ref('desc')
const breachPage = ref(1)
const breachPerPage = ref(10)
const columnModalVisible = ref(false)

const ALL_BREACH_COLUMNS = [
  { key: 'request_id', label: 'Request ID' },
  { key: 'category', label: 'Category' },
  { key: 'department', label: 'Department' },
  { key: 'priority', label: 'Priority' },
  { key: 'submitted_date', label: 'Submitted Date' },
  { key: 'sla_target', label: 'SLA Target' },
  { key: 'actual_time', label: 'Actual Time' },
  { key: 'breach_duration', label: 'Breach Duration' },
  { key: 'assigned_to', label: 'Assigned To' },
]

const visibleBreachColumns = ref([
  'request_id', 'category', 'department', 'priority',
  'submitted_date', 'sla_target', 'actual_time', 'breach_duration', 'assigned_to',
])

const activeBreachColumns = computed(() =>
  visibleBreachColumns.value.map((key) => ALL_BREACH_COLUMNS.find((c) => c.key === key)).filter(Boolean)
)

/* ───── Sorted & paginated breaches ───── */
const sortedBreaches = computed(() => {
  const data = [...allBreaches.value]
  const key = breachSort.value
  const dir = breachOrder.value === 'asc' ? 1 : -1
  data.sort((a, b) => {
    const va = a[key] ?? ''
    const vb = b[key] ?? ''
    if (va < vb) return -1 * dir
    if (va > vb) return 1 * dir
    return 0
  })
  return data
})

const breachTotal = computed(() => sortedBreaches.value.length)
const breachLastPage = computed(() => Math.max(1, Math.ceil(breachTotal.value / breachPerPage.value)))
const paginatedBreaches = computed(() => {
  const start = (breachPage.value - 1) * breachPerPage.value
  return sortedBreaches.value.slice(start, start + breachPerPage.value)
})

/* ───── Computed helpers ───── */
const maxCategoryTotal = computed(() => Math.max(1, ...categories.value.map((c) => c.total)))

/* ───── Data loading ───── */
async function loadData() {
  loading.value = true
  try {
    const params = {}
    if (filters.value.from) params.from = filters.value.from
    if (filters.value.to) params.to = filters.value.to
    const { data } = await api.get('/reports/sla-performance', { params })
    kpis.value = data.kpis ?? kpis.value
    departments.value = data.departments ?? []
    categories.value = data.categories ?? []
    priority.value = data.priority ?? []
    monthlyTrend.value = data.monthly_trend ?? []
    allBreaches.value = data.recent_breaches ?? []
    insights.value = data.insights ?? []
  } catch {
    /* silent */
  } finally {
    loading.value = false
  }
}

function applyFilters() {
  breachPage.value = 1
  loadData()
}

function resetFilters() {
  filters.value = { from: '', to: '' }
  breachPage.value = 1
  loadData()
}

/* ───── Breach table sort ───── */
function onBreachSort(colKey) {
  if (breachSort.value === colKey) {
    breachOrder.value = breachOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    breachSort.value = colKey
    breachOrder.value = 'asc'
  }
  breachPage.value = 1
}

function onBreachPageChange(page) {
  breachPage.value = page
}

function onBreachPerPageChange(e) {
  breachPerPage.value = Number(e.target.value)
  breachPage.value = 1
}

function onSaveBreachColumns(cols) {
  visibleBreachColumns.value = cols
}

/* ───── Format helpers ───── */
function formatDate(d) {
  if (!d) return '—'
  const dt = new Date(d)
  const day = String(dt.getDate()).padStart(2, '0')
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  return `${day}-${months[dt.getMonth()]}-${dt.getFullYear()}`
}

function priorityBadgeClass(p) {
  if (p === 'High') return 'bg-red-100 text-red-800'
  if (p === 'Medium') return 'bg-amber-100 text-amber-800'
  return 'bg-green-100 text-green-800'
}

function priorityDotClass(p) {
  if (p === 'High') return 'bg-red-500'
  if (p === 'Medium') return 'bg-amber-500'
  return 'bg-green-500'
}

function complianceBarColor(pct) {
  if (pct >= 90) return 'bg-emerald-500'
  if (pct >= 80) return 'bg-amber-500'
  return 'bg-red-500'
}

function insightIcon(type) {
  return type === 'positive' ? 'check' : type === 'warning' ? 'alert' : 'info'
}

function insightBg(type) {
  return type === 'positive' ? 'bg-emerald-50 border-emerald-200' : type === 'warning' ? 'bg-red-50 border-red-200' : 'bg-blue-50 border-blue-200'
}

function insightIconColor(type) {
  return type === 'positive' ? 'text-emerald-600' : type === 'warning' ? 'text-red-600' : 'text-blue-600'
}

function cellValue(row, key) {
  if (key === 'submitted_date') return formatDate(row[key])
  return row[key] ?? '—'
}

/* ───── Print & Export ───── */
function printReport() { window.print() }

function escapeCsv(val) {
  if (val == null) return ''
  const s = String(val)
  if (s.includes(',') || s.includes('"') || s.includes('\n')) return '"' + s.replace(/"/g, '""') + '"'
  return s
}

function exportReport() {
  exportLoading.value = true
  try {
    const cols = visibleBreachColumns.value
    const headers = cols.map((c) => ALL_BREACH_COLUMNS.find((x) => x.key === c)?.label ?? c)
    const csvRows = [headers.map(escapeCsv).join(',')]
    for (const row of sortedBreaches.value) {
      csvRows.push(cols.map((col) => escapeCsv(cellValue(row, col))).join(','))
    }
    const blob = new Blob([csvRows.join('\r\n')], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `sla-performance-report-${new Date().toISOString().slice(0, 10)}.csv`
    a.click()
    URL.revokeObjectURL(url)
  } finally {
    exportLoading.value = false
  }
}

/* ───── Init ───── */
onMounted(() => loadData())
</script>

<template>
  <div class="space-y-6 bg-gray-50 -mx-4 -my-5 min-h-full px-6 py-6">
    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">SLA Performance Report</h1>
        <Breadcrumbs class="mt-1" />
        <p class="text-sm text-gray-500 mt-1">Monitor and analyze Service Level Agreement compliance across all operations.</p>
      </div>
    </div>

    <!-- Filter bar -->
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
      <div class="flex items-center gap-3">
        <button
          type="button"
          class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
          @click="filtersVisible = !filtersVisible"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
          {{ filtersVisible ? 'Hide Filters' : 'Show Filters' }}
        </button>
        <template v-if="filtersVisible">
          <div class="flex items-center gap-2">
            <input v-model="filters.from" type="date" class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-40" />
            <span class="text-gray-400">to</span>
            <input v-model="filters.to" type="date" class="rounded-lg border border-gray-300 px-3 py-2 text-sm w-40" />
          </div>
          <button type="button" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700" @click="applyFilters">Apply</button>
          <button type="button" class="text-sm text-gray-500 hover:text-gray-700" @click="resetFilters">Reset</button>
        </template>
      </div>
      <div class="flex gap-2">
        <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="printReport">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2h-2m-4-1v8m0 0l-4-4m4 4l4-4" /></svg>
          Print Report
        </button>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-70"
          :disabled="exportLoading"
          @click="exportReport"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
          Export Report
        </button>
      </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Total Requests -->
      <div class="rounded-xl bg-white border border-gray-200 p-5 shadow-sm">
        <div class="flex items-center gap-3">
          <div class="rounded-lg bg-emerald-100 p-2.5">
            <svg class="h-6 w-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
          </div>
          <div>
            <p class="text-xs text-gray-500">Total Requests</p>
            <p class="text-2xl font-bold text-gray-900">{{ loading ? '…' : kpis.total_requests.toLocaleString() }}</p>
            <p class="text-xs text-gray-400">This period</p>
          </div>
        </div>
      </div>
      <!-- On Time -->
      <div class="rounded-xl bg-white border border-gray-200 p-5 shadow-sm">
        <div class="flex items-center gap-3">
          <div class="rounded-lg bg-green-100 p-2.5">
            <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          </div>
          <div>
            <p class="text-xs text-gray-500">On Time</p>
            <p class="text-2xl font-bold text-gray-900">{{ loading ? '…' : kpis.on_time_pct + '%' }}</p>
            <p class="text-xs text-gray-400">{{ kpis.on_time_count.toLocaleString() }} requests</p>
          </div>
        </div>
      </div>
      <!-- At Risk -->
      <div class="rounded-xl bg-white border border-gray-200 p-5 shadow-sm">
        <div class="flex items-center gap-3">
          <div class="rounded-lg bg-amber-100 p-2.5">
            <svg class="h-6 w-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          </div>
          <div>
            <p class="text-xs text-gray-500">At Risk</p>
            <p class="text-2xl font-bold text-gray-900">{{ loading ? '…' : kpis.at_risk_pct + '%' }}</p>
            <p class="text-xs text-gray-400">{{ kpis.at_risk_count.toLocaleString() }} requests</p>
          </div>
        </div>
      </div>
      <!-- Breached -->
      <div class="rounded-xl bg-white border border-gray-200 p-5 shadow-sm">
        <div class="flex items-center gap-3">
          <div class="rounded-lg bg-red-100 p-2.5">
            <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
          </div>
          <div>
            <p class="text-xs text-gray-500">Breached</p>
            <p class="text-2xl font-bold text-gray-900">{{ loading ? '…' : kpis.breached_pct + '%' }}</p>
            <p class="text-xs text-gray-400">{{ kpis.breached_count.toLocaleString() }} requests</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Department-wise SLA Performance -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900">Department-wise SLA Performance</h2>
        <p class="text-sm text-gray-500 mt-0.5">Compliance rates by department</p>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
              <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Total Requests</th>
              <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">On Time</th>
              <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">At Risk</th>
              <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Breached</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-40">Compliance Rate</th>
              <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Response Time</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="loading">
              <td colspan="7" class="px-5 py-8 text-center text-gray-400">Loading…</td>
            </tr>
            <tr v-else-if="!departments.length">
              <td colspan="7" class="px-5 py-8 text-center text-gray-400">No data available</td>
            </tr>
            <tr v-else v-for="dept in departments" :key="dept.name" class="hover:bg-gray-50">
              <td class="px-5 py-3">
                <div class="flex items-center gap-3">
                  <div class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-100">
                    <svg class="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                  </div>
                  <div>
                    <p class="text-sm font-medium text-gray-900">{{ dept.name }}</p>
                    <p class="text-xs text-gray-500">{{ dept.subtitle }}</p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3 text-center text-sm font-medium text-gray-900">{{ dept.total_requests }}</td>
              <td class="px-4 py-3 text-center">
                <span class="inline-flex items-center justify-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800">{{ dept.on_time }}</span>
              </td>
              <td class="px-4 py-3 text-center">
                <span class="inline-flex items-center justify-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">{{ dept.at_risk }}</span>
              </td>
              <td class="px-4 py-3 text-center">
                <span class="inline-flex items-center justify-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-800">{{ dept.breached }}</span>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <div class="flex-1 h-2.5 rounded-full bg-gray-200 overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-500" :class="complianceBarColor(dept.compliance_pct)" :style="{ width: dept.compliance_pct + '%' }" />
                  </div>
                  <span class="text-sm font-medium text-gray-900 w-12 text-right">{{ dept.compliance_pct }}%</span>
                </div>
              </td>
              <td class="px-4 py-3 text-center text-sm text-gray-700">{{ dept.avg_response }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Category + Priority row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- SLA Compliance by Category -->
      <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">SLA Compliance by Category</h3>
        <div v-if="loading" class="py-8 text-center text-gray-400 text-sm">Loading…</div>
        <div v-else class="space-y-4">
          <div v-for="cat in categories" :key="cat.name">
            <div class="flex items-center justify-between mb-1">
              <span class="text-sm font-medium text-gray-700">{{ cat.name }}</span>
              <span class="text-sm font-semibold text-gray-900">{{ cat.compliance_pct }}%</span>
            </div>
            <div class="h-2.5 rounded-full bg-gray-200 overflow-hidden">
              <div class="h-full rounded-full transition-all duration-500" :class="complianceBarColor(cat.compliance_pct)" :style="{ width: cat.compliance_pct + '%' }" />
            </div>
            <p class="text-xs text-gray-400 mt-0.5">{{ cat.total }} total requests &middot; {{ cat.breached }} breached</p>
          </div>
          <p v-if="!categories.length" class="text-sm text-gray-400 text-center py-4">No data available</p>
        </div>
      </div>

      <!-- Priority-wise Performance -->
      <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Priority-wise Performance</h3>
        <div v-if="loading" class="py-8 text-center text-gray-400 text-sm">Loading…</div>
        <div v-else class="space-y-5">
          <div v-for="p in priority" :key="p.level" class="rounded-lg border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center gap-2">
                <span class="inline-block h-2.5 w-2.5 rounded-full" :class="priorityDotClass(p.level)" />
                <span class="text-sm font-semibold" :class="p.level === 'High' ? 'text-red-700' : p.level === 'Medium' ? 'text-amber-700' : 'text-green-700'">{{ p.level }}</span>
                <span class="text-xs text-gray-500">{{ p.total }} requests</span>
              </div>
              <span class="text-sm font-bold text-gray-900">{{ p.compliance_pct }}%</span>
            </div>
            <div class="grid grid-cols-3 gap-3 text-center">
              <div>
                <p class="text-xs text-gray-500">On Time</p>
                <p class="text-lg font-bold text-green-600">{{ p.on_time }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500">At Risk</p>
                <p class="text-lg font-bold text-amber-600">{{ p.at_risk }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500">Breached</p>
                <p class="text-lg font-bold text-red-600">{{ p.breached }}</p>
              </div>
            </div>
          </div>
          <p v-if="!priority.length" class="text-sm text-gray-400 text-center py-4">No data available</p>
        </div>
      </div>
    </div>

    <!-- Recent SLA Breaches -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
      <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-gray-200">
        <div>
          <h2 class="text-lg font-semibold text-gray-900">Recent SLA Breaches</h2>
          <p class="text-sm text-gray-500 mt-0.5">Requests that exceeded SLA timelines</p>
        </div>
        <button
          type="button"
          class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
          @click="columnModalVisible = true"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" /></svg>
          Customize Columns
        </button>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th
                v-for="col in activeBreachColumns"
                :key="col.key"
                class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer select-none hover:bg-gray-100 transition-colors"
                @click="onBreachSort(col.key)"
              >
                <div class="flex items-center gap-1">
                  <span>{{ col.label }}</span>
                  <span v-if="breachSort === col.key" class="text-green-600">
                    <svg v-if="breachOrder === 'asc'" class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" /></svg>
                    <svg v-else class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                  </span>
                  <span v-else class="text-gray-300">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" /></svg>
                  </span>
                </div>
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="loading">
              <td :colspan="activeBreachColumns.length" class="px-4 py-12 text-center text-gray-400">Loading…</td>
            </tr>
            <tr v-else-if="!paginatedBreaches.length">
              <td :colspan="activeBreachColumns.length" class="px-4 py-12 text-center text-gray-400">No breaches found</td>
            </tr>
            <tr v-else v-for="(row, idx) in paginatedBreaches" :key="idx" class="hover:bg-gray-50 transition-colors">
              <td v-for="col in activeBreachColumns" :key="col.key" class="px-4 py-2.5 text-sm whitespace-nowrap">
                <template v-if="col.key === 'priority'">
                  <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', priorityBadgeClass(row.priority)]">{{ row.priority }}</span>
                </template>
                <template v-else-if="col.key === 'breach_duration'">
                  <span class="text-red-600 font-medium">{{ row.breach_duration }}</span>
                </template>
                <template v-else-if="col.key === 'request_id'">
                  <span class="font-medium text-gray-900">{{ row.request_id }}</span>
                </template>
                <template v-else>
                  <span class="text-gray-700">{{ cellValue(row, col.key) }}</span>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Pagination -->
      <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 bg-white px-4 py-3">
        <div class="flex items-center gap-3">
          <p class="text-sm text-gray-600">
            Showing {{ breachTotal ? ((breachPage - 1) * breachPerPage) + 1 : 0 }}
            to {{ Math.min(breachPage * breachPerPage, breachTotal) }}
            of {{ breachTotal }} entries
          </p>
          <div class="flex items-center gap-1.5 text-sm text-gray-600">
            <span>Number of pages</span>
            <select
              :value="breachPerPage"
              class="rounded border border-gray-300 px-2 py-1 text-sm"
              @change="onBreachPerPageChange"
            >
              <option :value="10">10</option>
              <option :value="20">20</option>
              <option :value="50">50</option>
            </select>
          </div>
        </div>
        <Pagination
          v-if="breachLastPage > 1"
          :meta="{
            prev_page_url: breachPage > 1 ? '#' : null,
            next_page_url: breachPage < breachLastPage ? '#' : null,
            current_page: breachPage,
            last_page: breachLastPage,
          }"
          @change="onBreachPageChange"
        />
      </div>
    </div>

    <!-- SLA Compliance Trend -->
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
      <h2 class="text-lg font-semibold text-gray-900 mb-1">SLA Compliance Trend</h2>
      <p class="text-sm text-gray-500 mb-4">Monthly compliance rate over time</p>
      <div v-if="loading" class="py-8 text-center text-gray-400 text-sm">Loading…</div>
      <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-4">
        <div v-for="m in monthlyTrend" :key="m.label" class="text-center">
          <p class="text-xs text-gray-500 mb-1">{{ m.label }}</p>
          <p class="text-2xl font-bold" :class="m.compliance_pct >= 85 ? 'text-gray-900' : 'text-amber-600'">{{ m.compliance_pct }}%</p>
          <div class="mt-1.5 h-2 rounded-full bg-gray-200 overflow-hidden mx-2">
            <div class="h-full rounded-full transition-all duration-500" :class="complianceBarColor(m.compliance_pct)" :style="{ width: m.compliance_pct + '%' }" />
          </div>
          <p class="text-xs text-gray-400 mt-1">{{ m.total }} requests</p>
        </div>
        <p v-if="!monthlyTrend.length" class="col-span-full text-sm text-gray-400 text-center py-4">No data available</p>
      </div>
    </div>

    <!-- Key Insights & Recommendations -->
    <div v-if="insights.length" class="rounded-xl border border-gray-200 bg-gray-800 p-5 shadow-sm">
      <h2 class="text-lg font-semibold text-white mb-4">Key Insights &amp; Recommendations</h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div
          v-for="(insight, idx) in insights"
          :key="idx"
          class="rounded-lg border p-4"
          :class="insightBg(insight.type)"
        >
          <div class="flex items-start gap-3">
            <div class="mt-0.5 flex-shrink-0">
              <!-- Check icon -->
              <svg v-if="insight.type === 'positive'" class="h-5 w-5" :class="insightIconColor(insight.type)" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              <!-- Alert icon -->
              <svg v-else-if="insight.type === 'warning'" class="h-5 w-5" :class="insightIconColor(insight.type)" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
              <!-- Info icon -->
              <svg v-else class="h-5 w-5" :class="insightIconColor(insight.type)" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <div>
              <p class="text-sm font-semibold text-gray-900">{{ insight.title }}</p>
              <p class="text-sm text-gray-600 mt-0.5">{{ insight.text }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Column Customizer Modal for Breaches Table -->
    <ColumnCustomizerModal
      :visible="columnModalVisible"
      :all-columns="ALL_BREACH_COLUMNS"
      :visible-columns="visibleBreachColumns"
      :default-columns="['request_id', 'category', 'department', 'priority', 'submitted_date', 'sla_target', 'actual_time', 'breach_duration', 'assigned_to']"
      @update:visible="columnModalVisible = $event"
      @save="onSaveBreachColumns"
    />
  </div>
</template>
