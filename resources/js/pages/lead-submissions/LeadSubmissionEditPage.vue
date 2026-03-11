<script setup>
/**
 * Lead Submission Edit – full page with all lead form + back office fields pre-filled from database.
 */
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import leadSubmissionsApi from '@/services/leadSubmissionsApi'
import { useAuthStore } from '@/stores/auth'
import Toast from '@/components/Toast.vue'
import { useFormDraft } from '@/composables/useFormDraft'
import { formatSystemDateTime } from '@/lib/dateFormat'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const loading = ref(true)
const saving = ref(false)
const lead = ref(null)

/* ───── Toast ───── */
const showToast = ref(false)
const toastType = ref('success')
const toastMsg  = ref('')
function toast(t, m) { toastType.value = t; toastMsg.value = m; showToast.value = true }
const primarySectionCollapsed = ref(false)
const teamSectionCollapsed = ref(false)
const backOfficeSectionCollapsed = ref(false)
const documentsSectionCollapsed = ref(false)
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
const accountSyncing = ref(false)

const STATUS_OPTIONS = [
  { value: 'unassigned', label: 'UnAssigned' },
  { value: 'submitted', label: 'Submitted' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'pending_from_sales', label: 'Pending with Sales' },
  { value: 'pending_for_finance', label: 'Pending with Finance' },
  { value: 'pending_for_ata', label: 'Pending for ATA' },
]

function normalizeLeadStatus(status) {
  const value = String(status || '').trim().toLowerCase()
  if (!value || value === 'submitted') return 'unassigned'
  return value
}

function normalizeDuStatus(status) {
  const value = String(status || '').trim().toLowerCase()
  if (value === 'submitted') return 'In Progress'
  return status ?? ''
}

const EMIRATES_OPTIONS = [
  'Abu Dhabi',
  'Dubai',
  'Sharjah',
  'Ajman',
  'Umm Al Quwain',
  'Ras Al Khaimah',
  'Fujairah',
]

const BASE_REQUEST_TYPE_OPTIONS = ['New Submission', 'Resubmission']
const requestTypeOptions = computed(() => {
  const current = String(form.value.submission_type ?? '').trim()
  if (current && !BASE_REQUEST_TYPE_OPTIONS.includes(current)) {
    return [current, ...BASE_REQUEST_TYPE_OPTIONS]
  }
  return BASE_REQUEST_TYPE_OPTIONS
})

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

const DATE_MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
function formatDateDisplay(val) {
  if (!val) return ''
  const d = new Date(val + 'T00:00:00')
  if (Number.isNaN(d.getTime())) return val
  return `${String(d.getDate()).padStart(2, '0')}-${DATE_MONTHS[d.getMonth()]}-${d.getFullYear()}`
}

function openDatePicker(refName) {
  const el = document.querySelector(`input[data-date-ref="${refName}"]`)
  if (el?.showPicker) el.showPicker()
  else el?.click()
}

function formatDateTime(d) {
  return formatSystemDateTime(d, '—')
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
      leadSubmissionsApi.getBackOfficeOptions(true).catch(() => ({})),
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
    const accountNumberValue = data.account_number ?? data.back_office_account ?? ''
    form.value = {
      account_number: accountNumberValue,
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
      status: normalizeLeadStatus(data.status),
      call_verification: data.call_verification ?? '',
      documents_verification: data.documents_verification ?? '',
      submission_date_from: data.submission_date_from ?? '',
      back_office_notes: data.back_office_notes ?? '',
      activity: data.activity ?? '',
      back_office_account: accountNumberValue,
      work_order: data.work_order ?? '',
      du_status: normalizeDuStatus(data.du_status),
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
const showDeleteConfirmModal = ref(false)
const deleteDocCandidate = ref(null)
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
    toast('error', 'Please select one or more files (PDF, DOC, DOCX, EML).')
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
    // Refresh only documents to preserve unsaved form edits.
    const leadRes = await leadSubmissionsApi.getLead(id)
    const latestLead = leadRes?.data ?? leadRes ?? null
    if (lead.value) {
      lead.value.documents = latestLead?.documents ?? lead.value.documents ?? []
    } else {
      lead.value = latestLead
    }
    input.value = ''
    toast('success', 'Document uploaded successfully.')
  } catch (err) {
    const msg = err.response?.data?.message || err.message || 'Upload failed.'
    toast('error', msg)
  } finally {
    uploadingDocs.value = false
  }
}

