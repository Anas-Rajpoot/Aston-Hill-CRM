<script setup>
/**
 * Audit Logs — read-only compliance module.
 *
 * KPI tiles, advanced filters, server-side paginated table,
 * detail drawer with diff, CSV export, progressive rendering.
 */
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/axios'
import { useTablePageSize } from '@/composables/useTablePageSize'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import Toast from '@/components/Toast.vue'
import SkeletonBox from '@/components/skeletons/SkeletonBox.vue'

const router = useRouter()

// ─── Toast ────────────────────────────────────────────────
const showToast = ref(false)
const toastType = ref('success')
const toastMsg  = ref('')
function toast(t, m) { toastType.value = t; toastMsg.value = m; showToast.value = true }

// ─── Loading ──────────────────────────────────────────────
const loading     = ref(true)
const statsLoading = ref(true)

// ─── Stats / KPIs ─────────────────────────────────────────
const stats = reactive({ total: 0, success_rate: 0, failed: 0, active_users: 0 })

async function fetchStats() {
  statsLoading.value = true
  try {
    const { data } = await api.get('/audit-logs/stats')
    Object.assign(stats, data.data)
  } catch { /* silent */ }
  finally { statsLoading.value = false }
}

// ─── Meta (filter options) ────────────────────────────────
const metaActions = ref([])
const metaModules = ref([])
const metaRoles   = ref([])

async function fetchMeta() {
  try {
    const { data } = await api.get('/audit-logs/meta')
    metaActions.value = data.data.actions ?? []
    metaModules.value = data.data.modules ?? []
    metaRoles.value   = data.data.roles ?? []
  } catch { /* silent */ }
}

// ─── Filters ──────────────────────────────────────────────
const filters = reactive({
  date_from: '', date_to: '', user_name: '', user_role: '',
  action: '', module: '', ip: '', result: '', session_id: '', device: '',
})
const filtersOpen = ref(true)

function resetFilters() {
  Object.keys(filters).forEach(k => filters[k] = '')
  fetchList(1)
}

// ─── Pagination & sort ────────────────────────────────────
const rows    = ref([])
const meta    = reactive({ total: 0, per_page: 10, current_page: 1, last_page: 1, from: 0, to: 0 })
const { perPage, perPageOptions, perPageReady, setPerPage } = useTablePageSize('audit-logs')
const sortKey = ref('occurred_at')
const sortDir = ref('desc')

function toggleSort(col) {
  if (sortKey.value === col) { sortDir.value = sortDir.value === 'desc' ? 'asc' : 'desc' }
  else { sortKey.value = col; sortDir.value = 'desc' }
  fetchList(1)
}

function sortIcon(col) {
  if (sortKey.value !== col) return '↕'
  return sortDir.value === 'asc' ? '↑' : '↓'
}

watch(perPage, () => fetchList(1))

async function fetchList(page = 1) {
  loading.value = true
  try {
    const params = { ...filters, page, per_page: perPage.value, sort: `${sortKey.value}:${sortDir.value}` }
    // Clean empty params
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k] })
    const { data } = await api.get('/audit-logs', { params })
    rows.value = data.data
    Object.assign(meta, data.meta)
  } catch { toast('error', 'Failed to load audit logs.') }
  finally { loading.value = false }
}

function applyFilters() { fetchList(1) }

// ─── Detail drawer ────────────────────────────────────────
const showDetail  = ref(false)
const detailLog   = ref(null)
const detailTab   = ref('summary')

async function openDetail(log) {
  try {
    const { data } = await api.get(`/audit-logs/${log.id}`)
    detailLog.value = data.data
    detailTab.value = 'summary'
    showDetail.value = true
  } catch { toast('error', 'Failed to load log details.') }
}

// ─── Export ───────────────────────────────────────────────
const exporting = ref(false)

