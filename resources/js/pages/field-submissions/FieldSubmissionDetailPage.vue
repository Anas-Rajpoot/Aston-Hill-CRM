<script setup>
/**
 * Field Submission Details – read-only view matching Lead Submission detail design.
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
const audits = ref([])
const auditPage = ref(1)
const auditPerPage = 10
const auditTotalPages = computed(() => Math.max(1, Math.ceil(audits.value.length / auditPerPage)))
const paginatedAudits = computed(() => {
  const start = (auditPage.value - 1) * auditPerPage
  return audits.value.slice(start, start + auditPerPage)
})

const id = computed(() => {
  const p = route.params.id
  return p != null ? Number(p) : null
})

const canEdit = computed(() => (auth.user?.permissions ?? []).includes('field_head.view') || (auth.user?.permissions ?? []).includes('field_head.list'))

function displayVal(val) {
  return val != null && val !== '' ? String(val) : '—'
}

const MONTH_NAMES = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']

function formatDate(d) {
  if (!d) return '—'
  const date = new Date(d)
  if (Number.isNaN(date.getTime())) return '—'
  const day = String(date.getDate()).padStart(2, '0')
  const mon = MONTH_NAMES[date.getMonth()]
  const year = date.getFullYear()
  return `${day}-${mon}-${year}`
}

function formatDateTime(d) {
  if (!d) return '—'
  const date = new Date(d)
  if (Number.isNaN(date.getTime())) return '—'
  const day = String(date.getDate()).padStart(2, '0')
  const mon = MONTH_NAMES[date.getMonth()]
  const year = date.getFullYear()
  const h = String(date.getHours()).padStart(2, '0')
  const m = String(date.getMinutes()).padStart(2, '0')
  return `${day}-${mon}-${year} ${h}:${m}`
}

function formatStatus(status) {
  if (status == null || status === '') return '—'
  const s = String(status).replace(/_/g, ' ')
  return s.charAt(0).toUpperCase() + s.slice(1).toLowerCase()
}

function formatFileSize(bytes) {
  if (bytes == null) return '—'
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
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

const FIELD_LABELS = {
  company_name: 'Company Name',
  contact_number: 'Contact Number',
  product: 'Product',
  alternate_number: 'Alternate Contact Number',
  emirates: 'Emirates',
  complete_address: 'Complete Address',
  location_coordinates: 'Location Coordinates',
  additional_notes: 'Additional Notes',
  special_instruction: 'Special Instruction',
  manager_id: 'Manager',
  team_leader_id: 'Team Leader',
  sales_agent_id: 'Sales Agent',
  field_executive_id: 'Field Agent',
  field_status: 'Status',
  meeting_date: 'Meeting Date',
  status: 'Status',
  remarks_by_field_agent: 'Remarks by Field Agent',
  manager_name: 'Manager Name',
  team_leader_name: 'Team Leader Name',
  sales_agent_name: 'Sales Agent Name',
  field_executive_name: 'Field Agent Name',
  creator_name: 'Created By',
  original_name: 'File Name',
  doc_key: 'Document Type',
  file_path: 'File Path',
  size: 'Size',
  mime_type: 'File Type',
}

function fieldLabel(fieldName) {
  if (!fieldName) return '—'
  if (FIELD_LABELS[fieldName]) return FIELD_LABELS[fieldName]
  return String(fieldName).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

const DATE_PATTERN = /^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?/
function formatAuditSingleValue(val) {
  if (val == null || val === '') return null
  const s = String(val)
  if (DATE_PATTERN.test(s)) {
    const date = new Date(s)
    if (!Number.isNaN(date.getTime())) return formatDateTime(date)
  }
  return s
}

const AUDIT_SKIP_KEYS = ['id', 'created_at', 'updated_at', 'deleted_at', 'pivot', '_token', 'password', 'remember_token', 'user_agent', 'ip_address', 'file_path', 'mime_type']

function formatAuditObject(obj) {
  if (obj == null) return 'empty'
  if (Array.isArray(obj)) {
    if (obj.length === 0) return 'empty'
    return obj.map((item) => {
      if (typeof item === 'object' && item !== null) return formatAuditObject(item)
      return formatAuditSingleValue(item) ?? 'empty'
    }).join('\n')
  }
  if (typeof obj === 'object') {
    const entries = Object.entries(obj).filter(([k]) => !AUDIT_SKIP_KEYS.includes(k))
    if (entries.length === 0) return 'empty'
    return entries.map(([k, v]) => {
      const label = fieldLabel(k)
      if (v != null && typeof v === 'object') return `${label}: ${formatAuditObject(v)}`
      return `${label}: ${formatAuditSingleValue(v) ?? 'empty'}`
    }).join('\n')
  }
  return formatAuditSingleValue(obj) ?? 'empty'
}

function formatAuditValue(val) {
  if (val == null || val === '') return null
  const s = String(val).trim()
  if ((s.startsWith('{') && s.endsWith('}')) || (s.startsWith('[') && s.endsWith(']'))) {
    try {
      let parsed = JSON.parse(s)
      if (typeof parsed === 'string') {
        try { parsed = JSON.parse(parsed) } catch {}
      }
      return formatAuditObject(parsed)
    } catch {}
  }
  return formatAuditSingleValue(s)
}

async function load() {
  if (!id.value) return
  window.scrollTo(0, 0)
  loading.value = true
  submission.value = null
  try {
    const res = await fieldSubmissionsApi.getSubmission(id.value)
    submission.value = res?.data ?? res
    if (submission.value?.id) {
      const auditRes = await fieldSubmissionsApi.getAudits(id.value)
      audits.value = auditRes?.data ?? []
    } else {
      audits.value = []
    }
  } catch {
    submission.value = null
    audits.value = []
  } finally {
    loading.value = false
    window.scrollTo(0, 0)
  }
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
    <div class="w-full">
      <!-- Header card (separate from content, like Lead Submission) -->
      <div class="mb-4 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div class="flex flex-wrap items-baseline gap-2">
            <h1 class="text-xl font-semibold text-gray-900">Field Submission Details</h1>
            <Breadcrumbs />
          </div>
          <div class="flex items-center gap-2">
            <router-link
              to="/field-submissions"
              class="rounded border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
            >
              Back to List
            </router-link>
            <button
              type="button"
              class="inline-flex items-center rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
              :disabled="!submission?.id"
              @click="goToEdit"
            >
              Edit Field Submission
            </button>
          </div>
        </div>
      </div>

      <div v-if="loading" class="flex justify-center py-16">
        <svg class="h-10 w-10 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
      </div>

      <div v-else-if="!submission" class="rounded-lg border border-gray-200 bg-white p-8 text-center text-gray-500">
        Unable to load submission. You may not have permission to view it.
      </div>

      <div v-else class="space-y-6">
        <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
          <div class="px-6 py-4">
            <!-- Primary Information -->
            <section class="mb-6">
              <h2 class="mb-3 text-sm font-semibold text-gray-900">Primary Information</h2>
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                  <label class="block text-xs font-medium text-gray-500">Submission ID</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ submissionId(submission) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Company Name</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(submission.company_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Product</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(submission.product) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Contact Number</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(submission.contact_number) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Alternate Contact Number</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(submission.alternate_number) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Emirates</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(submission.emirates) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Location Coordinates</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(submission.location_coordinates) }}</div>
                </div>
                <div class="sm:col-span-2">
                  <label class="block text-xs font-medium text-gray-500">Complete Address</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(submission.complete_address) }}</div>
                </div>
              </div>
              <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                  <label class="block text-xs font-medium text-gray-500">Additional Notes</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 whitespace-pre-wrap">{{ displayVal(submission.additional_notes) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Any Special Instruction</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 whitespace-pre-wrap">{{ displayVal(submission.special_instruction) }}</div>
                </div>
              </div>
            </section>

            <!-- Team Information -->
            <section class="mb-6">
              <h2 class="mb-3 text-sm font-semibold text-gray-900">Team Information</h2>
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                  <label class="block text-xs font-medium text-gray-500">Manager</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(submission.manager_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Team Leader</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(submission.team_leader_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Sales Agent</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(submission.sales_agent_name) }}</div>
                </div>
              </div>
              <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                  <label class="block text-xs font-medium text-gray-500">Field Agent</label>
                  <div
                    class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm"
                    :class="fieldAgentDisplay(submission) === 'Unassigned' ? 'text-red-600 font-medium' : 'text-gray-800'"
                  >
                    {{ fieldAgentDisplay(submission) }}
                  </div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Created By</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(submission.creator_name) }}</div>
                </div>
              </div>
            </section>

            <!-- Status & Timeline -->
            <section class="mb-6">
              <h2 class="mb-3 text-sm font-semibold text-gray-900">Status & Timeline</h2>
              <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div>
                  <label class="block text-xs font-medium text-gray-500">Field Status</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
                    <span
                      v-if="submission.field_status"
                      :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusBadgeClass(submission.field_status)]"
                    >
                      {{ submission.field_status }}
                    </span>
                    <span v-else class="text-gray-500">—</span>
                  </div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Meeting Date</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ submission.meeting_date ? formatDateTime(submission.meeting_date) : '—' }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">SLA Timer</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm" :class="slaTimerClass(slaTimerText, slaStatusText)">
                    {{ slaTimerText ?? '—' }}
                  </div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">SLA Status</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
                    <span
                      v-if="slaStatusText"
                      :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusBadgeClass(slaStatusText)]"
                    >
                      {{ slaStatusText }}
                    </span>
                    <span v-else class="text-gray-500">—</span>
                  </div>
                </div>
              </div>
            </section>

            <!-- Notes & Remarks -->
            <section class="mb-6">
              <h2 class="mb-3 text-sm font-semibold text-gray-900">Notes & Remarks</h2>
              <div>
                <label class="block text-xs font-medium text-gray-500">Remarks by Field Agent</label>
                <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 whitespace-pre-wrap">{{ displayVal(submission.remarks_by_field_agent) }}</div>
              </div>
            </section>

            <!-- Change History -->
            <section class="mb-6">
              <h2 class="mb-3 text-sm font-semibold text-gray-900">Change History</h2>
              <p class="mb-3 text-xs text-gray-500">All field changes with previous value, new value, date/time and who made the change.</p>
              <div v-if="audits.length === 0" class="rounded-lg border border-gray-200 bg-gray-50 py-6 text-center text-sm text-gray-500">No changes recorded yet.</div>
              <template v-else>
                <div class="overflow-x-auto rounded-lg border border-gray-200">
                  <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-gray-200 bg-gray-100">
                      <tr>
                        <th class="px-4 py-2 font-semibold text-gray-900">Field</th>
                        <th class="px-4 py-2 font-semibold text-gray-900">Old Value</th>
                        <th class="px-4 py-2 font-semibold text-gray-900">New Value</th>
                        <th class="px-4 py-2 font-semibold text-gray-900">Date & Time</th>
                        <th class="px-4 py-2 font-semibold text-gray-900">Changed By</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                      <tr v-for="a in paginatedAudits" :key="a.id" class="hover:bg-gray-50/50">
                        <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-800">{{ fieldLabel(a.field_name) }}</td>
                        <td class="max-w-[350px] whitespace-pre-wrap break-words px-4 py-2 text-gray-600">{{ a.old_value != null && a.old_value !== '' ? formatAuditValue(a.old_value) : 'empty' }}</td>
                        <td class="max-w-[350px] whitespace-pre-wrap break-words px-4 py-2 text-gray-600">{{ a.new_value != null && a.new_value !== '' ? formatAuditValue(a.new_value) : '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ a.changed_at ? formatDateTime(a.changed_at) : '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ a.changed_by_name ?? '—' }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div v-if="auditTotalPages > 1" class="mt-3 flex items-center justify-between">
                  <p class="text-xs text-gray-500">
                    Showing {{ (auditPage - 1) * auditPerPage + 1 }}–{{ Math.min(auditPage * auditPerPage, audits.length) }} of {{ audits.length }} changes
                  </p>
                  <div class="flex items-center gap-1.5">
                    <button type="button" :disabled="auditPage <= 1" class="rounded border border-gray-300 bg-white px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50" @click="auditPage--">Previous</button>
                    <span class="rounded border border-gray-300 bg-gray-50 px-3 py-1 text-xs text-gray-700">Page {{ auditPage }} of {{ auditTotalPages }}</span>
                    <button type="button" :disabled="auditPage >= auditTotalPages" class="rounded border border-gray-300 bg-white px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50" @click="auditPage++">Next</button>
                  </div>
                </div>
              </template>
            </section>

            <!-- Documents -->
            <section>
              <h2 class="mb-3 text-sm font-semibold text-gray-900">Documents</h2>
              <div v-if="submission.documents && submission.documents.length" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div
                  v-for="doc in submission.documents"
                  :key="doc.id"
                  class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-100 p-3"
                >
                  <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-red-600 text-sm font-bold text-white">D</div>
                  <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-gray-900" :title="docDisplayName(doc)">{{ docDisplayName(doc) }}</p>
                    <p class="text-xs text-gray-500">{{ formatFileSize(doc.size) }}</p>
                  </div>
                  <button
                    type="button"
                    class="shrink-0 rounded p-1.5 text-blue-600 hover:bg-blue-50 hover:text-blue-700"
                    title="Download"
                    @click="downloadDoc(doc)"
                  >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                  </button>
                </div>
              </div>
              <div v-else class="rounded-lg border border-gray-200 bg-gray-50 py-8 text-center text-sm text-gray-500">No documents uploaded.</div>
            </section>

            <!-- Close Button -->
            <div class="mt-6 flex justify-end border-t border-gray-200 pt-4">
              <router-link
                to="/field-submissions"
                class="rounded-lg border border-gray-300 bg-white px-6 py-2.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
              >
                Close
              </router-link>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