function requestRemoveDocument(doc) {
  if (!doc?.id) return
  deleteDocCandidate.value = doc
  showDeleteConfirmModal.value = true
}

function closeDeleteConfirmModal() {
  if (removingDocId.value) return
  showDeleteConfirmModal.value = false
  deleteDocCandidate.value = null
}

async function confirmRemoveDocument() {
  const id = leadId.value
  const doc = deleteDocCandidate.value
  if (!id || !doc?.id) return
  const docName = docDisplayName(doc)
  // Close confirmation modal immediately after user confirms.
  closeDeleteConfirmModal()
  removingDocId.value = doc.id
  try {
    await leadSubmissionsApi.deleteDocument(id, doc.id)
    if (lead.value?.documents) {
      lead.value.documents = lead.value.documents.filter((d) => d.id !== doc.id)
    }
    toast('success', `Document "${docName}" deleted successfully.`)
  } catch (err) {
    const msg = err.response?.data?.message || err.message || 'Remove failed.'
    toast('error', msg)
  } finally {
    removingDocId.value = null
  }
}

const fieldErrors = ref({})

function validatePhone(value) {
  if (!value) return null
  if (/\s/.test(value)) return 'Must not contain spaces.'
  if (!/^\d+$/.test(value)) return 'Must contain only digits.'
  if (!value.startsWith('971')) return 'Must start with 971.'
  if (value.length !== 12) return 'Must be exactly 12 digits.'
  return null
}

function validateCoordinates(value) {
  if (!value) return null
  const pattern = /^-?\d{1,3}(\.\d+)?\s*,\s*-?\d{1,3}(\.\d+)?$/
  if (!pattern.test(value)) return 'Invalid format. Use: lat, long (e.g. 25.2048, 55.2708)'
  const [latStr, lonStr] = value.split(',').map(s => s.trim())
  const lat = parseFloat(latStr)
  const lon = parseFloat(lonStr)
  if (lat < -90 || lat > 90) return 'Latitude must be between -90 and 90.'
  if (lon < -180 || lon > 180) return 'Longitude must be between -180 and 180.'
  return null
}

function onPhoneInput(field, event) {
  form.value[field] = event.target.value.replace(/\D/g, '')
  fieldErrors.value[field] = null
}

