<script setup>
/**
 * Lead Submission Edit – full page with all lead form + back office fields pre-filled from database.
 */
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import leadSubmissionsApi from '@/services/leadSubmissionsApi'
import { useAuthStore } from '@/stores/auth'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import { useFormDraft } from '@/composables/useFormDraft'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const loading = ref(true)
const saving = ref(false)
const lead = ref(null)
const options = ref({
  executives: [],
  call_verification_options: [],
  documents_verification_options: [],
  du_status_options: [],
  categories: [],
  serviceTypes: [],
  managers: [],
  team_leaders: [],
  sales_agents: [],
})

const form = ref({
  // Lead fields
  account_number: '',
  company_name: '',
  authorized_signatory_name: '',
  email: '',
  contact_number_gsm: '',
  alternate_contact_number: '',
  address: '',
  emirate: '',
  location_coordinates: '',
  service_category_id: null,
  service_type_id: null,
  product: '',
  offer: '',
  mrc_aed: '',
  quantity: '',
  ae_domain: '',
  gaid: '',
  remarks: '',
  submission_type: '',
  sales_agent_id: null,
  team_leader_id: null,
  manager_id: null,
  // Back office
  executive_id: null,
  status: '',
  call_verification: '',
  documents_verification: '',
  submission_date_from: '',
  back_office_notes: '',
  activity: '',
  back_office_account: '',
  work_order: '',
  du_status: '',
  completion_date: '',
  du_remarks: '',
  additional_note: '',
})

const { draftSaving, draftSavedAt, clearDraft } = useFormDraft('lead-submission', route.params.id || 'new', form)

const STATUS_OPTIONS = [
  { value: '', label: 'Select' },
  { value: 'submitted', label: 'Submitted' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'pending_for_ata', label: 'Pending for ATA' },
  { value: 'pending_for_finance', label: 'Pending for Finance' },
  { value: 'pending_from_sales', label: 'Pending from Sales' },
  { value: 'unassigned', label: 'UnAssigned' },
]

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
  return date.toISOString().slice(0, 10)
}

function formatDateTime(d) {
  if (!d) return '—'
  const date = new Date(d)
  if (Number.isNaN(date.getTime())) return '—'
  const day = String(date.getDate()).padStart(2, '0')
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  const month = months[date.getMonth()]
  const h = String(date.getHours()).padStart(2, '0')
  const m = String(date.getMinutes()).padStart(2, '0')
  return `${day}-${month}-${date.getFullYear()} ${h}:${m}`
}

