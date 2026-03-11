<script setup>
/**
 * Customer Support Request Details – read-only view matching Lead Submission Details design.
 */
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import customerSupportApi from '@/services/customerSupportApi'
import api from '@/lib/axios'
import TruncatedText from '@/components/TruncatedText.vue'
import { formatUserDate, formatSystemDateTime } from '@/lib/dateFormat'

const route = useRoute()
const router = useRouter()

const loading = ref(true)
const submission = ref(null)
const audits = ref([])
const auditsLoading = ref(false)
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

function displayVal(val) {
  return val != null && val !== '' ? String(val) : '—'
}

const MONTH_NAMES = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']
function formatDate(d) {
  return formatUserDate(d, '—')
}

const DATE_PATTERN = /^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?/

function formatDateTime(d) {
  return formatSystemDateTime(d, '—')
}

function formatAuditSingleValue(val) {
  if (val == null || val === '') return null
  const s = String(val)
  if (DATE_PATTERN.test(s)) {
    return formatSystemDateTime(s, s)
  }
  return s
}

function prettifyKey(key) {
  return String(key).replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}

const AUDIT_SKIP_KEYS = ['id', 'created_at', 'updated_at', 'deleted_at', 'pivot', '_token', 'password', 'remember_token']

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
      const label = prettifyKey(k)
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
      const parsed = JSON.parse(s)
      return formatAuditObject(parsed)
    } catch {}
  }
  return formatAuditSingleValue(s)
}

function formatStatus(status) {
  if (status == null || status === '') return '—'
  if (String(status).toLowerCase() === 'unassigned') return 'UnAssigned'
  const s = String(status).replace(/_/g, ' ')
  return s.charAt(0).toUpperCase() + s.slice(1)
}

const FIELD_LABELS = {
  issue_category: 'Issue Category',
  company_name: 'Company Name as per Trade License',
  account_number: 'Account Number',
  contact_number: 'Contact Number',
  alternate_contact_number: 'Alternate Contact Number',
  issue_description: 'Issue Description',
  status: 'Status',
  manager_id: 'Manager',
  team_leader_id: 'Team Leader',
  sales_agent_id: 'Sales Agent',
  csr_id: 'CSR',
  csr_name: 'CSR Name',
  ticket_number: 'AH Ticket ID',
  workflow_status: 'SLA Status',
  completion_date: 'Completion Date',
  trouble_ticket: 'Trouble Ticket',
  activity: 'Activity',
  pending: 'Pending With',
  resolution_remarks: 'Resolution Remarks',
  internal_remarks: 'Internal Remarks',
  submitted_at: 'Submitted At',
  back_office_executive_id: 'Back Office Executive',
  attachments: 'Attachments',
}

function fieldLabel(name, row) {
  if (row?.field_label) return row.field_label
  return FIELD_LABELS[name] || name?.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()) || '—'
}

function attachmentDisplayName(att) {
  return att?.file_name ?? att?.original_name ?? 'Attachment'
}

function formatFileSize(bytes) {
  if (bytes == null) return '—'
  const b = Number(bytes)
  if (Number.isNaN(b)) return bytes
  if (b < 1024) return b + ' B'
  if (b < 1024 * 1024) return (b / 1024).toFixed(1) + ' KB'
  return (b / (1024 * 1024)).toFixed(1) + ' MB'
}

async function downloadAttachment(index) {
  if (!submission.value?.id) return
  const att = submission.value.attachments?.[index]
  const name = attachmentDisplayName(att) || 'attachment'
  try {
    const { data } = await api.get(`/customer-support/${submission.value.id}/attachments/${index}/download`, { responseType: 'blob' })
    const url = URL.createObjectURL(data)
    const a = document.createElement('a')
    a.href = url
    a.download = name
    a.rel = 'noopener'
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
  } catch {
    window.open(`/api/customer-support/${submission.value.id}/attachments/${index}/download`, '_blank', 'noopener')
  }
}

async function load() {
  if (!id.value) return
  window.scrollTo(0, 0)
  loading.value = true
  submission.value = null
  try {
    const data = await customerSupportApi.getSubmission(id.value)
    submission.value = data
  } catch {
    submission.value = null
  } finally {
    loading.value = false
    window.scrollTo(0, 0)
  }
  loadAudits()
}

async function loadAudits() {
  if (!id.value) return
  auditsLoading.value = true
  try {
    const res = await customerSupportApi.getAudits(id.value)
    audits.value = res?.data ?? []
  } catch {
    audits.value = []
  } finally {
    auditsLoading.value = false
  }
}

function goToEdit() {
  if (submission.value?.id) router.push(`/customer-support/${submission.value.id}/edit`)
}

