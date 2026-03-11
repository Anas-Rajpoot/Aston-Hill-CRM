<script setup>
/**
 * Reports main page: 4 report cards (Lead, Field Operations, VAS, SLA) with "View Report" links.
 */
import { useRouter } from 'vue-router'
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { canModuleAction } from '@/lib/accessControl'

const router = useRouter()
const auth = useAuthStore()
const canView = computed(() =>
  canModuleAction(auth.user, 'reports', 'view', ['reports.view', 'reports.list'])
)

const cards = [
  {
    id: 'lead',
    title: 'Lead Reports',
    description: 'Analyze Lead Submissions, Resubmissions, and conversion metrics by service category and sales team.',
    icon: 'document',
    iconBg: 'bg-brand-primary',
    route: '/reports/lead',
  },
  {
    id: 'field',
    title: 'Field Operations Reports',
    description: 'Track field meetings, agent workload, completion rates, and SLA compliance.',
    icon: 'map-pin',
    iconBg: 'bg-brand-primary',
    route: '/reports/field-operations',
  },
  {
    id: 'vas',
    title: 'VAS Reports',
    description: 'Analyze VAS request types, DU status distribution, and completion metrics.',
    icon: 'heart',
    iconBg: 'bg-brand-primary',
    route: '/reports/vas',
  },
  {
    id: 'sla',
    title: 'SLA & Performance Reports',
    description: 'Track SLA compliance, breach analysis, and average processing times across all modules.',
    icon: 'clock',
    iconBg: 'bg-brand-primary',
    route: '/reports/sla',
  },
  {
    id: 'support',
    title: 'Customer Support Reports',
    description: 'Ticket volume, resolution rates, CSR workload distribution, and monthly trend analysis.',
    icon: 'support',
    iconBg: 'bg-brand-primary',
    route: '/reports/customer-support',
  },
  {
    id: 'clients',
    title: 'Client & Company Reports',
    description: 'Client growth, status distribution, top accounts by submissions, and MRC revenue insights.',
    icon: 'building',
    iconBg: 'bg-brand-primary',
    route: '/reports/clients',
  },
]

function viewReport(route) {
  if (!canView.value) return
  router.push(route)
}
</script>

<template>
  <div class="space-y-8 bg-white -mx-4 -my-5 min-h-full px-6 py-6">
    <div v-if="!canView" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
      You do not have permission to view reports.
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <article
        v-for="card in cards"
        :key="card.id"
        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm hover:shadow-md transition-shadow"
      >
        <div class="flex gap-4">
          <div
            :class="[card.iconBg, 'flex h-12 w-12 shrink-0 items-center justify-center rounded-lg text-white']"
          >
            <svg
              v-if="card.icon === 'document'"
              class="h-6 w-6"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <svg
              v-else-if="card.icon === 'map-pin'"
              class="h-6 w-6"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <svg
              v-else-if="card.icon === 'heart'"
              class="h-6 w-6"
              fill="currentColor"
              viewBox="0 0 24 24"
            >
              <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
            </svg>
            <svg
              v-else-if="card.icon === 'support'"
              class="h-6 w-6"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            <svg
              v-else-if="card.icon === 'building'"
              class="h-6 w-6"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
            <svg
              v-else
              class="h-6 w-6"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="min-w-0 flex-1">
            <h2 class="text-lg font-semibold text-gray-900">{{ card.title }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ card.description }}</p>
            <button
              type="button"
              class="mt-3 text-sm font-medium text-brand-primary hover:text-brand-primary"
              @click="viewReport(card.route)"
            >
              View Report →
            </button>
          </div>
        </div>
      </article>
    </div>

    <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-5">
      <div class="flex gap-3">
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-200 text-gray-600">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </span>
        <div>
          <h3 class="text-base font-semibold text-gray-900">Report features</h3>
          <ul class="mt-2 list-disc list-inside space-y-1 text-sm text-gray-600">
            <li>Export reports to Excel or CSV format</li>
            <li>Print-friendly layouts for all reports</li>
            <li>Advanced filtering and date range selection</li>
            <li>Role-based access control for sensitive data</li>
            <li>Real-time data updates and trend analysis</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>
