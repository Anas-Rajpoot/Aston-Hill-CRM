<script setup>
/**
 * VAS Request Details – redesigned to match Lead Submission Detail page layout.
 */
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import vasRequestsApi from '@/services/vasRequestsApi'
import { useAuthStore } from '@/stores/auth'
import TruncatedText from '@/components/TruncatedText.vue'
import { formatUserDate, formatSystemDateTime } from '@/lib/dateFormat'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const loading = ref(true)
const request = ref(null)
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

const canEdit = computed(() => {
  const perms = auth.user?.permissions ?? []
  if (perms.includes('vas.edit')) return true
  const roles = auth.user?.roles ?? []
  if (!Array.isArray(roles)) return false
  return roles.some((r) => {
    const name = typeof r === 'string' ? r : r?.name
    return name === 'superadmin' || name === 'back_office' || name === 'backoffice'
  })
})

function displayVal(val) {
  return val != null && val !== '' ? String(val) : '—'
}

const MONTH_NAMES = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']

function formatDate(d) {
  return formatUserDate(d, '—')
}

function formatDateTime(d) {
  return formatSystemDateTime(d, '—')
}

function formatStatus(status) {
  if (status == null || status === '') return '—'
  return String(status).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function docDisplayName(doc) {
  return doc?.label || doc?.file_name || doc?.doc_key || 'Document'
}

function formatFileSize(bytes) {
  if (bytes == null || bytes === '') return '—'
  const n = Number(bytes)
  if (Number.isNaN(n) || n < 0) return '—'
  if (n < 1024) return `${n} B`
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`
  return `${(n / (1024 * 1024)).toFixed(1)} MB`
}

async function downloadDocument(doc) {
  if (!request.value?.id || !doc?.id) return
  const name = docDisplayName(doc) || 'document'
  try {
    const blob = await vasRequestsApi.downloadDocument(request.value.id, doc.id)
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = name
    a.rel = 'noopener'
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
  } catch {
    window.open(`/api/vas-requests/${request.value.id}/documents/${doc.id}/download`, '_blank', 'noopener')
  }
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

function prettifyKey(key) {
  return String(key).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

const DATE_PATTERN = /^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?/

function formatAuditSingleValue(val) {
  if (val == null || val === '') return null
  const s = String(val)
  if (DATE_PATTERN.test(s)) {
    return formatSystemDateTime(s, s)
  }
  return s
}

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
    const entries = Object.entries(obj).filter(([, v]) => v !== undefined)
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
      let parsed = JSON.parse(s)
      if (typeof parsed === 'string') {
        try { parsed = JSON.parse(parsed) } catch {}
      }
      return formatAuditObject(parsed)
    } catch {}
  }
  return formatAuditSingleValue(s)
}

const FIELD_LABELS = {
  company_name: 'Company Name',
  account_number: 'Account Number',
  contact_number: 'Contact Number',
  request_type: 'Request Type',
  request_description: 'Request Description',
  description: 'Description',
  additional_notes: 'Additional Notes',
  status: 'Status',
  manager_id: 'Manager',
  team_leader_id: 'Team Leader',
  sales_agent_id: 'Sales Agent',
  back_office_executive_id: 'Back Office Executive',
  submitted_at: 'Submitted At',
  documents: 'Documents',
}

function fieldLabel(name, row) {
  if (row?.field_label) return row.field_label
  return FIELD_LABELS[name] || name?.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()) || '—'
}

async function loadAudits() {
  if (!id.value) return
  auditsLoading.value = true
  try {
    const res = await vasRequestsApi.getAudits(id.value)
    audits.value = res?.data ?? []
  } catch {
    audits.value = []
  } finally {
    auditsLoading.value = false
  }
}

function goToEdit() {
  if (request.value?.id) router.push(`/vas-requests/${request.value.id}/edit`)
}

onMounted(() => {
  load()
  loadAudits()
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-gray-100 p-0">
    <div class="w-full">
      <!-- Header -->
      <div class="mb-4 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div class="flex flex-wrap items-baseline gap-2">
            <h1 class="text-xl font-semibold text-gray-900">VAS Request Details</h1>          </div>
          <div class="flex items-center gap-2">
            <router-link
              to="/vas-requests"
              class="rounded border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
            >
              Back to List
            </router-link>
            <button
              v-if="canEdit && request"
              type="button"
              class="inline-flex items-center rounded bg-brand-primary px-4 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover"
              @click="goToEdit"
            >
              Edit VAS Request
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

      <div v-else-if="!request" class="rounded-lg border border-gray-200 bg-white p-8 text-center text-gray-500">
        Unable to load request. You may not have permission to view it.
      </div>

      <div v-else class="space-y-6">
        <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
          <div class="px-6 py-4">
            <!-- Primary Information -->
            <section class="mb-6">
              <h2 class="mb-3 text-sm font-semibold text-gray-900">Primary Information</h2>
              <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <div>
                  <label class="block text-xs font-medium text-gray-500">Request Type</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(request.request_type) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Account Number</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(request.account_number) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Contact Number</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(request.contact_number) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Company Name as per Trade License</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(request.company_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Status</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ formatStatus(request.status) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Created</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ formatDateTime(request.created_at) }}</div>
                </div>
              </div>
              <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                  <label class="block text-xs font-medium text-gray-500">Request Description</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 whitespace-pre-wrap">{{ displayVal(request.request_description) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Additional Notes</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 whitespace-pre-wrap">{{ displayVal(request.additional_notes) }}</div>
                </div>
              </div>
            </section>

            <!-- Team Assignment -->
            <section class="mb-6">
              <h2 class="mb-3 text-sm font-semibold text-gray-900">Team Assignment</h2>
              <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div>
                  <label class="block text-xs font-medium text-gray-500">Sales Agent</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(request.sales_agent_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Team Leader</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(request.team_leader_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Manager</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(request.manager_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Back Office Executive</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(request.back_office_executive_name) }}</div>
                </div>
              </div>
            </section>

            <section class="mb-6">
              <h2 class="mb-3 text-sm font-semibold text-gray-900">Back Office Working Section</h2>
              <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div>
                  <label class="block text-xs font-medium text-gray-500">Back Office Executive</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(request.back_office_executive_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Submission Date</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ formatDate(request.submitted_at || request.created_at) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Activity</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 whitespace-pre-wrap">{{ displayVal(request.activity) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Completion Date</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ formatDate(request.completion_date || request.approved_at) }}</div>
                </div>
              </div>
              <div class="mt-3">
                <label class="block text-xs font-medium text-gray-500">Remarks</label>
                <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 whitespace-pre-wrap">{{ displayVal(request.remarks) }}</div>
              </div>
            </section>

            <!-- Documents -->
            <section class="mb-6">
              <h2 class="mb-3 text-sm font-semibold text-gray-900">Documents</h2>
              <div v-if="request.documents?.length" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div
                  v-for="doc in request.documents"
                  :key="doc.id"
                  class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-100 p-3"
                >
                  <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-red-600 text-sm font-bold text-white">D</div>
                  <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-gray-900" :title="docDisplayName(doc)">{{ docDisplayName(doc) }}</p>
                    <p v-if="doc.size != null" class="text-xs text-gray-500">{{ formatFileSize(doc.size) }}</p>
                  </div>
                  <button
                    type="button"
                    class="shrink-0 rounded p-1.5 text-brand-primary hover:bg-brand-primary-light hover:text-brand-primary-hover"
                    title="Download"
                    @click="downloadDocument(doc)"
                  >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                  </button>
                </div>
              </div>
              <div v-else class="rounded-lg border border-gray-200 bg-gray-50 py-8 text-center text-sm text-gray-500">No documents uploaded.</div>
            </section>

            <!-- Change History -->
            <section class="mb-6">
              <h2 class="mb-3 text-sm font-semibold text-gray-900">Change History</h2>
              <p class="mb-3 text-xs text-gray-500">All field changes with previous value, new value, date/time and who made the change.</p>

              <div v-if="auditsLoading" class="flex items-center justify-center py-8">
                <svg class="h-6 w-6 animate-spin text-brand-primary" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
              </div>

              <div v-else-if="!audits.length" class="rounded-lg border border-gray-200 bg-gray-50 py-6 text-center text-sm text-gray-500">No changes recorded yet.</div>

              <template v-else>
                <div class="overflow-x-auto rounded-lg border border-gray-200">
                  <table class="min-w-full text-left text-sm">
                    <thead class="bg-brand-primary border-b-2 border-green-700">
                      <tr>
                        <th class="px-4 py-2 font-semibold text-white">Field</th>
                        <th class="px-4 py-2 font-semibold text-white">Old Value</th>
                        <th class="px-4 py-2 font-semibold text-white">New Value</th>
                        <th class="px-4 py-2 font-semibold text-white">Date & Time</th>
                        <th class="px-4 py-2 font-semibold text-white">Changed By</th>
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

            <!-- Close Button -->
            <div class="mt-6 flex justify-end border-t border-gray-200 pt-4">
              <router-link
                to="/vas-requests"
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
