<script setup>
/**
 * Client & Company Reports – KPIs, status distribution, top clients,
 * monthly client growth, and revenue summary.
 * Permission: reports.view / reports.list
 */
import { ref, computed, watch, onMounted } from 'vue'
import api from '@/lib/axios'
import { useAuthStore } from '@/stores/auth'
import { canModuleAction } from '@/lib/accessControl'
import { useRouter } from 'vue-router'

const router = useRouter()
const authStore = useAuthStore()
const canView = computed(() =>
  canModuleAction(authStore.user, 'reports', 'view', ['reports.view', 'reports.list'])
)

/* ───── Loading & filter state ───── */
const loading = ref(true)
const filtersVisible = ref(false)
const filters = ref({ from: '', to: '', status: '' })

/* ───── Data state ───── */
const stats = ref({
  total_clients: 0,
  by_status: {},
  top_clients: [],
  monthly_growth: [],
  revenue_summary: { total_mrc: 0, avg_mrc: 0, deals_with_mrc: 0 },
})

/* ───── Computed helpers ───── */
const statusEntries = computed(() =>
  Object.entries(stats.value.by_status).map(([status, count]) => ({ status, count }))
)
const maxStatusCount = computed(() => Math.max(1, ...statusEntries.value.map((s) => s.count)))
const maxTopSubmissions = computed(() => Math.max(1, ...stats.value.top_clients.map((c) => c.total_submissions)))
const maxGrowthCount = computed(() => Math.max(1, ...stats.value.monthly_growth.map((m) => m.count)))

function formatCurrency(val) {
  return new Intl.NumberFormat('en-AE', { style: 'currency', currency: 'AED', minimumFractionDigits: 0 }).format(val)
}

