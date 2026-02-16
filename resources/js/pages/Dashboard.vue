<script setup>
/**
 * Dashboard — KPIs, recent activity, auto-refresh toggle.
 */
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/lib/axios'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import SkeletonBox from '@/components/skeletons/SkeletonBox.vue'

const auth = useAuthStore()
const loading = ref(true)
const refreshing = ref(false)
const kpis = ref({})
const recentActivity = ref([])

function applyData(data) {
  if (!data) return
  kpis.value = data.kpis ?? {}
  recentActivity.value = data.recent_activity ?? []
}

// ── Initial load ──────────────────────────────────────
async function initialLoad() {
  loading.value = true
  try {
    const { data } = await api.get('/dashboard/stats')
    applyData(data.data)
  } catch { /* silent */ }
  finally { loading.value = false }
}

// ── Manual refresh ────────────────────────────────────
async function doRefresh() {
  if (refreshing.value) return
  refreshing.value = true
  try {
    const { data } = await api.get('/dashboard/stats')
    applyData(data.data)
  } catch { /* silent */ }
  finally { refreshing.value = false }
}

onMounted(initialLoad)

// ── KPI cards config ──────────────────────────────────
const cards = computed(() => [
  { label: 'Total Leads',       value: kpis.value.total_leads ?? 0,       sub: `${kpis.value.leads_today ?? 0} today`,       icon: '📋', color: 'blue' },
  { label: 'Field Submissions', value: kpis.value.field_submissions ?? 0, sub: `${kpis.value.field_today ?? 0} today`,       icon: '📍', color: 'green' },
  { label: 'Support Tickets',   value: kpis.value.support_tickets ?? 0,   sub: `${kpis.value.support_open ?? 0} open`,       icon: '🎫', color: 'amber' },
  { label: 'VAS Requests',      value: kpis.value.vas_requests ?? 0,      sub: `${kpis.value.vas_pending ?? 0} pending`,     icon: '⚡', color: 'purple' },
  { label: 'Total Clients',     value: kpis.value.total_clients ?? 0,     sub: '',                                           icon: '👥', color: 'teal' },
  { label: 'Active Users',      value: kpis.value.active_users ?? 0,      sub: 'last 7 days',                                icon: '🟢', color: 'indigo' },
])

function cardBg(c) {
  const m = { blue: 'bg-blue-50 border-blue-200', green: 'bg-green-50 border-green-200', amber: 'bg-amber-50 border-amber-200', purple: 'bg-purple-50 border-purple-200', teal: 'bg-teal-50 border-teal-200', indigo: 'bg-indigo-50 border-indigo-200' }
  return m[c] || 'bg-gray-50 border-gray-200'
}
function cardText(c) {
  const m = { blue: 'text-blue-700', green: 'text-green-700', amber: 'text-amber-700', purple: 'text-purple-700', teal: 'text-teal-700', indigo: 'text-indigo-700' }
  return m[c] || 'text-gray-700'
}

// ── Activity helpers ──────────────────────────────────
function activityColor(type) {
  const m = { lead: 'bg-blue-100 text-blue-700', field: 'bg-green-100 text-green-700', support: 'bg-amber-100 text-amber-700', vas: 'bg-purple-100 text-purple-700' }
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
</script>

<template>
  <div class="bg-white -mx-4 -my-5 min-h-full px-6 py-6 space-y-6">

    <!-- ═══ Header ═══ -->
    <div class="flex items-center justify-between">
      <div>
        <div class="flex items-center gap-2">
          <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
          <Breadcrumbs />
        </div>
        <p class="text-sm text-gray-500 mt-0.5">Welcome back, {{ auth.user?.name ?? 'User' }}</p>
      </div>
    </div>

    <!-- ═══ KPI Cards ═══ -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
      <template v-if="loading">
        <div v-for="i in 6" :key="'sk' + i" class="rounded-xl border border-gray-200 p-4">
          <SkeletonBox class="h-4 w-20 mb-2" /><SkeletonBox class="h-8 w-16 mb-1" /><SkeletonBox class="h-3 w-24" />
        </div>
      </template>
      <template v-else>
        <div
          v-for="card in cards"
          :key="card.label"
          class="rounded-xl border p-4 transition-all hover:shadow-sm"
          :class="cardBg(card.color)"
        >
          <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ card.label }}</span>
            <span class="text-lg">{{ card.icon }}</span>
          </div>
          <p class="text-2xl font-bold" :class="cardText(card.color)">{{ card.value.toLocaleString() }}</p>
          <p v-if="card.sub" class="text-xs text-gray-500 mt-0.5">{{ card.sub }}</p>
        </div>
      </template>
    </div>

    <!-- ═══ Recent Activity ═══ -->
    <div class="rounded-xl border border-gray-200 bg-gray-50/60 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
        <h2 class="text-base font-semibold text-gray-900">Recent Activity</h2>
        <button
          class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-500 hover:text-gray-700 transition"
          :disabled="refreshing"
          @click="doRefresh"
        >
          <svg class="w-3.5 h-3.5" :class="refreshing && 'animate-spin'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          Refresh
        </button>
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
        <li v-for="(item, idx) in recentActivity" :key="idx" class="px-5 py-3 flex items-center gap-3 hover:bg-white/60 transition">
          <span class="inline-flex items-center justify-center h-8 w-8 rounded-full text-xs font-semibold capitalize" :class="activityColor(item.type)">
            {{ item.type?.[0]?.toUpperCase() }}
          </span>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate">{{ item.label }}</p>
            <p class="text-xs text-gray-500 truncate">{{ item.detail }}</p>
          </div>
          <div class="text-right shrink-0">
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium capitalize"
              :class="item.status === 'open' || item.status === 'pending' ? 'bg-amber-100 text-amber-700' : item.status === 'completed' || item.status === 'closed' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'">
              {{ item.status }}
            </span>
            <p class="text-[10px] text-gray-400 mt-0.5">{{ timeAgo(item.timestamp) }}</p>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>
