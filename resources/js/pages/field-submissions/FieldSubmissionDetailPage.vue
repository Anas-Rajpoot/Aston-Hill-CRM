<script setup>
/**
 * Field Submission Details – read-only view matching design: Basic Info, Location & Product, Team Assignment, Status & Timeline, Notes.
 */
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import fieldSubmissionsApi from '@/services/fieldSubmissionsApi'
import { useAuthStore } from '@/stores/auth'
import Breadcrumbs from '@/components/Breadcrumbs.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const loading = ref(true)
const submission = ref(null)

const id = computed(() => {
  const p = route.params.id
  return p != null ? Number(p) : null
})

const canEdit = computed(() => (auth.user?.permissions ?? []).includes('field_head.view') || (auth.user?.permissions ?? []).includes('field_head.list'))

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

function submissionId(s) {
  if (!s?.id) return '—'
  const year = s.created_at ? new Date(s.created_at).getFullYear() : new Date().getFullYear()
  const num = String(s.id).padStart(3, '0')
  return `FLD-${year}-${num}`
}

const completedStatuses = ['Survey Completed', 'Completed', 'Visited']

function slaTimer(s) {
  if (!s) return null
  if (completedStatuses.includes(s.field_status)) return 'Completed'
  if (!s.meeting_date) return null
  const meetingDate = new Date(s.meeting_date)
  const targetEnd = new Date(meetingDate)
  targetEnd.setHours(23, 59, 59, 999)
  const now = new Date()
  if (targetEnd < now) {
    const totalMins = Math.floor((now - targetEnd) / 60000)
    const h = Math.floor(totalMins / 60)
    const m = totalMins % 60
    return `Breached by ${h}h ${m}m`
  }
  const totalMins = Math.floor((targetEnd - now) / 60000)
  const h = Math.floor(totalMins / 60)
  const m = totalMins % 60
  return `${h}h ${m}m remaining`
}

function slaStatus(s) {
  if (!s) return null
  if (completedStatuses.includes(s.field_status)) return 'Completed'
  if (!s.meeting_date) return null
  const meetingDate = new Date(s.meeting_date)
  const targetEnd = new Date(meetingDate)
  targetEnd.setHours(23, 59, 59, 999)
  const now = new Date()
  if (targetEnd < now) return 'Breached'
  const hoursRemaining = (targetEnd - now) / (1000 * 60 * 60)
  return hoursRemaining <= 4 ? 'Approaching' : 'On Time'
}

function slaTimerClass(timer, status) {
  if (!timer) return 'text-gray-500'
  if (timer === 'Completed' || status === 'Completed') return 'text-green-600 font-medium'
  if (status === 'Breached') return 'text-red-600 font-medium'
  if (status === 'Approaching') return 'text-amber-600 font-medium'
  return 'text-green-600'
}

const fieldStatusBadgeClass = {
  'Pending Assignment': 'bg-gray-100 text-gray-700',
  'Site Survey Scheduled': 'bg-blue-100 text-blue-700',
  'Survey Completed': 'bg-green-100 text-green-700',
  'In Progress': 'bg-amber-100 text-amber-800',
  'Installation Scheduled': 'bg-blue-100 text-blue-700',
  'Completed': 'bg-green-100 text-green-700',
  'Meeting Scheduled': 'bg-blue-100 text-blue-700',
  'Visited': 'bg-green-100 text-green-700',
  'Cancelled': 'bg-red-100 text-red-700',
  'Rescheduled': 'bg-amber-100 text-amber-800',
  'No Show': 'bg-gray-100 text-gray-600',
}

function statusBadgeClass(fieldStatus) {
  return fieldStatusBadgeClass[fieldStatus] ?? 'bg-gray-100 text-gray-700'
}

function fieldAgentDisplay(s) {
  return s?.field_executive_name ?? 'Unassigned'
}

