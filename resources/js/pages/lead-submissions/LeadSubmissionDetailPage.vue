<script setup>
/**
 * Lead Submission Detail – full page with all submission + back office fields and documents.
 */
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import leadSubmissionsApi from '@/services/leadSubmissionsApi'
import { useAuthStore } from '@/stores/auth'
import Breadcrumbs from '@/components/Breadcrumbs.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const loading = ref(true)
const lead = ref(null)
const bulkDownloading = ref(false)

const leadId = computed(() => {
  const id = route.params.id
  return id != null ? Number(id) : null
})

const canEditBackOffice = computed(() => {
  const roles = auth.user?.roles ?? []
  if (!Array.isArray(roles)) return false
  return roles.some((r) => {
    const name = typeof r === 'string' ? r : r?.name
    return name === 'superadmin' || name === 'backoffice' || name === 'back_office'
  })
})

function displayVal(val) {
  return val != null && val !== '' ? val : '—'
}

function formatDate(d) {
  if (!d) return '—'
  const date = new Date(d)
  if (Number.isNaN(date.getTime())) return '—'
  const day = String(date.getDate()).padStart(2, '0')
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  return `${day}/${months[date.getMonth()]}/${date.getFullYear()}`
}

function submissionDateDisplay(l) {
  return formatDate(l?.submitted_at ?? l?.created_at)
}

function formatStatus(status) {
  if (status == null || status === '') return '—'
  const s = String(status).replace(/_/g, ' ')
  return s.charAt(0).toUpperCase() + s.slice(1).toLowerCase()
}

function formatMrc(val) {
  if (val == null || val === '') return '—'
  const num = Number(val)
  if (Number.isNaN(num)) return val
  return 'AED ' + num.toLocaleString()
}

function categoryDisplay(l) {
  return displayVal(l?.category_name ?? l?.category?.name)
}

function typeNameDisplay(l) {
  return displayVal(l?.type_name ?? l?.type?.name)
}

function docDisplayName(doc) {
  return doc?.original_name || doc?.label || doc?.doc_key || 'Document'
}