async function exportCsv() {
  if (exporting.value) return
  exporting.value = true
  try {
    const params = { ...filters }
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k] })
    const response = await api.get('/audit-logs/export', { params, responseType: 'blob' })
    const url  = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href  = url
    link.setAttribute('download', `audit_logs_${new Date().toISOString().slice(0, 10)}.csv`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
    toast('success', 'Audit logs exported successfully.')
  } catch (e) {
    toast('error', e?.response?.status === 403 ? 'No permission to export.' : 'Export failed.')
  } finally { exporting.value = false }
}

// ─── Helpers ──────────────────────────────────────────────
function fmtDate(iso) {
  if (!iso) return '—'
  const d = new Date(iso)
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
    + ' ' + d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
}

function actionChipClass(color) {
  const map = {
    green: 'bg-green-100 text-green-700', blue: 'bg-blue-100 text-blue-700',
    red: 'bg-red-100 text-red-700', purple: 'bg-purple-100 text-purple-700',
    teal: 'bg-teal-100 text-teal-700', gray: 'bg-gray-100 text-gray-600',
    orange: 'bg-orange-100 text-orange-700', indigo: 'bg-indigo-100 text-indigo-700',
    yellow: 'bg-yellow-100 text-yellow-800',
  }
  return map[color] || map.gray
}

function prettyJson(obj) {
  if (!obj) return '—'
  try { return JSON.stringify(obj, null, 2) } catch { return String(obj) }
}

const crumbs = [
  { label: 'Settings', to: '/settings' },
  { label: 'Audit Logs' },
]

// ─── Init ─────────────────────────────────────────────────
onMounted(() => {
  Promise.allSettled([fetchStats(), fetchMeta(), fetchList(1)])
})
</script>