const AE_DOMAIN_FORBIDDEN_KEYWORDS = ['lac', 'rac', 'rat', 'sgns']
const AE_DOMAIN_SPECIAL = /[@#$%^&*()\-+={}\[\]:;'"\\<>,_?/!_`|\s]/
function validateAeDomain(value) {
  const v = (value || '').trim()
  if (!v) return { valid: true, message: '' }
  if (/\s/.test(v)) return { valid: false, message: 'The domain must not contain spaces.' }
  if (/[0-9]/.test(v)) return { valid: false, message: 'The domain must not contain numbers (0–9).' }
  if (AE_DOMAIN_SPECIAL.test(v)) return { valid: false, message: 'The domain must not contain special characters such as: @ # $ % ^ & * ( ) - + = { } [ ] : ; \' " \\ <> , ? / ! _ ` |' }
  const lower = v.toLowerCase()
  for (const kw of AE_DOMAIN_FORBIDDEN_KEYWORDS) {
    if (lower.includes(kw)) return { valid: false, message: 'The domain must not contain these keywords (case-insensitive): LAC, RAC, RAT, SGNS.' }
  }
  if ((v.match(/\./g) || []).length !== 1) return { valid: false, message: 'The domain must contain only one dot (.).' }
  if (!lower.endsWith('.ae')) return { valid: false, message: 'The domain must end with .ae (example: example.ae).' }
  return { valid: true, message: 'You can use this Domain.' }
}

const aeDomainValidation = computed(() => validateAeDomain(form.value.ae_domain))

function validateFields() {
  const errs = {}
  if (!form.value.company_name?.trim()) errs.company_name = 'Company Name as per Trade License is required.'
  if (!form.value.product?.trim()) errs.product = 'Product is required.'
  if (!form.value.contact_number_gsm?.trim()) {
    errs.contact_number_gsm = 'Contact Number is required.'
  } else {
    const phoneErr = validatePhone(form.value.contact_number_gsm.trim())
    if (phoneErr) errs.contact_number_gsm = phoneErr
  }
  const altPhoneErr = validatePhone(form.value.alternate_contact_number?.trim())
  if (form.value.alternate_contact_number?.trim() && altPhoneErr) errs.alternate_contact_number = altPhoneErr
  if (!form.value.address?.trim()) errs.address = 'Complete Address as per Ejari is required.'
  if (!form.value.emirate?.trim()) errs.emirate = 'Emirates is required.'
  const coordErr = validateCoordinates(form.value.location_coordinates?.trim())
  if (form.value.location_coordinates?.trim() && coordErr) errs.location_coordinates = coordErr
  const aeResult = validateAeDomain(form.value.ae_domain)
  if (form.value.ae_domain?.trim() && !aeResult.valid) errs.ae_domain = aeResult.message
  if (!form.value.manager_id) errs.manager_id = 'Manager Name is required.'
  fieldErrors.value = errs
  return Object.keys(errs).length === 0
}

async function save() {
  const id = leadId.value
  if (!id) return
  if (!validateFields()) return
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
    toast('error', msg)
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

watch(
  () => form.value.account_number,
  (value) => {
    if (accountSyncing.value) return
    if (form.value.back_office_account === value) return
    accountSyncing.value = true
    form.value.back_office_account = value ?? ''
    accountSyncing.value = false
  }
)

watch(
  () => form.value.back_office_account,
  (value) => {
    if (accountSyncing.value) return
    if (form.value.account_number === value) return
    accountSyncing.value = true
    form.value.account_number = value ?? ''
    accountSyncing.value = false
  }
)

onMounted(() => {
  loadLead()
})

</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] bg-gray-100 p-0">
    <div class="w-full">
      <!-- Header + Breadcrumb: background and border -->
      <div class="mb-4 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div class="flex flex-wrap items-baseline gap-2">
            <h1 class="text-xl font-semibold text-gray-900">Edit Lead Submission #{{ leadId }}</h1>            <span v-if="draftSavedAt" class="text-xs text-gray-400 flex items-center gap-1">
              <svg v-if="draftSaving" class="w-3 h-3 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="4" class="opacity-25" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
              <svg v-else class="w-3 h-3 text-brand-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
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
        <svg class="h-10 w-10 animate-spin text-brand-primary" fill="none" viewBox="0 0 24 24">
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
            <div v-if="canEditBackOffice" class="mb-6 flex gap-2 rounded-lg border border-brand-primary-muted bg-brand-primary-light px-4 py-3">
              <svg class="h-5 w-5 shrink-0 text-brand-primary" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
              </svg>
              <div>
                <p class="font-medium text-brand-primary-hover">Back Office Verification</p>
                <p class="text-sm text-brand-primary-hover">Review all information and documents. Edit the back office section below and save changes.</p>
              </div>
            </div>

            <!-- Section 1: Lead Submission Details (editable when canEditBackOffice) -->
            <section class="mb-6">
              <button
                type="button"
                class="mb-3 flex w-full items-center justify-between border-b border-gray-200 pb-2 text-left text-base font-semibold text-gray-900"
                :aria-expanded="(!primarySectionCollapsed).toString()"
                @click.prevent="primarySectionCollapsed = !primarySectionCollapsed"
              >
                <span>Primary Information</span>
                <div class="flex items-center gap-2 text-sm font-medium text-gray-500">
                  <span>{{ primarySectionCollapsed ? 'Show' : 'Minimize' }}</span>
                  <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': primarySectionCollapsed }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </div>
              </button>
              <div v-show="!primarySectionCollapsed" class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                <div>
                  <label class="block text-xs font-medium text-gray-500">ID</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.id) }}</div>
                </div>
                <div v-if="canEditBackOffice">
                  <label class="block text-xs font-medium text-gray-500">Request Type</label>
                  <select v-model="form.submission_type" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                    <option value="">Select Request Type</option>
                    <option v-for="opt in requestTypeOptions" :key="opt" :value="opt">{{ opt }}</option>
                  </select>
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
                  <label class="block text-xs font-medium text-gray-500">Company Name as per Trade License <span class="text-red-500">*</span></label>
                  <template v-if="canEditBackOffice">
                    <input v-model="form.company_name" type="text" class="mt-0.5 w-full rounded border bg-white px-3 py-2 text-sm" :class="fieldErrors.company_name ? 'border-red-500' : 'border-gray-300'" @input="fieldErrors.company_name = null" />
                    <p v-if="fieldErrors.company_name" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.company_name }}</p>
                  </template>
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.company_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Authorized Signatory Name</label>
                  <input v-if="canEditBackOffice" v-model="form.authorized_signatory_name" type="text" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" />
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.authorized_signatory_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Contact Number <span class="text-red-500">*</span></label>
                  <template v-if="canEditBackOffice">
                    <input v-model="form.contact_number_gsm" type="text" maxlength="12" placeholder="971XXXXXXXXX" class="mt-0.5 w-full rounded border bg-white px-3 py-2 text-sm" :class="fieldErrors.contact_number_gsm ? 'border-red-500' : 'border-gray-300'" @input="onPhoneInput('contact_number_gsm', $event)" />
                    <p v-if="fieldErrors.contact_number_gsm" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.contact_number_gsm }}</p>
                  </template>
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.contact_number_gsm) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Alternate Contact Number</label>
                  <template v-if="canEditBackOffice">
                    <input v-model="form.alternate_contact_number" type="text" maxlength="12" placeholder="971XXXXXXXXX" class="mt-0.5 w-full rounded border bg-white px-3 py-2 text-sm" :class="fieldErrors.alternate_contact_number ? 'border-red-500' : 'border-gray-300'" @input="onPhoneInput('alternate_contact_number', $event)" />
                    <p v-if="fieldErrors.alternate_contact_number" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.alternate_contact_number }}</p>
                  </template>
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.alternate_contact_number) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Email ID</label>
                  <input v-if="canEditBackOffice" v-model="form.email" type="email" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" />
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.email) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">.ae Domain</label>
                  <template v-if="canEditBackOffice">
                    <input v-model="form.ae_domain" type="text" placeholder="Enter Domain (e.g. example.ae)" class="mt-0.5 w-full rounded border bg-white px-3 py-2 text-sm" :class="fieldErrors.ae_domain ? 'border-red-500' : 'border-gray-300'" @input="fieldErrors.ae_domain = null" />
                    <p v-if="fieldErrors.ae_domain" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.ae_domain }}</p>
                    <p v-else-if="form.ae_domain?.trim() && aeDomainValidation.valid && aeDomainValidation.message" class="mt-0.5 text-xs text-brand-primary">{{ aeDomainValidation.message }}</p>
                    <p v-else-if="form.ae_domain?.trim() && !aeDomainValidation.valid" class="mt-0.5 text-xs text-red-600">{{ aeDomainValidation.message }}</p>
                  </template>
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.ae_domain) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">GAID</label>
                  <input v-if="canEditBackOffice" v-model="form.gaid" type="text" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" />
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.gaid) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Service Categories</label>
                  <select v-if="canEditBackOffice" v-model="form.service_category_id" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                    <option :value="null">Select</option>
                    <option v-for="c in options.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                  </select>
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ categoryDisplay(lead) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Service Types</label>
                  <select v-if="canEditBackOffice" v-model="form.service_type_id" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                    <option :value="null">Select</option>
                    <option v-for="t in options.serviceTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                  </select>
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ typeNameDisplay(lead) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Product <span class="text-red-500">*</span></label>
                  <template v-if="canEditBackOffice">
                    <input v-model="form.product" type="text" class="mt-0.5 w-full rounded border bg-white px-3 py-2 text-sm" :class="fieldErrors.product ? 'border-red-500' : 'border-gray-300'" @input="fieldErrors.product = null" />
                    <p v-if="fieldErrors.product" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.product }}</p>
                  </template>
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.product) }}</div>
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
                  <label class="block text-xs font-medium text-gray-500">Offer</label>
                  <input v-if="canEditBackOffice" v-model="form.offer" type="text" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" />
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.offer) }}</div>
                </div>
                <div class="sm:col-span-2">
                  <label class="block text-xs font-medium text-gray-500">Complete Address as per Ejari <span class="text-red-500">*</span></label>
                  <template v-if="canEditBackOffice">
                    <input v-model="form.address" type="text" class="mt-0.5 w-full rounded border bg-white px-3 py-2 text-sm" :class="fieldErrors.address ? 'border-red-500' : 'border-gray-300'" @input="fieldErrors.address = null" />
                    <p v-if="fieldErrors.address" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.address }}</p>
                  </template>
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 whitespace-pre-wrap">{{ displayVal(lead.address) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Emirates <span class="text-red-500">*</span></label>
                  <template v-if="canEditBackOffice">
                    <select v-model="form.emirate" class="mt-0.5 w-full rounded border bg-white px-3 py-2 text-sm" :class="fieldErrors.emirate ? 'border-red-500' : 'border-gray-300'" @change="fieldErrors.emirate = null">
                      <option value="">Select Emirates</option>
                      <option v-for="emirate in EMIRATES_OPTIONS" :key="emirate" :value="emirate">{{ emirate }}</option>
                    </select>
                    <p v-if="fieldErrors.emirate" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.emirate }}</p>
                  </template>
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.emirate) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Location Coordinates</label>
                  <template v-if="canEditBackOffice">
                    <input v-model="form.location_coordinates" type="text" placeholder="e.g. 25.2048, 55.2708" class="mt-0.5 w-full rounded border bg-white px-3 py-2 text-sm" :class="fieldErrors.location_coordinates ? 'border-red-500' : 'border-gray-300'" @input="fieldErrors.location_coordinates = null" />
                    <p v-if="fieldErrors.location_coordinates" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.location_coordinates }}</p>
                  </template>
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.location_coordinates) }}</div>
                </div>
              </div>
            </section>

            <!-- Team Information -->
            <section class="mb-6">
              <button
                type="button"
                class="mb-4 flex w-full items-center justify-between border-b border-gray-200 pb-2 text-left text-base font-semibold text-gray-900"
                :aria-expanded="(!teamSectionCollapsed).toString()"
                @click.prevent="teamSectionCollapsed = !teamSectionCollapsed"
              >
                <span>Team Information</span>
                <div class="flex items-center gap-2 text-sm font-medium text-gray-500">
                  <span>{{ teamSectionCollapsed ? 'Show' : 'Minimize' }}</span>
                  <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': teamSectionCollapsed }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </div>
              </button>
              <div v-show="!teamSectionCollapsed" class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                <div>
                  <label class="block text-xs font-medium text-gray-500">Submitter Name</label>
                  <div class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.creator_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Manager Name <span class="text-red-500">*</span></label>
                  <template v-if="canEditBackOffice">
                    <select v-model="form.manager_id" class="mt-0.5 w-full rounded border bg-white px-3 py-2 text-sm" :class="fieldErrors.manager_id ? 'border-red-500' : 'border-gray-300'" @change="fieldErrors.manager_id = null">
                      <option :value="null">Select Manager</option>
                      <option v-for="u in options.managers" :key="u.id" :value="Number(u.id)">{{ u.name }}</option>
                    </select>
                    <p v-if="fieldErrors.manager_id" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.manager_id }}</p>
                  </template>
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.manager_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Team Leader Name</label>
                  <template v-if="canEditBackOffice">
                    <select v-model="form.team_leader_id" class="mt-0.5 w-full rounded border bg-white px-3 py-2 text-sm" :class="fieldErrors.team_leader_id ? 'border-red-500' : 'border-gray-300'" @change="fieldErrors.team_leader_id = null">
                      <option :value="null">Select Team Leader</option>
                      <option v-for="u in options.team_leaders" :key="u.id" :value="Number(u.id)">{{ u.name }}</option>
                    </select>
                    <p v-if="fieldErrors.team_leader_id" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.team_leader_id }}</p>
                  </template>
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.team_leader_name) }}</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Sales Agent Name</label>
                  <template v-if="canEditBackOffice">
                    <select v-model="form.sales_agent_id" class="mt-0.5 w-full rounded border bg-white px-3 py-2 text-sm" :class="fieldErrors.sales_agent_id ? 'border-red-500' : 'border-gray-300'" @change="fieldErrors.sales_agent_id = null">
                      <option :value="null">Select Sales Agent</option>
                      <option v-for="u in options.sales_agents" :key="u.id" :value="Number(u.id)">{{ u.name }}</option>
                    </select>
                    <p v-if="fieldErrors.sales_agent_id" class="mt-0.5 text-xs text-red-600">{{ fieldErrors.sales_agent_id }}</p>
                  </template>
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ displayVal(lead.sales_agent_name) }}</div>
                </div>
              </div>
              <div v-show="!teamSectionCollapsed" class="mt-3 grid grid-cols-1 gap-3">
                <div>
                  <label class="block text-xs font-medium text-gray-500">Comment / Remarks</label>
                  <textarea v-if="canEditBackOffice" v-model="form.remarks" rows="2" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" placeholder="Enter any additional comments or remarks"></textarea>
                  <div v-else class="mt-0.5 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 whitespace-pre-wrap">{{ displayVal(lead.remarks) }}</div>
                </div>
              </div>
            </section>

            <!-- Section 2: Back Office (editable) -->
            <section v-if="canEditBackOffice" class="mb-6">
              <button
                type="button"
                class="mb-3 flex w-full items-center justify-between border-b border-gray-200 pb-2 text-left text-base font-semibold text-gray-900"
                :aria-expanded="(!backOfficeSectionCollapsed).toString()"
                @click.prevent="backOfficeSectionCollapsed = !backOfficeSectionCollapsed"
              >
                <span>Back Office Working Section</span>
                <div class="flex items-center gap-2 text-sm font-medium text-gray-500">
                  <span>{{ backOfficeSectionCollapsed ? 'Show' : 'Minimize' }}</span>
                  <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': backOfficeSectionCollapsed }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </div>
              </button>
              <div v-show="!backOfficeSectionCollapsed" class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                <div>
                  <label class="block text-xs font-medium text-gray-500">Back Office Executive</label>
                  <select v-model="form.executive_id" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                    <option :value="null">Select</option>
                    <option v-for="e in options.executives" :key="e.id" :value="e.id">{{ e.name }}</option>
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
                  <label class="block text-xs font-medium text-gray-500">Status</label>
                  <select v-model="form.status" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm">
                    <option v-for="s in STATUS_OPTIONS" :key="s.value" :value="s.value">{{ s.label }}</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Submission Date</label>
                  <div class="relative mt-0.5 cursor-pointer" @click="openDatePicker('submission_date_from')">
                    <input type="text" readonly :value="formatDateDisplay(form.submission_date_from)" placeholder="DD-MMM-YYYY" class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm cursor-pointer" />
                    <input type="date" v-model="form.submission_date_from" data-date-ref="submission_date_from" class="sr-only" />
                  </div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Activity</label>
                  <input v-model="form.activity" type="text" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" placeholder="Enter Activity" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Account Number</label>
                  <input v-model="form.back_office_account" type="text" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" placeholder="Enter Account Number" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Work Order</label>
                  <input v-model="form.work_order" type="text" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" placeholder="Enter Work Order" />
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
                  <div class="relative mt-0.5 cursor-pointer" @click="openDatePicker('completion_date')">
                    <input type="text" readonly :value="formatDateDisplay(form.completion_date)" placeholder="DD-MMM-YYYY" class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm cursor-pointer" />
                    <input type="date" v-model="form.completion_date" data-date-ref="completion_date" class="sr-only" />
                  </div>
                </div>
              </div>
              <div v-show="!backOfficeSectionCollapsed" class="mt-3">
                <label class="block text-xs font-medium text-gray-500">Back Office Notes</label>
                <textarea v-model="form.back_office_notes" rows="2" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" placeholder="Add verification notes..."></textarea>
              </div>
              <div v-show="!backOfficeSectionCollapsed" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                  <label class="block text-xs font-medium text-gray-500">DU Remarks</label>
                  <textarea v-model="form.du_remarks" rows="2" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" placeholder="Add remarks here..."></textarea>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500">Additional Note</label>
                  <textarea v-model="form.additional_note" rows="2" class="mt-0.5 w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm" placeholder="Add remarks here..."></textarea>
                </div>
              </div>
            </section>

            <!-- Section 3: Documents -->
            <section>
              <div class="mb-3">
                <button
                  type="button"
                  class="flex w-full items-center justify-between border-b border-gray-200 pb-2 text-left text-base font-semibold text-gray-900"
                  :aria-expanded="(!documentsSectionCollapsed).toString()"
                  @click.prevent="documentsSectionCollapsed = !documentsSectionCollapsed"
                >
                  <span>Documents</span>
                  <div class="flex items-center gap-2 text-sm font-medium text-gray-500">
                    <span>{{ documentsSectionCollapsed ? 'Show' : 'Minimize' }}</span>
                    <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': documentsSectionCollapsed }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                  </div>
                </button>
              </div>
              <div v-show="!documentsSectionCollapsed" class="mb-3 flex items-center justify-end">
                <button
                  type="button"
                  class="rounded border border-gray-300 bg-gray-100 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-200 disabled:opacity-50"
                  :disabled="bulkDownloading || !(lead.documents && lead.documents.length)"
                  @click="bulkDownload"
                >
                  {{ bulkDownloading ? 'Preparing...' : 'Bulk Download' }}
                </button>
              </div>
              <div v-show="!documentsSectionCollapsed && lead.documents && lead.documents.length" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
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
                      class="rounded p-1.5 text-brand-primary hover:bg-brand-primary-light hover:text-brand-primary-hover"
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
                      @click="requestRemoveDocument(doc)"
                    >
                      <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>
              <div v-show="!documentsSectionCollapsed && !(lead.documents && lead.documents.length)" class="rounded-lg border border-gray-200 bg-gray-50 py-8 text-center text-sm text-gray-500">No documents uploaded.</div>
              <!-- Add Document cards: one or more slots; user can add more -->
              <div v-show="!documentsSectionCollapsed && canEditBackOffice" class="mt-4 border-t border-gray-200 pt-4">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                  <h3 class="text-base font-semibold text-gray-900">Add Document</h3>
                  <button
                    type="button"
                    class="inline-flex items-center gap-2 text-sm font-medium text-brand-primary hover:text-brand-primary-hover"
                    @click="addDocumentSlot"
                  >
                    <svg class="w-5 h-5 text-brand-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                      <label class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-brand-primary bg-white text-brand-primary-hover text-sm font-medium cursor-pointer hover:bg-brand-primary-light shrink-0 disabled:opacity-50" :class="{ 'opacity-50 pointer-events-none': uploadingDocs }">
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
            </section>

            <!-- Actions -->
            <div v-if="canEditBackOffice" class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 pt-4">
              <button type="button" class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="goBack">Cancel</button>
              <button
                type="button"
                :disabled="saving"
                class="inline-flex items-center gap-2 rounded bg-brand-primary px-4 py-2 text-sm font-medium text-white hover:bg-brand-primary-hover disabled:opacity-70"
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

    <div
      v-if="showDeleteConfirmModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      @click.self="closeDeleteConfirmModal"
    >
      <div class="w-full max-w-md rounded-xl border border-gray-200 bg-white shadow-xl">
        <div class="border-b border-gray-100 px-5 py-4">
          <h3 class="text-base font-semibold text-gray-900">Confirm Document Deletion</h3>
        </div>
        <div class="px-5 py-4 text-sm text-gray-700">
          Delete document
          <span class="font-medium text-gray-900">"{{ docDisplayName(deleteDocCandidate) }}"</span>?
        </div>
        <div class="flex items-center justify-end gap-2 border-t border-gray-100 px-5 py-4">
          <button
            type="button"
            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            :disabled="!!removingDocId"
            @click="closeDeleteConfirmModal"
          >
            Cancel
          </button>
          <button
            type="button"
            class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-70"
            :disabled="!!removingDocId"
            @click="confirmRemoveDocument"
          >
            {{ removingDocId ? 'Deleting...' : 'OK' }}
          </button>
        </div>
      </div>
    </div>

    <Toast :show="showToast" :type="toastType" :message="toastMsg" :duration="4000" @dismiss="showToast = false" />
  </div>
</template>