function formatFileSize(bytes) {
  if (bytes == null) return '—'
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
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

async function loadServiceTypes() {
  const catId = form.value.service_category_id
  if (!catId) {
    options.value.serviceTypes = []
    return
  }
  try {
    const res = await leadSubmissionsApi.getServiceTypesByCategory(catId)
    const list = res?.data ?? res ?? []
    options.value.serviceTypes = Array.isArray(list) ? list : []
  } catch {
    options.value.serviceTypes = []
  }
}

async function loadLead() {
  const id = leadId.value
  if (!id) return
  loading.value = true
  lead.value = null
  try {
    const [leadRes, optionsRes, categoriesRes, teamRes] = await Promise.all([
      leadSubmissionsApi.getLead(id),
      leadSubmissionsApi.getBackOfficeOptions().catch(() => ({})),
      leadSubmissionsApi.getCategories().catch(() => ({ data: [] })),
      leadSubmissionsApi.getTeamOptions().catch(() => ({})),
    ])
    const data = leadRes?.data ?? leadRes
    lead.value = data
    const categories = categoriesRes?.data ?? categoriesRes ?? []
    const teamData = teamRes?.data ?? teamRes ?? {}
    options.value = {
      executives: optionsRes.executives ?? [],
      call_verification_options: optionsRes.call_verification_options ?? [],
      documents_verification_options: optionsRes.documents_verification_options ?? [],
      du_status_options: optionsRes.du_status_options ?? [],
      categories: Array.isArray(categories) ? categories : [],
      serviceTypes: [],
      managers: teamData.managers ?? [],
      team_leaders: teamData.team_leaders ?? [],
      sales_agents: teamData.sales_agents ?? [],
    }
    form.value = {
      account_number: data.account_number ?? '',
      company_name: data.company_name ?? '',
      authorized_signatory_name: data.authorized_signatory_name ?? '',
      email: data.email ?? '',
      contact_number_gsm: data.contact_number_gsm ?? '',
      alternate_contact_number: data.alternate_contact_number ?? '',
      address: data.address ?? '',
      emirate: data.emirate ?? '',
      location_coordinates: data.location_coordinates ?? '',
      service_category_id: data.service_category_id != null ? Number(data.service_category_id) : null,
      service_type_id: data.service_type_id != null ? Number(data.service_type_id) : null,
      product: data.product ?? '',
      offer: data.offer ?? '',
      mrc_aed: data.mrc_aed ?? '',
      quantity: data.quantity ?? '',
      ae_domain: data.ae_domain ?? '',
      gaid: data.gaid ?? '',
      remarks: data.remarks ?? '',
      submission_type: data.submission_type ?? '',
      sales_agent_id: data.sales_agent_id != null ? Number(data.sales_agent_id) : null,
      team_leader_id: data.team_leader_id != null ? Number(data.team_leader_id) : null,
      manager_id: data.manager_id != null ? Number(data.manager_id) : null,
      executive_id: data.executive_id != null ? Number(data.executive_id) : null,
      status: data.status ?? '',
      call_verification: data.call_verification ?? '',
      documents_verification: data.documents_verification ?? '',
      submission_date_from: data.submission_date_from ?? '',
      back_office_notes: data.back_office_notes ?? '',
      activity: data.activity ?? '',
      back_office_account: data.back_office_account ?? '',
      work_order: data.work_order ?? '',
      du_status: data.du_status ?? '',
      completion_date: data.completion_date ?? '',
      du_remarks: data.du_remarks ?? '',
      additional_note: data.additional_note ?? '',
    }
    await loadServiceTypes()
    if (form.value.service_type_id && !options.value.serviceTypes.some((t) => t.id === form.value.service_type_id)) {
      form.value.service_type_id = null
    }
  } catch {
    lead.value = null
  } finally {
    loading.value = false
    window.scrollTo(0, 0)
  }
}

function goBack() {
  router.push(`/lead-submissions/${leadId.value}`)
}

function goToList() {
  router.push('/lead-submissions')
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

const bulkDownloading = ref(false)
const uploadingDocs = ref(false)
const removingDocId = ref(null)
/** Slots for "Add Document" cards; user can add more. */
const addDocumentSlots = ref([{ id: 0 }])
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

function addDocumentSlot() {
  addDocumentSlots.value = [...addDocumentSlots.value, { id: Date.now() }]
}

async function uploadFromInput(e) {
  const input = e?.target
  if (!input?.files?.length) {
    alert('Please select one or more files (PDF, DOC, DOCX, EML).')
    return
  }
  const id = leadId.value
  if (!id) return
  uploadingDocs.value = true
  try {
    const formData = new FormData()
    for (let i = 0; i < input.files.length; i++) {
      formData.append('documents[]', input.files[i])
    }
    await leadSubmissionsApi.uploadDocuments(id, formData)
    await loadLead()
    input.value = ''
  } catch (err) {
    const msg = err.response?.data?.message || err.message || 'Upload failed.'
    alert(msg)
  } finally {
    uploadingDocs.value = false
  }
}

async function removeDocument(doc) {
  const id = leadId.value
  if (!id || !doc?.id) return
  if (!confirm('Remove this document?')) return
  removingDocId.value = doc.id
  try {
    await leadSubmissionsApi.deleteDocument(id, doc.id)
    if (lead.value?.documents) {
      lead.value.documents = lead.value.documents.filter((d) => d.id !== doc.id)
    }
  } catch (err) {
    const msg = err.response?.data?.message || err.message || 'Remove failed.'
    alert(msg)
  } finally {
    removingDocId.value = null
  }
}

async function save() {
  const id = leadId.value
  if (!id) return
  saving.value = true
  try {
    const payload = {
      ...form.value,
      executive_id: form.value.executive_id || null,
      manager_id: form.value.manager_id || null,
      team_leader_id: form.value.team_leader_id || null,
      sales_agent_id: form.value.sales_agent_id || null,
      service_category_id: form.value.service_category_id || null,
      service_type_id: form.value.service_type_id || null,
      submission_date_from: form.value.submission_date_from || null,
      completion_date: form.value.completion_date || null,
    }
    await leadSubmissionsApi.updateBackOffice(id, payload)
    await clearDraft()
    goBack()
  } catch (err) {
    const msg = err.response?.data?.message || err.message || 'Failed to save.'
    alert(msg)
  } finally {
    saving.value = false
  }
}

watch(
  () => form.value.service_category_id,
  async (newCatId, oldCatId) => {
    if (newCatId === oldCatId) return
    await loadServiceTypes()
    const typeId = form.value.service_type_id
    if (typeId && !options.value.serviceTypes.some((t) => t.id === typeId)) {
      form.value.service_type_id = null
    }
  }
)

onMounted(() => {
  loadLead()
})
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-[#f0f2f5] p-0">
    <div class="w-full">
      <!-- Header + Breadcrumb: background and border -->
      <div class="mb-4 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div class="flex flex-wrap items-baseline gap-2">
            <h1 class="text-xl font-semibold text-gray-900">Edit Lead Submission #{{ leadId }}</h1>
            <Breadcrumbs />
            <span v-if="draftSavedAt" class="text-xs text-gray-400 flex items-center gap-1">
              <svg v-if="draftSaving" class="w-3 h-3 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="4" class="opacity-25" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
              <svg v-else class="w-3 h-3 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
              Draft saved
            </span>
          </div>
          <router-link
            :to="'/lead-submissions'"
            class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            Back to List
          </router-link>
        </div>
      </div>

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
            <!-- Info banner -->
            <div v-if="canEditBackOffice" class="mb-6 flex gap-2 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3">
              <svg class="h-5 w-5 shrink-0 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
              </svg>
              <div>
                <p class="font-medium text-blue-800">Back Office Verification</p>
                <p class="text-sm text-blue-700">Review all information and documents. Edit the back office section below and save changes.</p>
              </div>
            </div>

            <!-- Section 1: Lead Submission Details (editable when canEditBackOffice) -->
            <section class="mb-6">
              <h2 class="mb-3 text-sm font-semibold text-gray-900">Lead Submission Details</h2>
              <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div>
                  <label class="block text-xs font-medium text-gray-500">ID</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.id) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Submission Date</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ formatDateTime(lead.submitted_at ?? lead.created_at) }}</div>
                </div>
                <div v-if="canEditBackOffice">
                  <label class="block text-xs font-medium text-gray-500">Request Type</label>
                  <input v-model="form.submission_type" type="text" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" placeholder="Request type" />
                </div>
                <div v-else>
                  <label class="block text-xs font-medium text-gray-500">Request Type</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.submission_type) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Account Number</label>
                  <input v-if="canEditBackOffice" v-model="form.account_number" type="text" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" />
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.account_number) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Company Name</label>
                  <input v-if="canEditBackOffice" v-model="form.company_name" type="text" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" />
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.company_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Authorized Signatory</label>
                  <input v-if="canEditBackOffice" v-model="form.authorized_signatory_name" type="text" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" />
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.authorized_signatory_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Email</label>
                  <input v-if="canEditBackOffice" v-model="form.email" type="email" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" />
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.email) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Contact Number</label>
                  <input v-if="canEditBackOffice" v-model="form.contact_number_gsm" type="text" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" />
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.contact_number_gsm) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Alternate Contact</label>
                  <input v-if="canEditBackOffice" v-model="form.alternate_contact_number" type="text" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" />
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.alternate_contact_number) }}</div>
                </div>
                <div class="sm:col-span-2">
                  <label class="block text-xs font-medium text-gray-500">Address</label>
                  <input v-if="canEditBackOffice" v-model="form.address" type="text" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" />
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.address) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Emirate</label>
                  <input v-if="canEditBackOffice" v-model="form.emirate" type="text" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" />
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.emirate) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Location Coordinates</label>
                  <input v-if="canEditBackOffice" v-model="form.location_coordinates" type="text" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" />
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.location_coordinates) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Service Category</label>
                  <select v-if="canEditBackOffice" v-model="form.service_category_id" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                    <option :value="null">Select</option>
                    <option v-for="c in options.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                  </select>
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ categoryDisplay(lead) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Service Type</label>
                  <select v-if="canEditBackOffice" v-model="form.service_type_id" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                    <option :value="null">Select</option>
                    <option v-for="t in options.serviceTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                  </select>
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ typeNameDisplay(lead) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Product</label>
                  <input v-if="canEditBackOffice" v-model="form.product" type="text" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" />
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.product) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Offer</label>
                  <input v-if="canEditBackOffice" v-model="form.offer" type="text" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" />
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.offer) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">MRC (AED)</label>
                  <input v-if="canEditBackOffice" v-model="form.mrc_aed" type="text" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" />
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.mrc_aed) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Quantity</label>
                  <input v-if="canEditBackOffice" v-model="form.quantity" type="text" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" />
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.quantity) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">AE Domain</label>
                  <input v-if="canEditBackOffice" v-model="form.ae_domain" type="text" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" />
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.ae_domain) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">GAID</label>
                  <input v-if="canEditBackOffice" v-model="form.gaid" type="text" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" />
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.gaid) }}</div>
                </div>
                <div class="sm:col-span-2">
                  <label class="block text-xs font-medium text-gray-500">Remarks</label>
                  <textarea v-if="canEditBackOffice" v-model="form.remarks" rows="2" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm"></textarea>
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 whitespace-pre-wrap">{{ displayVal(lead.remarks) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Sales Agent</label>
                  <select v-if="canEditBackOffice" v-model="form.sales_agent_id" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                    <option :value="null">Select</option>
                    <option v-for="u in options.sales_agents" :key="u.id" :value="Number(u.id)">{{ u.name }}</option>
                  </select>
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.sales_agent_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Team Leader</label>
                  <select v-if="canEditBackOffice" v-model="form.team_leader_id" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                    <option :value="null">Select</option>
                    <option v-for="u in options.team_leaders" :key="u.id" :value="Number(u.id)">{{ u.name }}</option>
                  </select>
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.team_leader_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Manager</label>
                  <select v-if="canEditBackOffice" v-model="form.manager_id" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                    <option :value="null">Select</option>
                    <option v-for="u in options.managers" :key="u.id" :value="Number(u.id)">{{ u.name }}</option>
                  </select>
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.manager_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Created By</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.creator_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Created At</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ formatDateTime(lead.created_at) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Updated At</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ formatDateTime(lead.updated_at) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Status Changed At</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ formatDateTime(lead.status_changed_at) }}</div>
                </div>
              </div>
            </section>

            <!-- Section 2: Back Office (editable) -->
            <section v-if="canEditBackOffice" class="mb-6">
              <h2 class="mb-3 text-sm font-semibold text-gray-900">Back Office Details (Editable)</h2>
              <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div>
                  <label class="block text-xs font-medium text-gray-500">Executive Name</label>
                  <select v-model="form.executive_id" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                    <option :value="null">Select</option>
                    <option v-for="e in options.executives" :key="e.id" :value="e.id">{{ e.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Status</label>
                  <select v-model="form.status" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                    <option v-for="s in STATUS_OPTIONS" :key="s.value" :value="s.value">{{ s.label }}</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Call Verification</label>
                  <select v-model="form.call_verification" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                    <option value="">Select</option>
                    <option v-for="o in options.call_verification_options" :key="o.value" :value="o.value">{{ o.label }}</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Documents Verification</label>
                  <select v-model="form.documents_verification" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                    <option value="">Select</option>
                    <option v-for="o in options.documents_verification_options" :key="o.value" :value="o.value">{{ o.label }}</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Submission Date From</label>
                  <input v-model="form.submission_date_from" type="date" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Activity</label>
                  <input v-model="form.activity" type="text" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" placeholder="Describe Activity" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Back Office Account</label>
                  <input v-model="form.back_office_account" type="text" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" placeholder="Enter Account" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Work Order</label>
                  <input v-model="form.work_order" type="text" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">DU Status</label>
                  <select v-model="form.du_status" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                    <option value="">Select</option>
                    <option v-for="o in options.du_status_options" :key="o.value" :value="o.value">{{ o.label }}</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Completion Date</label>
                  <input v-model="form.completion_date" type="date" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" />
                </div>
              </div>
              <div class="mt-3">
                <label class="block text-xs font-medium text-gray-500">Back Office Notes</label>
                <textarea v-model="form.back_office_notes" rows="3" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" placeholder="Add verification notes..."></textarea>
              </div>
              <div class="mt-3">
                <label class="block text-xs font-medium text-gray-500">DU Remarks</label>
                <textarea v-model="form.du_remarks" rows="2" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" placeholder="Add verification notes..."></textarea>
              </div>
              <div class="mt-3">
                <label class="block text-xs font-medium text-gray-500">Additional Note</label>
                <textarea v-model="form.additional_note" rows="3" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" placeholder="Add any remarks..."></textarea>
              </div>
            </section>

            <!-- Section 3: Documents -->
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
                  <div class="flex shrink-0 items-center gap-1">
                    <button
                      type="button"
                      class="rounded p-1.5 text-blue-600 hover:bg-blue-50 hover:text-blue-700"
                      title="Download"
                      @click="downloadDoc(doc)"
                    >
                      <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                      </svg>
                    </button>
                    <button
                      v-if="canEditBackOffice"
                      type="button"
                      class="rounded p-1.5 text-red-600 hover:bg-red-50 hover:text-red-700"
                      title="Remove"
                      :disabled="removingDocId === doc.id"
                      @click="removeDocument(doc)"
                    >
                      <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>
              <div v-else class="rounded-lg border border-gray-200 bg-gray-50 py-8 text-center text-sm text-gray-500">No documents uploaded.</div>
              <!-- Add Document cards: one or more slots; user can add more -->
              <div v-if="canEditBackOffice" class="mt-4 border-t border-gray-200 pt-4">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                  <h3 class="text-base font-semibold text-gray-900">Add Document</h3>
                  <button
                    type="button"
                    class="inline-flex items-center gap-2 text-sm font-medium text-green-600 hover:text-green-700"
                    @click="addDocumentSlot"
                  >
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Document
                  </button>
                </div>
                <div class="space-y-3">
                  <div
                    v-for="slot in addDocumentSlots"
                    :key="slot.id"
                    class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
                  >
                    <div class="flex items-start gap-3 min-w-0 flex-1">
                      <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                      </div>
                      <div class="min-w-0 flex-1">
                        <p class="font-semibold text-gray-900 text-sm">Additional Document</p>
                        <p class="mt-0.5 text-xs text-gray-500">PDF, DOC, DOCX, EML. You can select multiple files.</p>
                      </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                      <label class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-sky-400 bg-white text-sky-600 text-sm font-medium cursor-pointer hover:bg-sky-50 shrink-0 disabled:opacity-50" :class="{ 'opacity-50 pointer-events-none': uploadingDocs }">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        {{ uploadingDocs ? 'Uploading...' : 'Upload' }}
                        <input type="file" class="hidden" accept=".pdf,.doc,.docx,.eml" multiple @change="uploadFromInput" />
                      </label>
                    </div>
                  </div>
                </div>
              </div>
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

            <!-- Actions -->
            <div v-if="canEditBackOffice" class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 pt-4">
              <button type="button" class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="goBack">Cancel</button>
              <button
                type="button"
                :disabled="saving"
                class="inline-flex items-center gap-2 rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-70"
                @click="save"
              >
                <svg v-if="saving" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                {{ saving ? 'Saving...' : 'Save Changes' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
