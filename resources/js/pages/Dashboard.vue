<script setup>
/**
 * Dashboard — KPI cards, form summary table, recent activity, filters.
 * Green theme matching sidebar. Responsive grid layout.
 */
import { ref, computed, onMounted, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/lib/axios'
import SkeletonBox from '@/components/skeletons/SkeletonBox.vue'

const auth = useAuthStore()
const loading = ref(true)
const refreshing = ref(false)
const kpis = ref({})
const recentActivity = ref([])
const formSummary = ref(null)
const slaStats = ref(null)

// ── Filters ───────────────────────────────────────────
const filterOptions = ref({ teams: [], csrs: [] })
const dateFrom = ref('')
const dateTo = ref('')
const teamId = ref('')
const csrId = ref('')

function applyData(data) {
  if (!data) return
  kpis.value = data.kpis ?? {}
  recentActivity.value = data.recent_activity ?? []
  formSummary.value = data.form_summary ?? null
}

async function loadFilters() {
  try {
    const { data } = await api.get('/dashboard/filters')
    filterOptions.value = data
  } catch { /* silent */ }
}

async function fetchStats() {
  const params = {}
  if (dateFrom.value) params.date_from = dateFrom.value
  if (dateTo.value) params.date_to = dateTo.value
  if (teamId.value) params.team_id = teamId.value
  if (csrId.value) params.csr_id = csrId.value

  const { data } = await api.get('/dashboard/stats', { params })
  applyData(data.data)
}

async function initialLoad() {
  loading.value = true
  try {
    await Promise.all([fetchStats(), loadFilters(), fetchSlaStats()])
  } catch { /* silent */ }
  finally { loading.value = false }
}

async function fetchSlaStats() {
  try {
    const { data } = await api.get('/reports/sla-performance')
    slaStats.value = data.data ?? data
  } catch { slaStats.value = null }
}

async function doRefresh() {
  if (refreshing.value) return
  refreshing.value = true
  try { await fetchStats() }
  catch { /* silent */ }
  finally { refreshing.value = false }
}

function clearFilters() {
  dateFrom.value = ''
  dateTo.value = ''
  teamId.value = ''
  csrId.value = ''
}

// Auto-refresh when filters change
watch([dateFrom, dateTo, teamId, csrId], () => {
  doRefresh()
}, { flush: 'post' })

onMounted(initialLoad)

// ── Helpers ───────────────────────────────────────────
function formatAed(val) {
  const n = Number(val) || 0
  return 'AED ' + n.toLocaleString('en-AE', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

// ── KPI Cards ─────────────────────────────────────────
const cards = computed(() => [
  { label: 'Total Clients',  value: kpis.value.total_clients ?? 0,  icon: 'clients',  link: '/clients' },
  { label: 'Active Deals',   value: kpis.value.active_deals ?? 0,   icon: 'deals',    link: '/lead-submissions' },
  { label: 'Total Leads',    value: kpis.value.total_leads ?? 0,    icon: 'leads',    link: '/lead-submissions', sub: `${kpis.value.leads_today ?? 0} today` },
  { label: 'Teams',          value: kpis.value.total_teams ?? 0,    icon: 'teams',    link: '/teams' },
  { label: 'Employees',      value: kpis.value.total_employees ?? 0, icon: 'employees', link: '/employees' },
  { label: 'Target MRC',     value: formatAed(kpis.value.total_target_mrc ?? 0), icon: 'target', link: '/users', sub: 'Current Month', isFormatted: true },
])

const secondaryCards = computed(() => [
  { label: 'Field Submissions', value: kpis.value.field_submissions ?? 0, icon: 'field',   sub: `${kpis.value.field_today ?? 0} today` },
  { label: 'Support Tickets',   value: kpis.value.support_tickets ?? 0,   icon: 'support', sub: `${kpis.value.support_open ?? 0} open` },
  { label: 'VAS Requests',      value: kpis.value.vas_requests ?? 0,      icon: 'vas',     sub: `${kpis.value.vas_pending ?? 0} pending` },
  { label: 'Special Requests',  value: kpis.value.special_requests ?? 0, icon: 'special',  sub: '' },
  { label: 'Active Users',      value: kpis.value.active_users ?? 0,      icon: 'users',   sub: 'last 7 days' },
])

const cardIcons = {
  clients:   'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
  deals:     'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
  leads:     'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
  teams:     'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
  employees: 'M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0',
  field:     'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
  support:   'M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
  vas:       'M13 10V3L4 14h7v7l9-11h-7z',
  special:   'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z',
  users:     'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
  target:    'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
}

// ── Form Summary Table ────────────────────────────────
const summaryFormTypes = computed(() => {
  if (!formSummary.value) return []
  return Object.keys(formSummary.value.form_types || {})
})

const summaryStatuses = computed(() => formSummary.value?.statuses ?? [])

function getSummaryCell(formType, status) {
  const rows = formSummary.value?.form_types?.[formType] ?? []
  const matching = rows.filter(r => r.status === status)
  const qty = matching.reduce((s, r) => s + r.qty, 0)
  const mrc = matching.reduce((s, r) => s + r.mrc_total, 0)
  return { qty, mrc }
}

function getFormTypeTotal(formType) {
  const rows = formSummary.value?.form_types?.[formType] ?? []
  return {
    qty: rows.reduce((s, r) => s + r.qty, 0),
    mrc: rows.reduce((s, r) => s + r.mrc_total, 0),
  }
}

function statusBadgeClass(status) {
  const s = (status || '').toLowerCase()
  if (['approved', 'completed', 'closed'].includes(s)) return 'bg-brand-primary-light text-brand-primary-hover'
  if (['rejected'].includes(s)) return 'bg-red-100 text-red-800'
  if (['pending', 'open', 'draft', 'unassigned'].includes(s) || s.startsWith('pending')) return 'bg-amber-100 text-amber-800'
  if (['submitted', 'submitted_under_process'].includes(s)) return 'bg-brand-primary-light text-brand-primary-hover'
  return 'bg-gray-100 text-gray-700'
}

// ── Activity helpers ──────────────────────────────────
function activityColor(type) {
  const m = { lead: 'bg-brand-primary-light text-brand-primary-hover', field: 'bg-brand-primary-light text-brand-primary', support: 'bg-amber-100 text-amber-700', vas: 'bg-brand-primary-light text-brand-primary' }
  return m[type] || 'bg-gray-100 text-gray-600'
}

function timeAgo(iso) {
  if (!iso) return '—'
  const diff = Date.now() - new Date(iso).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return 'Just now'
  if (mins < 60) return `${mins}m ago`
  const hrs = Math.floor(mins / 60)
  if (hrs < 24) return `${hrs}h ago`
  return `${Math.floor(hrs / 24)}d ago`
}

function formatMrc(n) {
  return Number(n).toLocaleString('en-AE', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}
</script>

<template>
  <div class="bg-brand-bg -mx-4 -my-5 min-h-full px-4 sm:px-6 py-6 space-y-6">

    <!-- ═══ Header ═══ -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
        <p class="text-sm text-gray-500 mt-0.5">Welcome back, {{ auth.user?.name ?? 'User' }}</p>
      </div>
      <button
        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-brand-primary text-white text-sm font-medium hover:bg-brand-primary-hover transition shadow-sm"
        :disabled="refreshing"
        @click="doRefresh"
      >
        <svg class="w-4 h-4" :class="refreshing && 'animate-spin'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        Refresh
      </button>
    </div>

    <!-- ═══ Filters Bar ═══ -->
    <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
      <div class="flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[140px]">
          <label class="block text-xs font-medium text-gray-600 mb-1">From Date</label>
          <input type="date" v-model="dateFrom" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand-primary focus:ring-brand-primary" />
        </div>
        <div class="flex-1 min-w-[140px]">
          <label class="block text-xs font-medium text-gray-600 mb-1">To Date</label>
          <input type="date" v-model="dateTo" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand-primary focus:ring-brand-primary" />
        </div>
        <div class="flex-1 min-w-[160px]">
          <label class="block text-xs font-medium text-gray-600 mb-1">Team</label>
          <select v-model="teamId" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand-primary focus:ring-brand-primary">
            <option value="">All Teams</option>
            <option v-for="t in filterOptions.teams" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
        </div>
        <div class="flex-1 min-w-[160px]">
          <label class="block text-xs font-medium text-gray-600 mb-1">CSR / Agent</label>
          <select v-model="csrId" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand-primary focus:ring-brand-primary">
            <option value="">All CSRs</option>
            <option v-for="c in filterOptions.csrs" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <button
          @click="clearFilters"
          class="px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100 border border-gray-300 transition"
        >Clear</button>
      </div>
    </div>

    <!-- ═══ Primary KPI Cards ═══ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
      <template v-if="loading">
        <div v-for="i in 6" :key="'sk' + i" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
          <SkeletonBox class="h-4 w-20 mb-3" /><SkeletonBox class="h-8 w-16 mb-1" /><SkeletonBox class="h-3 w-24" />
        </div>
      </template>
      <template v-else>
        <router-link
          v-for="card in cards"
          :key="card.label"
          :to="card.link || '/'"
          class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm hover:shadow-md hover:border-brand-primary-muted transition-all cursor-pointer"
        >
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ card.label }}</span>
            <div class="w-9 h-9 rounded-lg bg-brand-primary-light flex items-center justify-center group-hover:bg-brand-primary-muted/40 transition">
              <svg class="w-5 h-5 text-brand-primary" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="cardIcons[card.icon]" />
              </svg>
            </div>
          </div>
          <p class="text-3xl font-bold text-gray-900">{{ card.isFormatted ? card.value : card.value.toLocaleString() }}</p>
          <p v-if="card.sub" class="text-xs text-gray-500 mt-1">{{ card.sub }}</p>
        </router-link>
      </template>
    </div>

    <!-- ═══ Secondary KPI Cards ═══ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
      <template v-if="!loading">
        <div
          v-for="card in secondaryCards"
          :key="card.label"
          class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
        >
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-brand-primary-light flex items-center justify-center flex-shrink-0">
              <svg class="w-4 h-4 text-brand-primary" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="cardIcons[card.icon]" />
              </svg>
            </div>
            <div class="min-w-0">
              <p class="text-lg font-bold text-gray-900">{{ card.value.toLocaleString() }}</p>
              <p class="text-xs text-gray-500 truncate">{{ card.label }}</p>
            </div>
          </div>
          <p v-if="card.sub" class="text-[11px] text-gray-400 mt-2">{{ card.sub }}</p>
        </div>
      </template>
    </div>

    <!-- ═══ SLA & Revenue Summary Strip ═══ -->
    <div v-if="!loading && slaStats" class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <div class="rounded-xl border border-green-200 bg-green-50 p-4">
        <p class="text-xs font-semibold text-green-700 uppercase tracking-wide">SLA On-Time</p>
        <p class="text-2xl font-bold text-green-800 mt-1">{{ slaStats.kpis?.on_time ?? 0 }}</p>
        <p class="text-xs text-green-600 mt-0.5">
          {{ slaStats.kpis?.total ? Math.round((slaStats.kpis.on_time / slaStats.kpis.total) * 100) : 0 }}% compliance
        </p>
      </div>
      <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
        <p class="text-xs font-semibold text-amber-700 uppercase tracking-wide">SLA At Risk</p>
        <p class="text-2xl font-bold text-amber-800 mt-1">{{ slaStats.kpis?.at_risk ?? 0 }}</p>
        <p class="text-xs text-amber-600 mt-0.5">nearing deadline</p>
      </div>
      <div class="rounded-xl border border-red-200 bg-red-50 p-4">
        <p class="text-xs font-semibold text-red-700 uppercase tracking-wide">SLA Breached</p>
        <p class="text-2xl font-bold text-red-800 mt-1">{{ slaStats.kpis?.breached ?? 0 }}</p>
        <p class="text-xs text-red-600 mt-0.5">past due</p>
      </div>
      <router-link to="/reports/sla" class="rounded-xl border border-brand-primary-muted bg-brand-primary-light p-4 hover:shadow-md transition cursor-pointer">
        <p class="text-xs font-semibold text-brand-primary uppercase tracking-wide">Total Tracked</p>
        <p class="text-2xl font-bold text-brand-primary-hover mt-1">{{ slaStats.kpis?.total ?? 0 }}</p>
        <p class="text-xs text-brand-primary mt-0.5">View SLA Report →</p>
      </router-link>
    </div>

    <!-- ═══ Forms Summary Table ═══ -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-200">
        <h2 class="text-base font-semibold text-gray-900">Forms Summary</h2>
        <p class="text-xs text-gray-500 mt-0.5">All 5 form types — grouped by status with Total MRC &amp; Qty</p>
      </div>

      <div v-if="loading" class="p-5 space-y-3">
        <SkeletonBox v-for="i in 5" :key="'fs'+i" class="h-10 w-full" />
      </div>

      <div v-else-if="formSummary" class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="bg-brand-primary text-white">
              <th class="px-4 py-3 text-left font-semibold whitespace-nowrap">Form Type</th>
              <th v-for="status in summaryStatuses" :key="status" class="px-3 py-3 text-center font-semibold whitespace-nowrap capitalize">{{ status.replace(/_/g, ' ') }}</th>
              <th class="px-4 py-3 text-center font-semibold">Total Qty</th>
              <th class="px-4 py-3 text-right font-semibold">Total MRC</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="ft in summaryFormTypes" :key="ft" class="hover:bg-gray-50 transition">
              <td class="px-4 py-3 font-medium text-gray-900 whitespace-nowrap">{{ ft }}</td>
              <td v-for="status in summaryStatuses" :key="ft+status" class="px-3 py-3 text-center">
                <template v-if="getSummaryCell(ft, status).qty > 0">
                  <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadgeClass(status)">
                    {{ getSummaryCell(ft, status).qty }}
                  </span>
                  <span v-if="getSummaryCell(ft, status).mrc > 0" class="block text-[10px] text-gray-400 mt-0.5">
                    {{ formatMrc(getSummaryCell(ft, status).mrc) }} AED
                  </span>
                </template>
                <span v-else class="text-gray-300">—</span>
              </td>
              <td class="px-4 py-3 text-center font-semibold text-gray-900">{{ getFormTypeTotal(ft).qty }}</td>
              <td class="px-4 py-3 text-right font-semibold text-gray-900">
                <template v-if="getFormTypeTotal(ft).mrc > 0">{{ formatMrc(getFormTypeTotal(ft).mrc) }} AED</template>
                <span v-else class="text-gray-400">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else class="p-8 text-center text-sm text-gray-400">No form data available.</div>
    </div>

    <!-- ═══ Recent Activity ═══ -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
        <h2 class="text-base font-semibold text-gray-900">Recent Activity</h2>
        <span class="text-xs text-gray-400">Last 10 items</span>
      </div>

      <div v-if="loading" class="p-5 space-y-3">
        <div v-for="i in 5" :key="'ra' + i" class="flex items-center gap-3">
          <SkeletonBox class="h-8 w-8 rounded-full" /><div class="flex-1"><SkeletonBox class="h-4 w-40 mb-1" /><SkeletonBox class="h-3 w-24" /></div><SkeletonBox class="h-3 w-16" />
        </div>
      </div>

      <div v-else-if="!recentActivity.length" class="p-8 text-center text-sm text-gray-400">
        No recent activity found.
      </div>

      <ul v-else class="divide-y divide-gray-100">
        <li v-for="(item, idx) in recentActivity" :key="idx" class="px-5 py-3 flex items-center gap-3 hover:bg-gray-50 transition">
          <span class="inline-flex items-center justify-center h-8 w-8 rounded-full text-xs font-semibold capitalize" :class="activityColor(item.type)">
            {{ item.type?.[0]?.toUpperCase() }}
          </span>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate">{{ item.label }}</p>
            <p class="text-xs text-gray-500 truncate">{{ item.detail }}</p>
          </div>
          <div class="text-right shrink-0">
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium capitalize" :class="statusBadgeClass(item.status)">
              {{ item.status }}
            </span>
            <p class="text-[10px] text-gray-400 mt-0.5">{{ timeAgo(item.timestamp) }}</p>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>