function formatFileSize(bytes) {
  if (bytes == null) return '—'
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

async function loadLead() {
  const id = leadId.value
  if (!id) return
  window.scrollTo(0, 0)
  loading.value = true
  lead.value = null
  try {
    const res = await leadSubmissionsApi.getLead(id)
    lead.value = res?.data ?? res
  } catch {
    lead.value = null
  } finally {
    loading.value = false
    window.scrollTo(0, 0)
  }
}

function goBack() {
  router.push('/lead-submissions')
}

function goToEdit() {
  if (lead.value?.id) router.push({ path: '/lead-submissions', query: { openEdit: lead.value.id } })
}

async function downloadDoc(doc) {
  const id = leadId.value
  if (!id) return
  try {
    const blob = await leadSubmissionsApi.downloadDocument(id, doc.id)
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = docDisplayName(doc)
    a.click()
    URL.revokeObjectURL(url)
  } catch {}
}

async function bulkDownload() {
  const id = leadId.value
  if (!id) return
  bulkDownloading.value = true
  try {
    const blob = await leadSubmissionsApi.bulkDownloadDocuments(id)
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `lead-submission-${id}-documents.zip`
    a.click()
    URL.revokeObjectURL(url)
  } catch {} finally {
    bulkDownloading.value = false
  }
}

onMounted(() => {
  loadLead()
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-[#f0f2f5] p-0">
    <div class="mx-auto max-w-5xl">
      <!-- Header -->
      <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-xl font-semibold text-gray-900">Submission Details</h1>
        <div class="flex items-center gap-2">
          <button
            v-if="canEditBackOffice && lead"
            type="button"
            class="inline-flex items-center rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
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
      <Breadcrumbs />

      <div v-if="loading" class="flex justify-center py-16">
        <svg class="h-10 w-10 animate-spin text-green-600" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
      </div>

      <div v-else-if="!lead" class="rounded-lg border border-gray-200 bg-white p-8 text-center text-gray-500">
        Unable to load submission. You may not have permission to view it.
      </div>

      <div v-else class="space-y-6">
        <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
          <div class="px-6 py-4">
            <!-- Basic Information -->
            <section class="mb-6">
              <h2 class="mb-3 text-sm font-semibold text-gray-900">Basic Information</h2>
              <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div>
                  <label class="block text-xs font-medium text-gray-500">Account Number</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.account_number) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Company Name</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.company_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Submission Date</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ submissionDateDisplay(lead) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Status</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ formatStatus(lead.status) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Request Type</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.submission_type) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Authorized Signatory</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.authorized_signatory_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Email</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.email) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Contact (GSM)</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.contact_number_gsm) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Alternate Contact</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.alternate_contact_number) }}</div>
                </div>
                <div class="sm:col-span-2">
                  <label class="block text-xs font-medium text-gray-500">Address</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.address) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Emirate</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.emirate) }}</div>
                </div>
              </div>
            </section>

            <!-- Service Details -->
            <section class="mb-6">
              <h2 class="mb-3 text-sm font-semibold text-gray-900">Service Details</h2>
              <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div>
                  <label class="block text-xs font-medium text-gray-500">Service Category</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ categoryDisplay(lead) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Service Type</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ typeNameDisplay(lead) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Product</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.product) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Offer</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.offer) }}</div>
                </div>
              </div>
            </section>

            <!-- Commercial -->
            <section class="mb-6">
              <h2 class="mb-3 text-sm font-semibold text-gray-900">Commercial</h2>
              <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div>
                  <label class="block text-xs font-medium text-gray-500">MRC (AED)</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ formatMrc(lead.mrc_aed) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Quantity</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.quantity) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">AE Domain</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.ae_domain) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">GAID</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.gaid) }}</div>
                </div>
                <div class="sm:col-span-2">
                  <label class="block text-xs font-medium text-gray-500">Remarks</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.remarks) }}</div>
                </div>
              </div>
            </section>

            <!-- Team & Status -->
            <section class="mb-6">
              <h2 class="mb-3 text-sm font-semibold text-gray-900">Team & Status</h2>
              <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div>
                  <label class="block text-xs font-medium text-gray-500">Sales Agent</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.sales_agent_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Team Leader</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.team_leader_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Manager</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.manager_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Back Office</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.executive_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Created By</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.creator_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Status Changed At</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ formatDate(lead.status_changed_at) }}</div>
                </div>
              </div>
            </section>

            <!-- Back Office / Edit form fields -->
            <section class="mb-6">
              <h2 class="mb-3 text-sm font-semibold text-gray-900">Back Office Details</h2>
              <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div>
                  <label class="block text-xs font-medium text-gray-500">Call Verification</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.call_verification) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Pending from Sales</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.pending_from_sales) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Documents Verification</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.documents_verification) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Submission Date From</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ formatDate(lead.submission_date_from) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Activity</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.activity) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Account (BO)</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.back_office_account) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Work Order</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.work_order) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">DU Status</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.du_status) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Completion Date</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ formatDate(lead.completion_date) }}</div>
                </div>
              </div>
              <div class="mt-3">
                <label class="block text-xs font-medium text-gray-500">Back Office Notes</label>
                <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 whitespace-pre-wrap">{{ displayVal(lead.back_office_notes) }}</div>
              </div>
              <div class="mt-3">
                <label class="block text-xs font-medium text-gray-500">DU Remarks</label>
                <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 whitespace-pre-wrap">{{ displayVal(lead.du_remarks) }}</div>
              </div>
              <div class="mt-3">
                <label class="block text-xs font-medium text-gray-500">Additional Note</label>
                <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 whitespace-pre-wrap">{{ displayVal(lead.additional_note) }}</div>
              </div>
            </section>

            <!-- Documents -->
            <section>
              <h2 class="mb-3 text-sm font-semibold text-gray-900">Documents</h2>
              <div v-if="lead.documents && lead.documents.length" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div
                  v-for="doc in lead.documents"
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
              <div class="mt-3 flex justify-end">
                <button
                  type="button"
                  class="rounded border border-gray-300 bg-gray-100 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-200 disabled:opacity-50"
                  :disabled="bulkDownloading || !(lead.documents && lead.documents.length)"
                  @click="bulkDownload"
                >
                  {{ bulkDownloading ? 'Preparing...' : 'Bulk download' }}
                </button>
              </div>
            </section>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