/* ───── Data loading ───── */
async function loadData() {
  if (!canView.value) return
  loading.value = true
  try {
    const params = {}
    if (filters.value.from) params.from = filters.value.from
    if (filters.value.to) params.to = filters.value.to
    if (filters.value.status) params.status = filters.value.status
    const { data } = await api.get('/reports/client-stats', { params })
    stats.value = data
  } catch {
    /* keep defaults */
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  filters.value = { from: '', to: '', status: '' }
}

watch(filters, () => loadData(), { deep: true })
onMounted(() => loadData())

const statusColor = (status) => {
  const map = {
    active: 'bg-green-100 text-green-800',
    inactive: 'bg-gray-100 text-gray-800',
    pending: 'bg-amber-100 text-amber-800',
    suspended: 'bg-red-100 text-red-800',
    closed: 'bg-gray-100 text-gray-800',
  }
  return map[status?.toLowerCase()] || 'bg-gray-100 text-gray-800'
}
</script>

<template>
  <div class="space-y-6 bg-white -mx-4 -my-5 min-h-full px-6 py-6">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <button
          type="button"
          class="mb-2 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-brand-primary"
          @click="router.push('/reports')"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
          Back to Reports
        </button>
        <h1 class="text-2xl font-bold text-gray-900">Client & Company Reports</h1>
        <p class="mt-1 text-sm text-gray-500">Client growth, status distribution, top accounts, and revenue insights.</p>
      </div>
      <button
        type="button"
        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
        @click="filtersVisible = !filtersVisible"
      >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
        </svg>
        {{ filtersVisible ? 'Hide Filters' : 'Filters' }}
      </button>
    </div>

    <!-- Permission gate -->
    <div v-if="!canView" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
      You do not have permission to view reports.
    </div>

    <template v-else>
      <!-- Filters -->
      <div v-if="filtersVisible" class="rounded-xl border border-gray-200 bg-gray-50 p-4">
        <div class="flex flex-wrap items-end gap-4">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">From</label>
            <input v-model="filters.from" type="date" class="rounded-lg border border-gray-300 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">To</label>
            <input v-model="filters.to" type="date" class="rounded-lg border border-gray-300 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
            <select v-model="filters.status" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
              <option value="">All</option>
              <option v-for="s in statusEntries" :key="s.status" :value="s.status">{{ s.status }}</option>
            </select>
          </div>
          <button
            type="button"
            class="rounded-lg bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300"
            @click="resetFilters"
          >
            Reset
          </button>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex items-center justify-center py-16">
        <svg class="h-8 w-8 animate-spin text-brand-primary" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
        </svg>
      </div>

      <template v-else>
        <!-- KPI Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div class="rounded-xl bg-brand-primary p-5 text-white shadow-sm">
            <p class="text-sm font-medium opacity-80">Total Clients</p>
            <p class="mt-1 text-2xl font-bold">{{ stats.total_clients }}</p>
          </div>
          <div class="rounded-xl bg-blue-600 p-5 text-white shadow-sm">
            <p class="text-sm font-medium opacity-80">Total MRC</p>
            <p class="mt-1 text-2xl font-bold">{{ formatCurrency(stats.revenue_summary.total_mrc) }}</p>
          </div>
          <div class="rounded-xl bg-indigo-600 p-5 text-white shadow-sm">
            <p class="text-sm font-medium opacity-80">Avg MRC</p>
            <p class="mt-1 text-2xl font-bold">{{ formatCurrency(stats.revenue_summary.avg_mrc) }}</p>
          </div>
          <div class="rounded-xl bg-green-600 p-5 text-white shadow-sm">
            <p class="text-sm font-medium opacity-80">Deals with MRC</p>
            <p class="mt-1 text-2xl font-bold">{{ stats.revenue_summary.deals_with_mrc }}</p>
          </div>
        </div>

        <!-- Status Distribution -->
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
          <h2 class="mb-4 text-lg font-semibold text-gray-900">Client Status Distribution</h2>
          <div v-if="statusEntries.length === 0" class="text-sm text-gray-500">No data available.</div>
          <div v-else class="space-y-3">
            <div v-for="s in statusEntries" :key="s.status" class="flex items-center gap-3">
              <span :class="[statusColor(s.status), 'inline-block rounded-full px-3 py-0.5 text-xs font-medium capitalize w-28 text-center']">
                {{ s.status?.replace(/_/g, ' ') || 'Unknown' }}
              </span>
              <div class="flex-1 h-5 rounded-full bg-gray-100 overflow-hidden">
                <div
                  class="h-full rounded-full bg-brand-primary transition-all"
                  :style="{ width: (s.count / maxStatusCount * 100) + '%' }"
                />
              </div>
              <span class="text-sm font-semibold text-gray-700 w-10 text-right">{{ s.count }}</span>
            </div>
          </div>
        </div>

        <!-- Top Clients by Submissions -->
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
          <h2 class="mb-4 text-lg font-semibold text-gray-900">Top 10 Clients by Total Submissions</h2>
          <div v-if="stats.top_clients.length === 0" class="text-sm text-gray-500">No data available.</div>
          <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-brand-primary border-b-2 border-green-700">
                  <th class="px-4 py-3 text-left text-xs font-semibold text-white uppercase">#</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-white uppercase">Company</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-white uppercase">Account #</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-white uppercase">Submissions</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-white uppercase w-64"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(c, i) in stats.top_clients" :key="c.account_number" class="border-b border-gray-100 hover:bg-gray-50">
                  <td class="px-4 py-3 text-gray-500">{{ i + 1 }}</td>
                  <td class="px-4 py-3 font-medium text-gray-900">{{ c.company_name }}</td>
                  <td class="px-4 py-3 text-gray-600">{{ c.account_number }}</td>
                  <td class="px-4 py-3 text-right font-semibold text-gray-900">{{ c.total_submissions }}</td>
                  <td class="px-4 py-3">
                    <div class="h-4 rounded-full bg-gray-100 overflow-hidden">
                      <div
                        class="h-full rounded-full bg-brand-primary transition-all"
                        :style="{ width: (c.total_submissions / maxTopSubmissions * 100) + '%' }"
                      />
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Monthly Client Growth -->
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
          <h2 class="mb-4 text-lg font-semibold text-gray-900">Monthly Client Growth</h2>
          <div v-if="stats.monthly_growth.length === 0" class="text-sm text-gray-500">No data available.</div>
          <div v-else class="flex items-end gap-2 h-48 overflow-x-auto">
            <div
              v-for="m in stats.monthly_growth"
              :key="m.label"
              class="flex flex-col items-center gap-1 min-w-[3rem]"
            >
              <span class="text-xs font-semibold text-gray-700">{{ m.count }}</span>
              <div
                class="w-8 rounded-t bg-brand-primary transition-all"
                :style="{ height: Math.max(4, m.count / maxGrowthCount * 160) + 'px' }"
              />
              <span class="text-[10px] text-gray-500 whitespace-nowrap">{{ m.label }}</span>
            </div>
          </div>
        </div>
      </template>
    </template>
  </div>
</template>
