<script setup>
/**
 * VAS Request Details – read-only view: Basic Info, Request, Team, Status & Documents, Creator.
 */
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import vasRequestsApi from '@/services/vasRequestsApi'
import { useAuthStore } from '@/stores/auth'
import Breadcrumbs from '@/components/Breadcrumbs.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const loading = ref(true)
const request = ref(null)

const id = computed(() => {
  const p = route.params.id
  return p != null ? Number(p) : null
})

const canEdit = computed(() => (auth.user?.permissions ?? []).includes('vas.edit'))

function displayVal(val) {
  return val != null && val !== '' ? String(val) : '—'
}

function formatDateTime(d) {
  if (!d) return '—'
  const date = new Date(d)
  if (Number.isNaN(date.getTime())) return '—'
  const day = String(date.getDate()).padStart(2, '0')
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const year = date.getFullYear()
  const h = String(date.getHours()).padStart(2, '0')
  const m = String(date.getMinutes()).padStart(2, '0')
  return `${year}-${month}-${day} ${h}:${m}`
}

const STATUS_BADGES = {
  draft: 'bg-gray-100 text-gray-700',
  submitted: 'bg-blue-100 text-blue-700',
  approved: 'bg-green-100 text-green-700',
  rejected: 'bg-red-100 text-red-700',
}
function statusBadgeClass(status) {
  return STATUS_BADGES[status] ?? 'bg-gray-100 text-gray-700'
}

function docDisplayName(doc) {
  return doc?.label || doc?.file_name || doc?.doc_key || 'Document'
}

async function load() {
  if (!id.value) return
  window.scrollTo(0, 0)
  loading.value = true
  request.value = null
  try {
    const res = await vasRequestsApi.getRequest(id.value)
    request.value = res?.data ?? res
  } catch {
    request.value = null
  } finally {
    loading.value = false
    window.scrollTo(0, 0)
  }
}

function goBack() {
  router.push('/vas-requests')
}

function goToEdit() {
  if (request.value?.id) router.push(`/vas-requests/${request.value.id}/edit`)
}