onMounted(() => {
  load()
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-gray-100 p-0">
    <div class="w-full">
      <!-- Header + Breadcrumb -->
      <div class="mb-4 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div class="flex flex-wrap items-baseline gap-2">
            <h1 class="text-xl font-semibold text-gray-900">Customer Support Request Details</h1>          </div>
          <div class="flex items-center gap-2">
            <router-link
              to="/customer-support"
              class="rounded border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
            >
              Back to List
            </router-link>
            <button
              v-if="submission"
              type="button"
              class="inline-flex items-center rounded bg-brand-primary px-4 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover"
              @click="goToEdit"
            >
              Edit Customer Support Request
            </button>
          </div>
        </div>
      </div>

      <div v-if="loading" class="flex justify-center py-16">
        <svg class="h-10 w-10 animate-spin text-brand-primary" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
      </div>

      <div v-else-if="!submission" class="rounded-lg border border-gray-200 bg-white p-8 text-center text-gray-500">
        Unable to load request. You may not have permission to view it.
      </div>

      <div v-else class="space-y-6">
        <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
          <div class="px-6 py-4">
            <!-- Primary Information -->
            <section class="mb-6">
              <h2 class="mb-3 text-sm font-semibold text-gray-900">Primary Information</h2>
              <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div>
                  <label class="block text-xs font-medium text-gray-500">Company Name as per Trade License</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(submission.company_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Account Number</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(submission.account_number) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Contact Number</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(submission.contact_number) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Alternate Contact Number</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(submission.alternate_contact_number) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Issue Category</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(submission.issue_category) }}</div>
                </div>
              </div>
            </section>

            <!-- Team Information -->
            <section class="mb-6">
              <h2 class="mb-3 text-sm font-semibold text-gray-900">Team Information</h2>
              <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div>
                  <label class="block text-xs font-medium text-gray-500">Submitter Name</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(submission.creator_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Manager Name</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(submission.manager_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Team Leader Name</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(submission.team_leader_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Sales Agent Name</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(submission.sales_agent_name) }}</div>
                </div>
                <div v-if="submission.account_csr_names?.length" class="sm:col-span-2">
                  <label class="block text-xs font-medium text-gray-500">Account CSRs</label>
                  <div class="mt-0.5 flex flex-wrap gap-1.5 rounded border border-gray-200 bg-gray-50 px-3 py-2">
                    <span
                      v-for="(name, idx) in submission.account_csr_names"
                      :key="idx"
                      class="inline-flex items-center rounded-full bg-brand-primary-light px-2.5 py-0.5 text-xs font-medium text-brand-primary-hover"
                    >{{ name }}</span>
                  </div>
                </div>
              </div>
            </section>

            <!-- Issue Details -->
            <section class="mb-6">
              <h2 class="mb-3 text-sm font-semibold text-gray-900">Issue Details</h2>
              <div class="grid grid-cols-1 gap-3">
                <div>
                  <label class="block text-xs font-medium text-gray-500">Issue Description</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 whitespace-pre-wrap">{{ displayVal(submission.issue_description) }}</div>
                </div>
              </div>
            </section>

            <!-- Additional Information (CSR) -->
            <section class="mb-6">
              <h2 class="mb-3 text-sm font-semibold text-gray-900">Additional Information (CSR)</h2>
              <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div>
                  <label class="block text-xs font-medium text-gray-500">Ticket Number</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(submission.ticket_number) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Status</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ formatStatus(submission.status) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">CSR Name</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(submission.csr_user_name ?? submission.csr_user?.name ?? submission.csr_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Trouble Ticket</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(submission.trouble_ticket) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Activity</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(submission.activity) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Completion Date</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ formatDate(submission.completion_date) }}</div>
                </div>
              </div>
              <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                  <label class="block text-xs font-medium text-gray-500">Resolution Remarks</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 whitespace-pre-wrap">{{ displayVal(submission.resolution_remarks) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Internal Remarks</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 whitespace-pre-wrap">{{ displayVal(submission.internal_remarks) }}</div>
                </div>
              </div>
            </section>

            <!-- Change History -->
            <section class="mb-6">
              <h2 class="mb-3 text-sm font-semibold text-gray-900">Change History</h2>
              <p class="mb-3 text-xs text-gray-500">All field changes with previous value, new value, date/time and who made the change.</p>
              <div v-if="auditsLoading" class="flex justify-center py-6">
                <svg class="h-6 w-6 animate-spin text-brand-primary" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
              </div>
              <div v-else-if="audits.length === 0" class="rounded-lg border border-gray-200 bg-gray-50 py-6 text-center text-sm text-gray-500">No changes recorded yet.</div>
              <template v-else>
                <div class="overflow-x-auto rounded-lg border border-gray-200">
                  <table class="min-w-full text-left text-sm">
                    <thead class="bg-brand-primary border-b-2 border-green-700">
                      <tr>
                        <th class="px-4 py-2 font-semibold text-gray-900">Field</th>
                        <th class="px-4 py-2 font-semibold text-gray-900">Old Value</th>
                        <th class="px-4 py-2 font-semibold text-gray-900">New Value</th>
                        <th class="px-4 py-2 font-semibold text-gray-900">Date &amp; Time</th>
                        <th class="px-4 py-2 font-semibold text-gray-900">Changed By</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                      <tr v-for="a in paginatedAudits" :key="a.id" class="hover:bg-gray-50/50">
                        <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-800">{{ fieldLabel(a.field_name, a) }}</td>
                        <td class="max-w-[350px] px-4 py-2 text-gray-600"><TruncatedText :text="a.old_value != null && a.old_value !== '' ? formatAuditValue(a.old_value) : ''" empty-label="empty" /></td>
                        <td class="max-w-[350px] px-4 py-2 text-gray-600"><TruncatedText :text="a.new_value != null && a.new_value !== '' ? formatAuditValue(a.new_value) : ''" empty-label="—" /></td>
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

            <!-- Attachments -->
            <section>
              <h2 class="mb-3 text-sm font-semibold text-gray-900">Documents</h2>
              <div v-if="submission.attachments && submission.attachments.length" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div
                  v-for="(att, idx) in submission.attachments"
                  :key="idx"
                  class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-100 p-3"
                >
                  <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-red-600 text-sm font-bold text-white">D</div>
                  <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-gray-900" :title="attachmentDisplayName(att)">{{ attachmentDisplayName(att) }}</p>
                    <p class="text-xs text-gray-500">{{ formatFileSize(att.file_size ?? att.size) }}</p>
                  </div>
                  <button
                    type="button"
                    class="shrink-0 rounded p-1.5 text-brand-primary hover:bg-brand-primary-light hover:text-brand-primary-hover"
                    title="Download"
                    @click="downloadAttachment(idx)"
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
                to="/customer-support"
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