<template>
  <div class="space-y-6 bg-white -mx-4 -my-5 min-h-full px-6 py-6">
    <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="4000" @dismiss="showToast = false" />

    <!-- ═══ Header ═══ -->
    <div class="bg-white border-b border-gray-200 px-6 py-5">
      <div class="max-w-[1400px] mx-auto">
        <div class="flex items-start justify-between">
          <div class="flex items-center gap-3">
            <button class="p-1 rounded hover:bg-gray-100 text-gray-500" @click="router.push('/settings')">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            </button>
            <div>
              <Breadcrumbs :items="crumbs" class="mb-1" />
              <h1 class="text-xl font-bold text-gray-900">Audit Logs</h1>
              <p class="text-sm text-gray-500 mt-0.5">Track all system changes and user actions for accountability</p>
            </div>
          </div>
          <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 border border-blue-200">Super Admin Only</span>
        </div>
      </div>
    </div>

    <div class="max-w-[1400px] mx-auto px-6 py-6 space-y-5">

      <!-- ═══ Compliance Banner ═══ -->
      <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 flex items-start gap-3">
        <div class="flex-shrink-0 mt-0.5">
          <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
        </div>
        <div>
          <p class="text-sm font-semibold text-red-800">Read-Only Compliance Module</p>
          <p class="text-sm text-red-700 mt-0.5">All audit logs are permanently stored and cannot be edited or deleted. These records are maintained for compliance, security investigations, and accountability purposes. Data is retained indefinitely.</p>
        </div>
      </div>

      <!-- ═══ KPI Tiles ═══ -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <!-- Total Logs -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm text-gray-500">Total Logs</span>
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
            </div>
          </div>
          <template v-if="statsLoading"><SkeletonBox class="h-8 w-16" /></template>
          <p v-else class="text-2xl font-bold text-gray-900">{{ stats.total }}</p>
          <p class="text-xs text-gray-400 mt-0.5">recorded entries</p>
        </div>

        <!-- Success Rate -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm text-gray-500">Success Rate</span>
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-50 text-green-600">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
          </div>
          <template v-if="statsLoading"><SkeletonBox class="h-8 w-16" /></template>
          <p v-else class="text-2xl font-bold text-green-600">{{ stats.success_rate }}%</p>
          <p class="text-xs text-gray-400 mt-0.5">successful actions</p>
        </div>

        <!-- Failed Actions -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm text-gray-500">Failed Actions</span>
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-50 text-red-600">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
          </div>
          <template v-if="statsLoading"><SkeletonBox class="h-8 w-16" /></template>
          <p v-else class="text-2xl font-bold text-red-600">{{ stats.failed }}</p>
          <p class="text-xs text-gray-400 mt-0.5">flagged actions</p>
        </div>

        <!-- Active Users -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm text-gray-500">Active Users</span>
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-50 text-purple-600">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
            </div>
          </div>
          <template v-if="statsLoading"><SkeletonBox class="h-8 w-16" /></template>
          <p v-else class="text-2xl font-bold text-purple-600">{{ stats.active_users }}</p>
          <p class="text-xs text-gray-400 mt-0.5">unique users</p>
        </div>
      </div>

      <!-- ═══ Advanced Filters ═══ -->
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <button class="w-full flex items-center justify-between px-5 py-3 text-left" @click="filtersOpen = !filtersOpen">
          <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
            <span class="text-sm font-semibold text-gray-700">Advanced Filters</span>
          </div>
          <div class="flex items-center gap-2">
            <button type="button" class="text-xs text-gray-500 hover:text-red-500" @click.stop="resetFilters">Reset Filters</button>
            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': filtersOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
          </div>
        </button>

        <div v-show="filtersOpen" class="px-5 pb-4 pt-1 border-t border-gray-100">
          <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3 mb-3">
            <div>
              <label class="block text-xs font-medium text-gray-500 mb-1">Date From</label>
              <input v-model="filters.date_from" type="date" class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500 mb-1">Date To</label>
              <input v-model="filters.date_to" type="date" class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500 mb-1">User Name</label>
              <input v-model="filters.user_name" type="text" placeholder="Search by name..." class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500 mb-1">User Role</label>
              <select v-model="filters.user_role" class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <option value="">All Roles</option>
                <option v-for="r in metaRoles" :key="r" :value="r">{{ r }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500 mb-1">IP Address</label>
              <input v-model="filters.ip" type="text" placeholder="e.g. 192.168.1.0" class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
            </div>
          </div>
          <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3 mb-4">
            <div>
              <label class="block text-xs font-medium text-gray-500 mb-1">Action Type</label>
              <select v-model="filters.action" class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <option value="">All Actions</option>
                <option v-for="a in metaActions" :key="a" :value="a">{{ a.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500 mb-1">Module</label>
              <select v-model="filters.module" class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <option value="">All Modules</option>
                <option v-for="m in metaModules" :key="m" :value="m">{{ m }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
              <select v-model="filters.result" class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <option value="">All Statuses</option>
                <option value="success">Success</option>
                <option value="failure">Failure</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500 mb-1">Session ID</label>
              <input v-model="filters.session_id" type="text" placeholder="Session..." class="w-full rounded-md border border-gray-300 px-2.5 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
            </div>
            <div class="flex items-end">
              <button type="button" class="w-full rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors" @click="applyFilters">Apply Filters</button>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══ Table ═══ -->
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200 bg-gray-50/80">
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer whitespace-nowrap" @click="toggleSort('occurred_at')">
                  Timestamp <span class="text-gray-400">{{ sortIcon('occurred_at') }}</span>
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer whitespace-nowrap" @click="toggleSort('user_name')">
                  User Name <span class="text-gray-400">{{ sortIcon('user_name') }}</span>
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">User Role</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer whitespace-nowrap" @click="toggleSort('action')">
                  Action Type <span class="text-gray-400">{{ sortIcon('action') }}</span>
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer whitespace-nowrap" @click="toggleSort('module')">
                  Module <span class="text-gray-400">{{ sortIcon('module') }}</span>
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Record ID</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">IP Address</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Device / Browser</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer whitespace-nowrap" @click="toggleSort('result')">
                  Status <span class="text-gray-400">{{ sortIcon('result') }}</span>
                </th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <!-- Loading skeleton -->
              <template v-if="loading">
                <tr v-for="i in perPage" :key="'sk-' + i">
                  <td v-for="j in 10" :key="j" class="px-4 py-3.5"><SkeletonBox class="h-4 w-full" /></td>
                </tr>
              </template>

              <!-- Rows -->
              <template v-else>
                <tr
                  v-for="log in rows"
                  :key="log.id"
                  class="hover:bg-gray-50/70 transition-colors cursor-pointer"
                  @click="openDetail(log)"
                >
                  <td class="px-4 py-3 text-gray-600 whitespace-nowrap text-xs">{{ fmtDate(log.occurred_at) }}</td>
                  <td class="px-4 py-3 font-medium text-gray-900 whitespace-nowrap text-xs">{{ log.user_name }}</td>
                  <td class="px-4 py-3 text-gray-500 whitespace-nowrap text-xs">{{ log.user_role || '—' }}</td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize" :class="actionChipClass(log.action_color)">
                      {{ log.action.replace(/_/g, ' ') }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-gray-700 whitespace-nowrap text-xs">{{ log.module }}</td>
                  <td class="px-4 py-3 text-gray-500 whitespace-nowrap text-xs font-mono">{{ log.record_ref || log.record_id || '—' }}</td>
                  <td class="px-4 py-3 text-gray-500 whitespace-nowrap text-xs font-mono">{{ log.ip || '—' }}</td>
                  <td class="px-4 py-3 text-gray-500 whitespace-nowrap text-xs">{{ log.device || '—' }}</td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize" :class="log.result === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                      {{ log.result }}
                    </span>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap" @click.stop>
                    <button type="button" class="p-1.5 rounded text-blue-600 hover:bg-blue-50" title="View Details" @click="openDetail(log)">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                    </button>
                  </td>
                </tr>
                <tr v-if="!rows.length">
                  <td colspan="10" class="px-6 py-12 text-center text-sm text-gray-400">No audit logs found.</td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Footer: pagination + export -->
        <div class="border-t border-gray-200 px-4 py-3 flex flex-col sm:flex-row items-center justify-between gap-3 bg-gray-50/50">
          <div class="flex items-center gap-3 text-sm text-gray-500">
            <span>Showing {{ meta.from ?? 0 }} to {{ meta.to ?? 0 }} of {{ meta.total }} entries</span>
            <span class="text-gray-300">|</span>
            <label class="flex items-center gap-1.5">
              Number of pages:
              <select :value="perPage" class="rounded border border-gray-300 px-2 py-1 text-sm focus:border-blue-500" @change="e => { setPerPage(e.target.value); fetchList(1) }">
                <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
              </select>
            </label>
          </div>

          <div class="flex items-center gap-2">
            <!-- Export -->
            <button type="button" :disabled="exporting" class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 transition" @click="exportCsv">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
              {{ exporting ? 'Exporting…' : 'Export CSV' }}
            </button>

            <!-- Pagination -->
            <button :disabled="meta.current_page <= 1" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-40 transition" @click="fetchList(meta.current_page - 1)">Previous</button>
            <span class="px-2 py-1.5 text-xs font-medium text-gray-600">{{ meta.current_page }}</span>
            <button :disabled="meta.current_page >= meta.last_page" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-40 transition" @click="fetchList(meta.current_page + 1)">Next</button>
          </div>
        </div>
      </div>

      <!-- ═══ Audit Retention Info ═══ -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center gap-2 mb-3">
          <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          <h3 class="text-sm font-bold text-gray-800">Audit Log Retention Policy</h3>
        </div>
        <ul class="space-y-1 text-xs text-gray-500 list-disc list-inside ml-1">
          <li>All system actions are automatically logged with timestamp, user, and IP address</li>
          <li>Logs include before/after values for all data modifications</li>
          <li>Failed login attempts and access denied events are flagged for investigation</li>
          <li>Data is retained permanently for compliance and can not be deleted</li>
          <li>Regular quarterly review recommended for security audits</li>
        </ul>
      </div>
    </div>

    <!-- ═══ Detail Drawer ═══ -->
    <Teleport to="body">
      <div v-if="showDetail && detailLog" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="showDetail = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-xl max-h-[90vh] flex flex-col overflow-hidden" @click.stop>
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 class="text-base font-bold text-gray-900">Log Details</h3>
            <button class="p-1 rounded hover:bg-gray-100 text-gray-400" @click="showDetail = false">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>

          <!-- Tabs -->
          <div class="border-b border-gray-200 px-6">
            <div class="flex gap-4">
              <button v-for="tab in ['summary', 'old_values', 'new_values']" :key="tab"
                class="py-2.5 text-sm font-medium border-b-2 transition-colors capitalize"
                :class="detailTab === tab ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                @click="detailTab = tab"
              >{{ tab.replace(/_/g, ' ') }}</button>
            </div>
          </div>

          <!-- Body -->
          <div class="flex-1 overflow-y-auto px-6 py-4">
            <!-- Summary tab -->
            <div v-if="detailTab === 'summary'" class="space-y-3">
              <div class="grid grid-cols-2 gap-3 text-sm">
                <div><span class="text-gray-400 text-xs block">Date/Time</span><span class="font-medium text-gray-900">{{ fmtDate(detailLog.occurred_at) }}</span></div>
                <div><span class="text-gray-400 text-xs block">User</span><span class="font-medium text-gray-900">{{ detailLog.user_name }}</span></div>
                <div><span class="text-gray-400 text-xs block">Role</span><span class="font-medium text-gray-900">{{ detailLog.user_role || '—' }}</span></div>
                <div>
                  <span class="text-gray-400 text-xs block">Action</span>
                  <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="actionChipClass(detailLog.action_color)">{{ detailLog.action.replace(/_/g, ' ') }}</span>
                </div>
                <div><span class="text-gray-400 text-xs block">Module</span><span class="font-medium text-gray-900">{{ detailLog.module }}</span></div>
                <div><span class="text-gray-400 text-xs block">Record</span><span class="font-medium text-gray-900 font-mono">{{ detailLog.record_ref || detailLog.record_id || '—' }}</span></div>
                <div>
                  <span class="text-gray-400 text-xs block">Status</span>
                  <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold capitalize" :class="detailLog.result === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">{{ detailLog.result }}</span>
                </div>
                <div><span class="text-gray-400 text-xs block">IP Address</span><span class="font-medium text-gray-900 font-mono">{{ detailLog.ip || '—' }}</span></div>
                <div><span class="text-gray-400 text-xs block">Device</span><span class="font-medium text-gray-900">{{ detailLog.device || '—' }}</span></div>
                <div><span class="text-gray-400 text-xs block">Session</span><span class="font-medium text-gray-900 font-mono text-xs">{{ detailLog.session_id || '—' }}</span></div>
                <div><span class="text-gray-400 text-xs block">Route</span><span class="font-medium text-gray-900 font-mono text-xs">{{ detailLog.method }} {{ detailLog.route || '—' }}</span></div>
                <div><span class="text-gray-400 text-xs block">Latency</span><span class="font-medium text-gray-900">{{ detailLog.latency_ms ? detailLog.latency_ms + ' ms' : '—' }}</span></div>
              </div>
            </div>

            <!-- Old Values tab -->
            <div v-else-if="detailTab === 'old_values'">
              <pre v-if="detailLog.old_values" class="bg-gray-50 rounded-lg p-4 text-xs text-gray-700 overflow-x-auto whitespace-pre-wrap font-mono">{{ prettyJson(detailLog.old_values) }}</pre>
              <p v-else class="text-sm text-gray-400 text-center py-8">No previous values recorded.</p>
            </div>

            <!-- New Values tab -->
            <div v-else-if="detailTab === 'new_values'">
              <pre v-if="detailLog.new_values" class="bg-gray-50 rounded-lg p-4 text-xs text-gray-700 overflow-x-auto whitespace-pre-wrap font-mono">{{ prettyJson(detailLog.new_values) }}</pre>
              <p v-else class="text-sm text-gray-400 text-center py-8">No new values recorded.</p>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-6 py-3 border-t border-gray-200 flex justify-end bg-white">
            <button type="button" class="rounded-lg bg-gray-600 px-5 py-2 text-sm font-medium text-white hover:bg-gray-700 transition" @click="showDetail = false">Close</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
