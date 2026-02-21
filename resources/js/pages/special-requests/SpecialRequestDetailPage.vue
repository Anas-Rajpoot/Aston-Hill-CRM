<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import specialRequestsApi from '@/services/specialRequestsApi'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import TruncatedText from '@/components/TruncatedText.vue'

const route = useRoute()
const router = useRouter()

const loading = ref(true)
const request = ref(null)
const audits = ref([])

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
  const day = String(date.getDate()).padStart(2, '0')
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const year = date.getFullYear()
  const h = String(date.getHours()).padStart(2, '0')
  const m = String(date.getMinutes()).padStart(2, '0')
  return `${year}-${month}-${day} ${h}:${m}`
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

function statusClass(s) {
  const status = (s || '').toLowerCase()
  if (status === 'approved') return 'bg-green-100 text-green-800'
  if (status === 'rejected') return 'bg-red-100 text-red-800'
  if (status === 'submitted') return 'bg-blue-100 text-blue-800'
  return 'bg-gray-100 text-gray-700'
}

function docDisplayName(doc) {
  return doc?.label || doc?.original_name || doc?.file_name || 'Document'
}

function formatFileSize(bytes) {
  if (bytes == null) return ''
  const n = Number(bytes)
  if (Number.isNaN(n) || n < 0) return ''
  if (n < 1024) return `${n} B`
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`
  return `${(n / (1024 * 1024)).toFixed(1)} MB`
}

async function downloadDocument(doc) {
  if (!request.value?.id || !doc?.id) return
  try {
    const blob = await specialRequestsApi.downloadDocument(request.value.id, doc.id)
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = docDisplayName(doc)
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
  } catch {
    window.open(`/api/special-requests/${request.value.id}/documents/${doc.id}/download`, '_blank')
  }
}

async function loadData() {
  if (!id.value) return
  window.scrollTo(0, 0)
  loading.value = true
  try {
    const [reqData, auditData] = await Promise.all([
      specialRequestsApi.getRequest(id.value),
      specialRequestsApi.getAudits(id.value).catch(() => ({ data: [] })),
    ])
    request.value = reqData
    audits.value = auditData?.data ?? []
  } catch {
    request.value = null
  } finally {
    loading.value = false
  }
}

function goBack() { router.push('/special-requests') }
function goToEdit() { if (request.value?.id) router.push(`/special-requests/${request.value.id}/edit`) }

onMounted(() => loadData())
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] p-0">
    <div class="w-full">
      <div class="border border-black">
        <div class="px-4 py-4 sm:px-5">
          <div class="flex flex-wrap items-center gap-3">
            <button type="button" class="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700" @click="goToEdit">Edit</button>
            <h1 class="text-xl font-semibold text-gray-900">Special Request Details</h1>
            <Breadcrumbs />
          </div>
        </div>

        <div class="border-t border-black" />

        <div v-if="loading" class="flex justify-center px-4 py-16 sm:px-5">
          <svg class="h-10 w-10 animate-spin text-green-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
        </div>

        <div v-else-if="!request" class="px-4 py-8 text-center text-gray-500 sm:px-5">Unable to load request.</div>

        <div v-else class="px-4 py-5 sm:px-5">
          <!-- Basic Information -->
          <section class="mb-6">
            <h2 class="mb-3 border-b border-black pb-2 text-base font-semibold text-gray-900">Basic Information</h2>
            <div class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
              <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="shrink-0 text-sm font-medium text-gray-500">Request ID:</span>
                <span class="text-sm font-medium text-gray-900">{{ request.id }}</span>
              </div>
              <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="shrink-0 text-sm font-medium text-gray-500">Company Name:</span>
                <span class="text-sm font-medium text-gray-900">{{ displayVal(request.company_name) }}</span>
              </div>
              <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="shrink-0 text-sm font-medium text-gray-500">Account Number:</span>
                <span class="text-sm font-medium text-gray-900">{{ displayVal(request.account_number) }}</span>
              </div>
              <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="shrink-0 text-sm font-medium text-gray-500">Request Type:</span>
                <span class="text-sm font-medium text-gray-900">{{ displayVal(request.request_type) }}</span>
              </div>
              <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="shrink-0 text-sm font-medium text-gray-500">Status:</span>
                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(request.status)">{{ displayVal(request.status) }}</span>
              </div>
              <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="shrink-0 text-sm font-medium text-gray-500">Submission Date:</span>
                <span class="text-sm font-medium text-gray-900">{{ formatDateTime(request.submitted_at) }}</span>
              </div>
              <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="shrink-0 text-sm font-medium text-gray-500">Created:</span>
                <span class="text-sm font-medium text-gray-900">{{ formatDateTime(request.created_at) }}</span>
              </div>
            </div>
          </section>

          <!-- Address & Instruction -->
          <section class="mb-6">
            <h2 class="mb-3 border-b border-black pb-2 text-base font-semibold text-gray-900">Details</h2>
            <div class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
              <div>
                <span class="block text-sm font-medium text-gray-500 mb-1">Complete Address:</span>
                <span class="text-sm text-gray-900 whitespace-pre-wrap">{{ displayVal(request.complete_address) }}</span>
              </div>
              <div>
                <span class="block text-sm font-medium text-gray-500 mb-1">Special Instruction:</span>
                <span class="text-sm text-gray-900 whitespace-pre-wrap">{{ displayVal(request.special_instruction) }}</span>
              </div>
            </div>
          </section>

          <!-- Team -->
          <section class="mb-6">
            <h2 class="mb-3 border-b border-black pb-2 text-base font-semibold text-gray-900">Team</h2>
            <div class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-4">
              <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="shrink-0 text-sm font-medium text-gray-500">Manager:</span>
                <span class="text-sm font-medium text-gray-900">{{ displayVal(request.manager_name) }}</span>
              </div>
              <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="shrink-0 text-sm font-medium text-gray-500">Team Leader:</span>
                <span class="text-sm font-medium text-gray-900">{{ displayVal(request.team_leader_name) }}</span>
              </div>
              <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="shrink-0 text-sm font-medium text-gray-500">Sales Agent:</span>
                <span class="text-sm font-medium text-gray-900">{{ displayVal(request.sales_agent_name) }}</span>
              </div>
              <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="shrink-0 text-sm font-medium text-gray-500">Created By:</span>
                <span class="text-sm font-medium text-gray-900">{{ displayVal(request.creator_name) }}</span>
              </div>
            </div>
          </section>

          <!-- Documents -->
          <section class="mb-6">
            <h2 class="mb-3 border-b border-black pb-2 text-base font-semibold text-gray-900">Documents</h2>
            <div v-if="request.documents?.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div v-for="doc in request.documents" :key="doc.id" class="flex items-center gap-3 rounded-lg border border-black p-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-red-50 text-red-600">
                  <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                </div>
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-medium text-gray-900">{{ docDisplayName(doc) }}</p>
                  <p v-if="doc.size" class="mt-0.5 text-xs text-gray-500">{{ formatFileSize(doc.size) }}</p>
                </div>
                <button type="button" class="shrink-0 rounded p-2 text-blue-600 hover:bg-blue-50" @click="downloadDocument(doc)">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                </button>
              </div>
            </div>
            <div v-else><span class="text-sm text-gray-500">No documents</span></div>
          </section>

          <!-- Change History -->
          <section class="mb-2">
            <h2 class="mb-3 border-b border-black pb-2 text-base font-semibold text-gray-900">Change History</h2>
            <div v-if="audits.length" class="overflow-x-auto">
              <table class="w-full border-collapse text-left text-sm">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="border-b border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600">Field</th>
                    <th class="border-b border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600">Old Value</th>
                    <th class="border-b border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600">New Value</th>
                    <th class="border-b border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600">Date/Time</th>
                    <th class="border-b border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600">Changed By</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="a in audits" :key="a.id" class="hover:bg-gray-50">
                    <td class="border-b border-gray-200 px-3 py-2 text-sm text-gray-700">{{ a.field_label ?? a.field_name }}</td>
                    <td class="border-b border-gray-200 px-3 py-2 text-sm text-gray-500 max-w-[350px]"><TruncatedText :text="a.old_value != null && a.old_value !== '' ? formatAuditValue(a.old_display ?? a.old_value) : ''" empty-label="empty" /></td>
                    <td class="border-b border-gray-200 px-3 py-2 text-sm text-gray-900 max-w-[350px]"><TruncatedText :text="a.new_value != null && a.new_value !== '' ? formatAuditValue(a.new_display ?? a.new_value) : ''" empty-label="—" /></td>
                    <td class="border-b border-gray-200 px-3 py-2 text-sm text-gray-500 whitespace-nowrap">{{ formatDateTime(a.changed_at) }}</td>
                    <td class="border-b border-gray-200 px-3 py-2 text-sm text-gray-700">{{ a.changed_by_name ?? '—' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-else><span class="text-sm text-gray-500">No change history</span></div>
          </section>
        </div>

        <div v-if="request" class="border-t border-black px-4 py-4 text-right sm:px-5">
          <button type="button" class="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700" @click="goBack">Close</button>
        </div>
      </div>
    </div>
  </div>
</template>
