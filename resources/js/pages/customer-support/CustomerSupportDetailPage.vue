<script setup>
/**
 * Customer Support Request Details – read-only view of all form fields and CSR/submitted data.
 */
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import customerSupportApi from '@/services/customerSupportApi'
import api from '@/lib/axios'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import { toDdMmYyyy } from '@/lib/dateFormat'

const route = useRoute()
const router = useRouter()

const loading = ref(true)
const submission = ref(null)
const audits = ref([])
const auditsLoading = ref(false)

const id = computed(() => {
  const p = route.params.id
  return p != null ? Number(p) : null
})

function displayVal(val) {
  return val != null && val !== '' ? String(val) : '—'
}

function formatDateTime(d) {
  if (!d) return '—'
  const date = new Date(d)
  if (Number.isNaN(date.getTime())) return '—'
  const dateStr = date.toISOString().slice(0, 10)
  const h = String(date.getHours()).padStart(2, '0')
  const m = String(date.getMinutes()).padStart(2, '0')
  return `${toDdMmYyyy(dateStr) || ''} ${h}:${m}`.trim() || '—'
}

const MONTH_NAMES = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']
const DATE_PATTERN = /^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?/

function formatAuditSingleValue(val) {
  if (val == null || val === '') return null
  const s = String(val)
  if (DATE_PATTERN.test(s)) {
    const date = new Date(s)
    if (!Number.isNaN(date.getTime())) {
      const day = String(date.getDate()).padStart(2, '0')
      const mon = MONTH_NAMES[date.getMonth()]
      const year = date.getFullYear()
      const h = String(date.getHours()).padStart(2, '0')
      const m = String(date.getMinutes()).padStart(2, '0')
      return `${day}-${mon}-${year} ${h}:${m}`
    }
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
  return String(status).charAt(0).toUpperCase() + String(status).slice(1).toLowerCase()
}

function attachmentDisplayName(att) {
  return att?.file_name ?? att?.original_name ?? 'Attachment'
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
    // 404 or network – user may open in new tab as fallback
    window.open(`/api/customer-support/${submission.value.id}/attachments/${index}/download`, '_blank', 'noopener')
  }
}