onMounted(() => load())
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-[#f0f2f5] p-0">
    <div class="mx-auto max-w-7xl px-1 sm:px-2">
      <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="px-4 py-4 sm:px-5">
          <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-baseline gap-2">
              <h1 class="text-xl font-semibold text-gray-900">VAS Request Details</h1>
              <Breadcrumbs />
            </div>
            <div class="flex items-center gap-2">
              <button
                v-if="canEdit"
                type="button"
                class="rounded bg-green-500 px-4 py-2 text-sm font-medium text-white hover:bg-green-600"
                @click="goToEdit"
              >
                Edit Request
              </button>
              <button
                type="button"
                class="rounded p-2 text-gray-600 hover:bg-gray-200 hover:text-gray-900"
                aria-label="Close"
                @click="goBack"
              >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>
        </div>

        <div class="border-t border-gray-200" />

        <div v-if="loading" class="flex justify-center px-4 py-16 sm:px-5">
          <svg class="h-10 w-10 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
        </div>

        <div v-else-if="!request" class="px-4 py-8 text-center text-gray-500 sm:px-5">
          Unable to load request. You may not have permission to view it.
        </div>

        <div v-else class="px-4 py-5 sm:px-5">
          <!-- Basic Information: compact grid, label + value on same row. Row 1: ID, Submission Date, Company. Row 2: Request Type, Account Number, Created. -->
          <section class="mb-5">
            <h2 class="mb-3 text-sm font-semibold text-gray-900">Basic Information</h2>
            <div class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
              <div class="flex items-baseline gap-2">
                <span class="shrink-0 text-xs font-medium text-gray-500">Request ID</span>
                <span class="text-sm font-medium text-gray-800">{{ request.id }}</span>
              </div>
              <div class="flex items-baseline gap-2">
                <span class="shrink-0 text-xs font-medium text-gray-500">Submission Date</span>
                <span class="text-sm font-medium text-gray-800">{{ formatDateTime(request.submitted_at) }}</span>
              </div>
              <div class="flex items-baseline gap-2">
                <span class="shrink-0 text-xs font-medium text-gray-500">Company Name</span>
                <span class="text-sm font-medium text-gray-800">{{ displayVal(request.company_name) }}</span>
              </div>
              <div class="flex items-baseline gap-2">
                <span class="shrink-0 text-xs font-medium text-gray-500">Request Type</span>
                <span class="text-sm font-medium text-gray-800">{{ displayVal(request.request_type) }}</span>
              </div>
              <div class="flex items-baseline gap-2">
                <span class="shrink-0 text-xs font-medium text-gray-500">Account Number</span>
                <span class="text-sm font-medium text-gray-800">{{ displayVal(request.account_number) }}</span>
              </div>
              <div class="flex items-baseline gap-2">
                <span class="shrink-0 text-xs font-medium text-gray-500">Created</span>
                <span class="text-sm font-medium text-gray-800">{{ formatDateTime(request.created_at) }}</span>
              </div>
            </div>
          </section>

          <!-- Description (no "Request" heading) -->
          <section class="mb-5">
            <div class="flex items-start gap-2">
              <span class="shrink-0 text-xs font-medium text-gray-500">Description</span>
              <span class="min-w-0 flex-1 text-sm font-medium text-gray-800 whitespace-pre-wrap">{{ displayVal(request.description) }}</span>
            </div>
          </section>

          <!-- Team: label + value on same row, 4 columns -->
          <section class="mb-5">
            <h2 class="mb-3 text-sm font-semibold text-gray-900">Team</h2>
            <div class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-4">
              <div class="flex items-baseline gap-2">
                <span class="shrink-0 text-xs font-medium text-gray-500">Sales Agent</span>
                <span class="text-sm font-medium text-gray-800">{{ displayVal(request.sales_agent_name) }}</span>
              </div>
              <div class="flex items-baseline gap-2">
                <span class="shrink-0 text-xs font-medium text-gray-500">Team Leader</span>
                <span class="text-sm font-medium text-gray-800">{{ displayVal(request.team_leader_name) }}</span>
              </div>
              <div class="flex items-baseline gap-2">
                <span class="shrink-0 text-xs font-medium text-gray-500">Manager</span>
                <span class="text-sm font-medium text-gray-800">{{ displayVal(request.manager_name) }}</span>
              </div>
              <div class="flex items-baseline gap-2">
                <span class="shrink-0 text-xs font-medium text-gray-500">Back Office Executive</span>
                <span class="text-sm font-medium text-gray-800">{{ displayVal(request.back_office_executive_name) }}</span>
              </div>
            </div>
          </section>

          <!-- Status & Documents: one row, compact -->
          <section class="mb-5">
            <h2 class="mb-3 text-sm font-semibold text-gray-900">Status & Documents</h2>
            <div class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
              <div class="flex items-baseline gap-2">
                <span class="shrink-0 text-xs font-medium text-gray-500">Status</span>
                <span
                  v-if="request.status"
                  :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium capitalize', statusBadgeClass(request.status)]"
                >
                  {{ request.status }}
                </span>
                <span v-else class="text-sm text-gray-500">—</span>
              </div>
              <div class="flex items-start gap-2">
                <span class="shrink-0 text-xs font-medium text-gray-500">Documents</span>
                <div class="min-w-0 flex-1">
                  <template v-if="request.documents?.length">
                    <ul class="list-inside list-disc text-sm text-gray-800">
                      <li v-for="doc in request.documents" :key="doc.id" class="truncate max-w-md" :title="docDisplayName(doc)">
                        {{ docDisplayName(doc) }}
                      </li>
                    </ul>
                  </template>
                  <span v-else class="text-sm text-gray-500">No documents</span>
                </div>
              </div>
            </div>
          </section>

          <!-- Created By: one row -->
          <section class="mb-2">
            <h2 class="mb-3 text-sm font-semibold text-gray-900">Created By</h2>
            <div class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
              <div class="flex items-baseline gap-2">
                <span class="shrink-0 text-xs font-medium text-gray-500">Creator</span>
                <span class="text-sm font-medium text-gray-800">{{ displayVal(request.creator_name) }}</span>
              </div>
              <div class="flex items-baseline gap-2">
                <span class="shrink-0 text-xs font-medium text-gray-500">Role</span>
                <span class="text-sm font-medium text-gray-800">{{ displayVal(request.creator_role) }}</span>
              </div>
            </div>
          </section>
        </div>

        <div v-if="request" class="border-t border-gray-200 px-4 py-4 text-right sm:px-5">
          <button
            type="button"
            class="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
            @click="goBack"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
