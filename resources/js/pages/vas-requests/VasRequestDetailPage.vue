<script setup>
/**
 * VAS Request Details – read-only view: Basic Info, Request, Team, Status & Documents, Creator.
 */
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import vasRequestsApi from '@/services/vasRequestsApi'
import { useAuthStore } from '@/stores/auth'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import TruncatedText from '@/components/TruncatedText.vue'

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

function docDisplayName(doc) {
  return doc?.label || doc?.file_name || doc?.doc_key || 'Document'
}

function formatFileSize(bytes) {
  if (bytes == null || bytes === '') return ''
  const n = Number(bytes)
  if (Number.isNaN(n) || n < 0) return ''
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

function formatAuditSingleValue(v) {
  if (v == null || v === '') return null
  if (typeof v === 'boolean') return v ? 'true' : 'false'
  return String(v)
}

function formatAuditObject(obj) {
  if (Array.isArray(obj)) {
    if (obj.length === 0) return 'empty'
    return obj.map((item, i) => (typeof item === 'object' && item !== null ? formatAuditObject(item) : `${formatAuditSingleValue(item) ?? 'empty'}`)).join(', ')
  }
  if (typeof obj === 'object' && obj !== null) {
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
      return formatAuditObject(JSON.parse(s))
    } catch {}
  }
  return formatAuditSingleValue(s)
}