function docDisplayName(doc) {
  return doc?.original_name || doc?.label || doc?.doc_key || 'Document'
}

async function load() {
  if (!id.value) return
  window.scrollTo(0, 0)
  loading.value = true
  submission.value = null
  try {
    const res = await fieldSubmissionsApi.getSubmission(id.value)
    submission.value = res?.data ?? res
  } catch {
    submission.value = null
  } finally {
    loading.value = false
    window.scrollTo(0, 0)
  }
}

function goBack() {
  router.push('/field-submissions')
}

function goToEdit() {
  if (submission.value?.id) router.push(`/field-submissions/${submission.value.id}/edit`)
}

async function downloadDoc(doc) {
  if (!submission.value?.id || !doc?.id) return
  try {
    const blob = await fieldSubmissionsApi.downloadDocument(submission.value.id, doc.id)
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = docDisplayName(doc)
    a.click()
    URL.revokeObjectURL(url)
  } catch {}
}

const slaTimerText = computed(() => submission.value ? slaTimer(submission.value) : null)
const slaStatusText = computed(() => submission.value ? slaStatus(submission.value) : null)

onMounted(() => load())
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-[#f0f2f5] p-0">
    <div class="mx-auto max-w-7xl px-1 sm:px-2">
      <!-- Single white card: heading + breadcrumbs + detail (same background, thin border between) -->
      <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
        <!-- Heading + breadcrumbs: same white background -->
        <div class="px-4 py-4 sm:px-5">
          <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-baseline gap-2">
              <h1 class="text-xl font-semibold text-gray-900">Field Submission Details</h1>
              <Breadcrumbs />
            </div>
            <div class="flex items-center gap-2">
              <button
                v-if="canEdit"
                type="button"
                class="rounded bg-green-500 px-4 py-2 text-sm font-medium text-white hover:bg-green-600"
                @click="goToEdit"
              >
                Edit Submission
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

        <!-- Thin border between heading area and detail part -->
        <div class="border-t border-gray-200" />

        <div v-if="loading" class="flex justify-center px-4 py-16 sm:px-5">
          <svg class="h-10 w-10 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
        </div>

        <div v-else-if="!submission" class="px-4 py-8 text-center text-gray-500 sm:px-5">
          Unable to load submission. You may not have permission to view it.
        </div>

        <div v-else class="px-4 py-5 sm:px-5">
          <!-- Basic Information -->
          <section class="mb-6">
            <h2 class="mb-3 text-sm font-semibold text-gray-900">Basic Information</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <div>
                <label class="block text-xs font-medium text-gray-500">Submission ID</label>
                <div class="mt-1 text-sm font-medium text-gray-800">{{ submissionId(submission) }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500">Submission Date</label>
                <div class="mt-1 text-sm font-medium text-gray-800">{{ formatDateTime(submission.submitted_at) }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500">Company Name</label>
                <div class="mt-1 text-sm font-medium text-gray-800">{{ displayVal(submission.company_name) }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500">Contact Number</label>
                <div class="mt-1 text-sm font-medium text-gray-800">{{ displayVal(submission.contact_number) }}</div>
              </div>
            </div>
          </section>

          <!-- Location & Product -->
          <section class="mb-6">
            <h2 class="mb-3 text-sm font-semibold text-gray-900">Location & Product</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <div>
                <label class="block text-xs font-medium text-gray-500">Emirates</label>
                <div class="mt-1 text-sm font-medium text-gray-800">{{ displayVal(submission.emirates) }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500">Area</label>
                <div class="mt-1 text-sm font-medium text-gray-800">{{ displayVal(submission.complete_address) }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500">Product</label>
                <div class="mt-1 text-sm font-medium text-gray-800">{{ displayVal(submission.product) }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500">Location Coordinates</label>
                <div class="mt-1 text-sm font-medium text-gray-800">{{ displayVal(submission.location_coordinates) }}</div>
              </div>
            </div>
          </section>

          <!-- Team Assignment -->
          <section class="mb-6">
            <h2 class="mb-3 text-sm font-semibold text-gray-900">Team Assignment</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <div>
                <label class="block text-xs font-medium text-gray-500">Sales Agent</label>
                <div class="mt-1 text-sm font-medium text-gray-800">{{ displayVal(submission.sales_agent_name) }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500">Team Leader</label>
                <div class="mt-1 text-sm font-medium text-gray-800">{{ displayVal(submission.team_leader_name) }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500">Manager</label>
                <div class="mt-1 text-sm font-medium text-gray-800">{{ displayVal(submission.manager_name) }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500">Field Agent</label>
                <div
                  :class="fieldAgentDisplay(submission) === 'Unassigned' ? 'mt-1 text-sm font-medium text-red-600' : 'mt-1 text-sm font-medium text-gray-800'"
                >
                  {{ fieldAgentDisplay(submission) }}
                </div>
              </div>
            </div>
          </section>

          <!-- Status & Timeline -->
          <section class="mb-6">
            <h2 class="mb-3 text-sm font-semibold text-gray-900">Status & Timeline</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <div>
                <label class="block text-xs font-medium text-gray-500">Status</label>
                <div class="mt-1">
                  <span
                    v-if="submission.field_status"
                    :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusBadgeClass(submission.field_status)]"
                  >
                    {{ submission.field_status }}
                  </span>
                  <span v-else class="text-sm text-gray-500">—</span>
                </div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500">Meeting Date</label>
                <div class="mt-1 text-sm font-medium text-gray-800">
                  {{ submission.meeting_date ? submission.meeting_date : '—' }}
                </div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500">SLA Timer</label>
                <div :class="['mt-1 text-sm font-medium', slaTimerClass(slaTimerText, slaStatusText)]">
                  {{ slaTimerText ?? '—' }}
                </div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500">SLA Status</label>
                <div class="mt-1">
                  <span
                    v-if="slaStatusText"
                    :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusBadgeClass(slaStatusText)]"
                  >
                    {{ slaStatusText }}
                  </span>
                  <span v-else class="text-sm text-gray-500">—</span>
                </div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500">Last Updated</label>
                <div class="mt-1 text-sm font-medium text-gray-800">{{ formatDateTime(submission.updated_at) }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500">Photographic Proof</label>
                <div class="mt-1">
                  <template v-if="submission.documents?.length">
                    <div class="flex flex-wrap gap-2">
                      <button
                        v-for="doc in submission.documents"
                        :key="doc.id"
                        type="button"
                        class="text-sm font-medium text-blue-600 hover:underline"
                        @click="downloadDoc(doc)"
                      >
                        {{ docDisplayName(doc) }}
                      </button>
                    </div>
                  </template>
                  <span v-else class="text-sm font-medium text-gray-800">Not Available</span>
                </div>
              </div>
            </div>
          </section>

          <!-- Notes & Remarks (three in one row) -->
          <section class="mb-2">
            <h2 class="mb-3 text-sm font-semibold text-gray-900">Notes & Remarks</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <div>
                <label class="block text-xs font-medium text-gray-500">Remarks / Comment</label>
                <div class="mt-1 text-sm font-medium text-gray-800">{{ displayVal(submission.special_instruction) }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500">Additional Notes</label>
                <div class="mt-1 text-sm font-medium text-gray-800">{{ displayVal(submission.additional_notes) }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500">Remarks by Field Agent</label>
                <div class="mt-1 text-sm font-medium text-gray-800">{{ displayVal(submission.remarks_by_field_agent) }}</div>
              </div>
            </div>
          </section>
        </div>

        <div v-if="submission" class="border-t border-gray-200 px-4 py-4 text-right sm:px-5">
          <button
            type="button"
            class="rounded bg-lime-500 px-4 py-2 text-sm font-medium text-white hover:bg-lime-600"
            @click="goBack"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