const FIELD_LABELS = {
  issue_category: 'Issue Category',
  company_name: 'Company Name',
  account_number: 'Account Number',
  contact_number: 'Contact Number',
  issue_description: 'Issue Description',
  status: 'Status',
  manager_id: 'Manager',
  team_leader_id: 'Team Leader',
  sales_agent_id: 'Sales Agent',
  csr_id: 'CSR',
  csr_name: 'CSR Name',
  ticket_number: 'Ticket ID',
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

async function load() {
  if (!id.value) return
  loading.value = true
  submission.value = null
  try {
    const data = await customerSupportApi.getSubmission(id.value)
    submission.value = data
  } catch {
    submission.value = null
  } finally {
    loading.value = false
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

function goBack() {
  router.push('/customer-support')
}

function goToEdit() {
  if (submission.value?.id) router.push(`/customer-support/${submission.value.id}/edit`)
}

onMounted(() => {
  load()
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-white p-0">
    <div class="w-full">
      <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
        <!-- Heading + breadcrumbs + Edit button -->
        <div class="px-4 py-4 sm:px-5">
          <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-baseline gap-2">
              <h1 class="text-xl font-semibold text-gray-900">Customer Support Request Details</h1>
              <Breadcrumbs />
            </div>
            <div class="flex items-center gap-2">
              <button
                type="button"
                class="rounded bg-green-500 px-4 py-2 text-sm font-medium text-white hover:bg-green-600"
                :disabled="!submission?.id"
                @click="goToEdit"
              >
                Edit Customer Support Request
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

        <div v-else-if="!submission" class="px-4 py-8 text-center text-gray-500 sm:px-5">
          Unable to load request. You may not have permission to view it.
        </div>

        <div v-else class="px-4 py-5 sm:px-5">
          <!-- Request information (from form) -->
          <section class="mb-6">
            <h2 class="mb-3 text-sm font-semibold text-gray-900">Request Information</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <div>
                <label class="block text-xs font-medium text-gray-500">Submission Date</label>
                <div class="mt-1 text-sm font-medium text-gray-800">{{ formatDateTime(submission.submitted_at) }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500">Status</label>
                <div class="mt-1 text-sm font-medium text-gray-800">{{ formatStatus(submission.status) }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500">Issue Category</label>
                <div class="mt-1 text-sm font-medium text-gray-800">{{ displayVal(submission.issue_category) }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500">Company Name</label>
                <div class="mt-1 text-sm font-medium text-gray-800">{{ displayVal(submission.company_name) }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500">Account Number</label>
                <div class="mt-1 text-sm font-medium text-gray-800">{{ displayVal(submission.account_number) }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500">Contact Number</label>
                <div class="mt-1 text-sm font-medium text-gray-800">{{ displayVal(submission.contact_number) }}</div>
              </div>
            </div>

            <!-- Issue Description (professional card) -->
            <div class="mt-5">
              <label class="block text-xs font-medium uppercase tracking-wide text-gray-500">Issue Description</label>
              <div class="mt-2 rounded-lg border border-gray-200 bg-white px-4 py-4 shadow-sm">
                <p class="text-sm leading-relaxed text-gray-800 whitespace-pre-wrap">{{ displayVal(submission.issue_description) }}</p>
              </div>
            </div>
          </section>

          <!-- Attachments (card layout with download) -->
          <section v-if="submission.attachments?.length" class="mb-6">
            <h2 class="mb-2 text-base font-semibold text-gray-900">Attachments</h2>
            <div class="border-b border-gray-200 pb-3" />
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div
                v-for="(att, idx) in submission.attachments"
                :key="idx"
                class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm"
              >
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-red-50 text-red-600">
                  <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                </div>
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-medium text-gray-900">{{ attachmentDisplayName(att) }}</p>
                  <p v-if="att.file_size" class="mt-0.5 text-xs text-gray-500">{{ att.file_size }}</p>
                </div>
                <button
                  type="button"
                  class="shrink-0 rounded p-2 text-blue-600 hover:bg-blue-50 hover:text-blue-700"
                  :title="'Download ' + attachmentDisplayName(att)"
                  @click="downloadAttachment(idx)"
                >
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                  </svg>
                </button>
              </div>
            </div>
          </section>

          <!-- Team assignment (Manager, Team Leader, Sales Agent) -->
          <section class="mb-6">
            <h2 class="mb-3 text-sm font-semibold text-gray-900">Team Assignment</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <div>
                <label class="block text-xs font-medium text-gray-500">Manager</label>
                <div class="mt-1 text-sm font-medium text-gray-800">{{ displayVal(submission.manager_name) }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500">Team Leader</label>
                <div class="mt-1 text-sm font-medium text-gray-800">{{ displayVal(submission.team_leader_name) }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500">Sales Agent</label>
                <div class="mt-1 text-sm font-medium text-gray-800">{{ displayVal(submission.sales_agent_name) }}</div>
              </div>
            </div>
          </section>

          <!-- Created by (CSR / submitted by) -->
          <section class="mb-6">
            <h2 class="mb-3 text-sm font-semibold text-gray-900">Created By</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <div>
                <label class="block text-xs font-medium text-gray-500">Created By</label>
                <div class="mt-1 text-sm font-medium text-gray-800">{{ displayVal(submission.creator_name) }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500">Created At</label>
                <div class="mt-1 text-sm font-medium text-gray-800">{{ formatDateTime(submission.created_at) }}</div>
              </div>
            </div>
          </section>

          <!-- Change History -->
          <section class="mb-6">
            <h2 class="mb-3 text-sm font-semibold text-gray-900">Change History</h2>
            <p class="mb-3 text-xs text-gray-500">All field changes with previous value, new value, date/time and who made the change.</p>
            <div v-if="auditsLoading" class="flex justify-center py-6">
              <svg class="h-6 w-6 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
            </div>
            <div v-else-if="audits.length === 0" class="rounded-lg border border-gray-200 bg-gray-50 py-6 text-center text-sm text-gray-500">No changes recorded yet.</div>
            <div v-else class="overflow-x-auto rounded-lg border border-gray-200">
              <table class="min-w-full text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-100">
                  <tr>
                    <th class="px-4 py-2 font-semibold text-gray-900">Field</th>
                    <th class="px-4 py-2 font-semibold text-gray-900">Old Value</th>
                    <th class="px-4 py-2 font-semibold text-gray-900">New Value</th>
                    <th class="px-4 py-2 font-semibold text-gray-900">Date &amp; Time</th>
                    <th class="px-4 py-2 font-semibold text-gray-900">Changed By</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                  <tr v-for="a in audits" :key="a.id" class="hover:bg-gray-50/50">
                    <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-800">{{ fieldLabel(a.field_name, a) }}</td>
                    <td class="max-w-[350px] whitespace-pre-wrap break-words px-4 py-2 text-gray-600">{{ a.old_value != null && a.old_value !== '' ? formatAuditValue(a.old_value) : 'empty' }}</td>
                    <td class="max-w-[350px] whitespace-pre-wrap break-words px-4 py-2 text-gray-600">{{ a.new_value != null && a.new_value !== '' ? formatAuditValue(a.new_value) : '—' }}</td>
                    <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ a.changed_at ? formatDateTime(a.changed_at) : '—' }}</td>
                    <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ a.changed_by_name ?? '—' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <!-- Back button at bottom -->
          <div class="flex w-full justify-end border-t border-gray-200 pt-4">
            <button
              type="button"
              class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
              @click="goBack"
            >
              Back
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