const FIELD_LABELS = {
  company_name: 'Company Name',
  account_number: 'Account Number',
  request_type: 'Request Type',
  description: 'Description',
  status: 'Status',
  manager_id: 'Manager',
  team_leader_id: 'Team Leader',
  sales_agent_id: 'Sales Agent',
  backoffice_user_id: 'Back Office Executive',
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

function goBack() {
  router.push('/vas-requests')
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
  <div class="min-h-[calc(100vh-4rem)] p-0">
    <div class="w-full">
      <div class="border border-black">
        <div class="px-4 py-4 sm:px-5">
          <div class="flex flex-wrap items-center gap-3">
            <button
              v-if="canEdit"
              type="button"
              class="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
              @click="goToEdit"
            >
              Edit VAS Request
            </button>
            <h1 class="text-xl font-semibold text-gray-900">VAS Request Details</h1>
            <Breadcrumbs />
          </div>
        </div>

        <div class="border-t border-black" />

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
          <!-- Basic Information: label: value with colon and spacing -->
          <section class="mb-6">
            <h2 class="mb-3 border-b border-black pb-2 text-base font-semibold text-gray-900">Basic Information</h2>
            <div class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
              <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="shrink-0 text-sm font-medium text-gray-500">Request ID:</span>
                <span class="text-sm font-medium text-gray-900">{{ request.id }}</span>
              </div>
              <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="shrink-0 text-sm font-medium text-gray-500">Submission Date:</span>
                <span class="text-sm font-medium text-gray-900">{{ formatDateTime(request.submitted_at) }}</span>
              </div>
              <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="shrink-0 text-sm font-medium text-gray-500">Company Name:</span>
                <span class="text-sm font-medium text-gray-900">{{ displayVal(request.company_name) }}</span>
              </div>
              <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="shrink-0 text-sm font-medium text-gray-500">Request Type:</span>
                <span class="text-sm font-medium text-gray-900">{{ displayVal(request.request_type) }}</span>
              </div>
              <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="shrink-0 text-sm font-medium text-gray-500">Account Number:</span>
                <span class="text-sm font-medium text-gray-900">{{ displayVal(request.account_number) }}</span>
              </div>
              <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="shrink-0 text-sm font-medium text-gray-500">Created:</span>
                <span class="text-sm font-medium text-gray-900">{{ formatDateTime(request.created_at) }}</span>
              </div>
            </div>
          </section>

          <!-- Description -->
          <section class="mb-6">
            <div class="flex flex-wrap items-start gap-x-3 gap-y-1">
              <span class="shrink-0 text-sm font-medium text-gray-500">Description:</span>
              <span class="min-w-0 flex-1 text-sm font-medium text-gray-900 whitespace-pre-wrap">{{ displayVal(request.description) }}</span>
            </div>
          </section>

          <!-- Team -->
          <section class="mb-6">
            <h2 class="mb-3 border-b border-black pb-2 text-base font-semibold text-gray-900">Team</h2>
            <div class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-4">
              <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="shrink-0 text-sm font-medium text-gray-500">Sales Agent:</span>
                <span class="text-sm font-medium text-gray-900">{{ displayVal(request.sales_agent_name) }}</span>
              </div>
              <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="shrink-0 text-sm font-medium text-gray-500">Team Leader:</span>
                <span class="text-sm font-medium text-gray-900">{{ displayVal(request.team_leader_name) }}</span>
              </div>
              <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="shrink-0 text-sm font-medium text-gray-500">Manager:</span>
                <span class="text-sm font-medium text-gray-900">{{ displayVal(request.manager_name) }}</span>
              </div>
              <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="shrink-0 text-sm font-medium text-gray-500">Back Office Executive:</span>
                <span class="text-sm font-medium text-gray-900">{{ displayVal(request.back_office_executive_name) }}</span>
              </div>
            </div>
          </section>

          <!-- Documents -->
          <section class="mb-6">
            <h2 class="mb-3 border-b border-black pb-2 text-base font-semibold text-gray-900">Documents</h2>
            <div v-if="request.documents?.length">
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div
                  v-for="doc in request.documents"
                  :key="doc.id"
                  class="flex items-center gap-3 rounded-lg border border-black p-4"
                >
                  <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-red-50 text-red-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                  </div>
                  <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-gray-900">{{ docDisplayName(doc) }}</p>
                    <p v-if="doc.size != null" class="mt-0.5 text-xs text-gray-500">{{ formatFileSize(doc.size) }}</p>
                  </div>
                  <button
                    type="button"
                    class="shrink-0 rounded p-2 text-blue-600 hover:bg-blue-50 hover:text-blue-700"
                    :title="'Download ' + docDisplayName(doc)"
                    @click="downloadDocument(doc)"
                  >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>
            <div v-else>
              <span class="text-sm text-gray-500">No documents</span>
            </div>
          </section>

          <!-- Created By -->
          <section class="mb-2">
            <h2 class="mb-3 border-b border-black pb-2 text-base font-semibold text-gray-900">Created By</h2>
            <div class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
              <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="shrink-0 text-sm font-medium text-gray-500">Creator:</span>
                <span class="text-sm font-medium text-gray-900">{{ displayVal(request.creator_name) }}</span>
              </div>
              <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="shrink-0 text-sm font-medium text-gray-500">Role:</span>
                <span class="text-sm font-medium text-gray-900">{{ displayVal(request.creator_role) }}</span>
              </div>
            </div>
          </section>
        </div>

        <!-- Change History -->
        <div v-if="request" class="border-t border-black px-4 py-5 sm:px-5">
          <h2 class="mb-3 text-base font-semibold text-gray-900">Change History</h2>

          <div v-if="auditsLoading" class="flex items-center justify-center py-8">
            <svg class="h-6 w-6 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
          </div>

          <div v-else-if="!audits.length" class="py-6 text-center text-sm text-gray-400">No changes recorded yet.</div>

          <template v-else>
            <div class="overflow-x-auto rounded-lg border border-gray-200">
              <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="whitespace-nowrap px-4 py-2 text-left font-semibold text-gray-600">Field</th>
                    <th class="whitespace-nowrap px-4 py-2 text-left font-semibold text-gray-600">Old Value</th>
                    <th class="whitespace-nowrap px-4 py-2 text-left font-semibold text-gray-600">New Value</th>
                    <th class="whitespace-nowrap px-4 py-2 text-left font-semibold text-gray-600">Date</th>
                    <th class="whitespace-nowrap px-4 py-2 text-left font-semibold text-gray-600">Changed By</th>
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
              <span class="text-xs text-gray-500">Page {{ auditPage }} of {{ auditTotalPages }}</span>
              <div class="flex gap-1">
                <button type="button" class="rounded border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-40" :disabled="auditPage <= 1" @click="auditPage--">&laquo; Prev</button>
                <button type="button" class="rounded border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-40" :disabled="auditPage >= auditTotalPages" @click="auditPage++">Next &raquo;</button>
              </div>
            </div>
          </template>
        </div>

        <div v-if="request" class="border-t border-black px-4 py-4 text-right sm:px-5">
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
